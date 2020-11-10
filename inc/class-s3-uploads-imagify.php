<?php

use WebPConvert\WebPConvert;
use WebPConvert\Convert\Converters\Stack;


class S3_Uploads_Imagify {

	public $instance;
	public $upload_dir;
	public $imagify;
	private $imagify_key = S3_UPLOADS_IMAGIFY_KEY;

	public function __construct( $instance ) {
		$this->instance = $instance;
		$this->imagify  = new Imagify\Optimizer( $this->imagify_key );
		$this->set_upload_dir();
		if ( ! file_exists( $this->upload_dir['tmp_path'] ) ) {
			mkdir( $this->upload_dir['tmp_path'], 0777, true );
		}
		add_action( 'optimaize_action', array( $this, 'copy_from_s3_to_tmp' ), 10, 3 );
		add_action( 'generate_webp_action', array( $this, 'generate_webp' ) );
		add_action( 'optimize_new_attachment_action', array( $this, 'optimize_new_attachment' ), 10, 2 );

		add_filter( 'wp_generate_attachment_metadata', array( $this, 'next_generate_attachment' ), 10, 3 );
		add_action( 'wp_ajax_next_upload', array( $this, 'next_upload_callback' ) );

	}

	/*
	* Add local and backup path to $upload_dir.
	*/
	public function set_upload_dir() {
		$upload_dir                   = wp_get_upload_dir();
		$upload_dir['tmp_basedir']    = ABSPATH . 'wp-content/uploads/s3_temp';
		$upload_dir['local_basedir']  = ABSPATH . 'wp-content/uploads';
		$upload_dir['tmp_path']       = $upload_dir['tmp_basedir'] . $upload_dir['subdir'];
		$upload_dir['beckup_basedir'] = $upload_dir['basedir'] . '/next_beckup';
		$upload_dir['beckup_path']    = $upload_dir['beckup_basedir'] . $upload_dir['subdir'];
		$this->upload_dir             = $upload_dir;
	}

	/*
	 * Use in wp_generate_attachment_metadata filter.
	 */
	public function next_generate_attachment( $metadata, $attachment_id, $context ) {
		$wp_attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$s3_is_image      = $this->is_image( $wp_attached_file );

		if ( $context === 'create' ) {
			if ( $s3_is_image ) {
				$this->new_image_handle( $metadata, $attachment_id, $context );
			} else {
				$this->new_attachment_handle( $attachment_id );
			}
		} else {
			$this->update_attachment_handle( $metadata, $attachment_id, $context );
		}
		return $metadata;
	}

	/*
	 * This method run every new image upload.
	 */
	public function new_image_handle( $metadata, $attachment_id, $context ) {
		$files_to_copy = $this->get_all_files_to_upload_by_id( $attachment_id, false, true );
		$args          = array(
			'files_to_copy' => $files_to_copy,
			'attachment_id' => $attachment_id,
		);
		wp_schedule_single_event( time(), 'optimize_new_attachment_action', $args );
	}

	/*
	 * This method run with cron job every new image upload.
	 */
	public function optimize_new_attachment( $files_to_copy, $attachment_id ) {
		$files_to_copy = $this->do_copy_files_to_local( $files_to_copy );
		$this->copy_original_file_to_backup( $files_to_copy );
		$files_to_copy = $this->create_webp_files( $files_to_copy );
		$files_to_copy = $this->create_optimize_files( $files_to_copy );
		$files_to_copy = $this->do_copy_files_to_s3( $files_to_copy );
		$this->set_attachment_metadata( $files_to_copy, $attachment_id );
	}

	/*
	 * This method run every non image upload.
	 */
	public function new_attachment_handle( $attachment_id ) {
		update_post_meta( $attachment_id, '_s3_is_image', false );
		update_post_meta( $attachment_id, '_s3_all_image_uploaded', true );
	}

	/*
	 * This method run with AJAX callback to upload and optimize image.
	 */
	public function next_upload_callback() {
		$attachment_id         = $_POST['post_id'];
		$s3_all_image_uploaded = get_post_meta( $attachment_id, '_s3_all_image_uploaded', true );
		$imagify_status        = get_post_meta( $attachment_id, '_imagify_status', true );
		$wp_attached_file      = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$is_image              = $this->is_image( $wp_attached_file );
		$files_to_copy         = array();
		if ( empty( $s3_all_image_uploaded ) && $is_image ) {
			if ( $imagify_status !== 'success' || $imagify_status !== 'already_optimized' ) {
				$files_to_copy = $this->get_all_files_to_upload_by_id( $attachment_id );
				$files_to_copy = $this->do_copy_files_to_local( $files_to_copy );
				$files_to_copy = $this->create_optimize_files( $files_to_copy );
				$files_to_copy = $this->create_webp_files( $files_to_copy );
			} else {
				$files_to_copy['data']['all_image_optimize'] = true;
				$files_to_copy                               = $this->get_all_files_to_upload_by_id( $attachment_id, true );
				$files_to_copy                               = $this->do_copy_files_to_local( $files_to_copy );
				$this->generate_missing_webp( $files_to_copy, $attachment_id );
			}
		} else {
			$files_to_copy = $this->get_all_files_to_upload_by_id( $attachment_id );
		}
		$files_to_copy = $this->do_copy_files_to_s3( $files_to_copy );
		$this->set_attachment_metadata( $files_to_copy, $attachment_id );
		exit();
	}

	/*
	 * This method update the attachment metadata after upload/optimize.
	 */
	public function set_attachment_metadata( $files_to_copy, $attachment_id ) {
		if ( $files_to_copy ) {
			update_post_meta( $attachment_id, '_s3_is_image', $files_to_copy['data']['is_image'] );
			update_post_meta( $attachment_id, '_s3_all_image_uploaded', $files_to_copy['data']['all_image_uploaded'] );
			update_post_meta( $attachment_id, '_s3_all_files', $files_to_copy['sizes'] );
			if ( $files_to_copy['data']['is_image'] ) {
				update_post_meta( $attachment_id, '_s3_webp', true );
				if ( $files_to_copy['data']['all_image_optimize'] ) {
					update_post_meta( $attachment_id, '_imagify_status', 'success' );
				} else {
					update_post_meta( $attachment_id, '_imagify_status', 'failed' );
				}
			}
		}
	}

	/*
	 * This method check if the attachment file is image.
	 */
	public function is_image( $file_name ) {
		$image_extensions = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif' );
		$ext              = pathinfo( $file_name, PATHINFO_EXTENSION );
		if ( in_array( $ext, $image_extensions ) ) {
			return true;
		}
		return false;
	}

	/*
	 * This method is set the $files_to_copy array with all image sizes and webp.
	 */
	public function get_all_files_to_upload_by_id( $attachment_id, $webp = false, $tmp = false ) {
		$files_to_copy    = array();
		$wp_attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$is_image         = $this->is_image( $wp_attached_file );
		$metadata         = wp_get_attachment_metadata( $attachment_id );
		$file_name        = basename( $wp_attached_file );
		if ( $tmp ) {
			$full_file_local = $this->upload_dir['tmp_basedir'] . '/' . $wp_attached_file;
		} else {
			$full_file_local = $this->upload_dir['local_basedir'] . '/' . $wp_attached_file;
		}
		$full_file_s3                      = $this->upload_dir['basedir'] . '/' . $wp_attached_file;
		$full_file_s3_backup               = $this->upload_dir['beckup_basedir'] . '/' . $wp_attached_file;
		$files_to_copy['data']['is_image'] = $is_image;
		$files_to_copy['sizes']['full']    = array(
			'local'     => $full_file_local,
			's3'        => $full_file_s3,
			's3_backup' => $full_file_s3_backup,
		);
		if ( $webp && $is_image ) {
			$files_to_copy['sizes']['full_webp'] = array(
				'local' => $full_file_local . '.webp',
				's3'    => $full_file_s3 . '.webp',
			);
		}
		if ( isset( $metadata['sizes'] ) && $metadata['sizes'] ) {
			foreach ( $metadata['sizes'] as $key => $size ) {
				$size_file_local                = str_replace( $file_name, $size['file'], $full_file_local );
				$size_file_s3                   = str_replace( $file_name, $size['file'], $full_file_s3 );
				$files_to_copy['sizes'][ $key ] = array(
					'local' => $size_file_local,
					's3'    => $size_file_s3,
				);
				if ( $webp && $is_image ) {
					$files_to_copy['sizes'][ $key . '_webp' ] = array(
						'local' => $size_file_local . '.webp',
						's3'    => $size_file_s3 . '.webp',
					);
				}
			}
		}
		return $files_to_copy;
	}

	/*
	 * This method will be used in the future.
	 */
	public function get_non_optimize_attachments() {
		global $wpdb;
		$query       = "SELECT * FROM wp_postmeta WHERE meta_key = 'attachment_optimaize' AND meta_value = false ";
		$attachments = $wpdb->get_results( $query );
		return $attachments;
	}

	public function check_attachments() {
		$attachments = $this->get_non_optimize_attachments();
		if ( $attachments ) {
			foreach ( $attachments as $attachment ) {
				$this->reoptimize_files( $attachment->post_id );
			}
		}

	}

	/*
	 * This method will be used in the future.
	 */
	public function update_attachment_handle( $metadata, $attachment_id, $context ) {
		$full_file     = $this->upload_dir['tmp_basedir'] . $metadata['file'];
		$files_to_copy = array();

		$files_to_copy[] = array(
			'from' => $this->upload_dir['basedir'] . '/' . $metadata['file'],
			'to'   => $full_file,
		);
		foreach ( $metadata['sizes'] as $size ) {
			$size_file       = $this->upload_dir['tmp_path'] . '/' . $size['file'];
			$files_to_copy[] = array(
				'from' => $this->upload_dir['path'] . '/' . $size['file'],
				'to'   => $size_file,
			);
		}
		$args = array(
			'files_to_copy' => $files_to_copy,
			'attachment_id' => $attachment_id,
		);
	}

	/*
	 * This method will be used in the future.
	 */

	public function reoptimize_files( $attachment_id ) {
		$files_to_copy = get_post_meta( $attachment_id, 'attachment_optimaize_failed_files', true );
		if ( $files_to_copy ) {
			$this->do_copy_files( $files_to_copy );
			$this->optimize_files( $files_to_copy, $attachment_id );
		}
	}

	/*
	 * This method use to copy files to S3 bucket.
	 */
	public function do_copy_files_to_s3( $files ) {
		$all_image_uploaded = true;
		if ( ! isset( $files['sizes'] ) && ! $files['sizes'] ) {
			return $files;
		}

		foreach ( $files['sizes'] as $key => $file ) {
			if ( copy( $file['local'], $file['s3'] ) ) {
				$files['sizes'][ $key ]['s3_upload'] = true;
			} else {
				$all_image_uploaded                  = false;
				$files['sizes'][ $key ]['s3_upload'] = false;
			}
		}
		$files['data']['all_image_uploaded'] = $all_image_uploaded;
		return $files;
	}

	/*
	 * This method use to copy files to local uploads/ local backup dir.
	 */

	public function do_copy_files_to_local( $files ) {
		if ( isset( $files['sizes'] ) && $files['sizes'] ) {
			$dir_name = dirname( $files['sizes']['full']['local'] );
			if ( ! is_dir( $dir_name ) ) {
				mkdir( $dir_name, 0755, true );
			}
			foreach ( $files['sizes'] as $key => $file ) {
				copy( $file['s3'], $file['local'] );
			}
		}
		return $files;
	}

	/*
	 * TThis method use to copy files to S3 buckup folder.
	 */

	public function copy_original_file_to_backup( $files ) {
		if ( isset( $files['sizes'] ) && $files['sizes'] ) {
			$original_file = $files['sizes']['full']['local'];
			$backup_file   = $files['sizes']['full']['s3_backup'];
			copy( $original_file, $backup_file );
		}
	}

	/*
	 * This method will be used in the future.
	 */
	public function create_optimize_files( $files ) {
		if ( $files['data']['is_image'] && isset( $files['sizes'] ) && $files['sizes'] ) {
			$tmp_files          = array();
			$tmp_files['data']  = $files['data'];
			$all_image_optimize = true;
			foreach ( $files['sizes'] as $key => $file ) {
				$file_name = $file['local'];
				if ( $this->is_image( $file_name ) ) {
					$param                      = array(
						'keep_exif' => false,
					);
					$handle                     = $this->imagify->optimize( $file_name, $param );
					$tmp_files['sizes'][ $key ] = $file;

					if ( true === $handle->success ) {
						$image_data = file_get_contents( $handle->image );
						file_put_contents( $file_name, $image_data );
						$tmp_files['sizes'][ $key ]['optimize_percent'] = $handle->percent;

					} else {
						$all_image_optimize                             = false;
						$tmp_files['sizes'][ $key ]['optimize_message'] = $handle->detail;
					}
					$tmp_files['sizes'][ $key ]['optimize_status'] = $handle->success;

				} else {
					$tmp_files['sizes'][ $key ] = $file;
				}
			}
			$tmp_files['data']['all_image_optimize'] = $all_image_optimize;
			return $tmp_files;
		} else {
			return $files;
		}
	}

	/*
	 * This method will be used in the future.
	 */

	public function optimize_files( $files, $attachment_id ) {
		$optimaize_failed_files = array();
		foreach ( $files as $file ) {
			$file_name = $file['to'];
			$param     = array(
				'keep_exif' => false,
			);
			$handle    = $this->imagify->optimize( $file_name, $param );
			if ( true === $handle->success ) {
				$image_data = file_get_contents( $handle->image );
				file_put_contents( $file_name, $image_data );
				if ( copy( $file_name, $file['from'] ) ) {
					unlink( $file_name );
				} else {
					$optimaize_failed_files[] = $file;
				}
			} else {
				$optimaize_failed_files[] = $file;
				unlink( $file_name );
			}
		}
		if ( $optimaize_failed_files ) {
			update_post_meta( $attachment_id, 'attachment_optimaize', false );
			update_post_meta( $attachment_id, 'attachment_optimaize_failed_files', $optimaize_failed_files );
		} else {
			update_post_meta( $attachment_id, 'attachment_optimaize', true );
			update_post_meta( $attachment_id, 'attachment_optimaize_failed_files', '' );
		}
	}

	/*
	 * This method create missing webp files.
	 */

	public function generate_missing_webp( $files, $attachment_id ) {
		if ( $files['data']['is_image'] && isset( $files['sizes'] ) && $files['sizes'] ) {
			foreach ( $files['sizes'] as $file ) {
				$ext = pathinfo( $file['local'], PATHINFO_EXTENSION );
				if ( $ext === 'webp' && ! file_exists( $file['from'] ) ) {
					$file_name = str_replace( '.webp', '', $file['local'] );
					Stack::convert( $file_name, $file['local'] );
				}
			}
		}
	}

	public function copy_files_to_local( $files ) {
		if ( $files['data']['is_image'] && isset( $files['sizes'] ) && $files['sizes'] ) {
			foreach ( $files['sizes'] as $key => $file ) {
				echo '<pre>';
				print_r( $file );
				echo '</pre>';
			}
		}
		die();

	}

	/*
	 * This method create webp files for all image sizes.
	 */

	public function create_webp_files( $files ) {
		if ( $files['data']['is_image'] && isset( $files['sizes'] ) && $files['sizes'] ) {
			foreach ( $files['sizes'] as $key => $file ) {
				$webp_file_s3 = $file['s3'] . '.webp';
				$webp_file    = $file['local'] . '.webp';
				Stack::convert( $file['local'], $webp_file );
				$files['sizes'][ $key . '_webp' ] = array(
					'local' => $webp_file,
					's3'    => $webp_file_s3,
				);
			}
		}
		return $files;
	}
}
