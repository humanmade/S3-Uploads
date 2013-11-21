<?php

/**
 * Upload all images to S3
 * Adapted from Neeki Patel's "Uploads S3" plugin
 */
class S3_Uploads_WordPress_Uploads_Uploader extends S3_Uploads_Uploader {

	public function __construct( $bucket, $key, $secret ) {

		parent::__construct( $bucket, $key, $secret );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'filter_upload_images' ), 10, 2 );
		add_filter( 'update_attached_file', array( $this, 'filter_upload_attachment' ), 10, 2 );
		add_filter( 'wp_get_attachment_url', array( $this, 'filter_get_attachment_url' ), 9, 2 );
		add_action( 'delete_attachment', array( $this, 'filter_delete_attachment' ) );
		add_filter( 'get_attached_file', array( $this, 'filter_get_attachment_url' ), 10, 2 );
	}

	/**
	 * Upload an image to S3 when it's uploaded to WP
	 */
	public function filter_upload_images( $file, $attachment_id ) {

		if ( empty( $file ) )
			return $file;

		$original_file = get_attached_file( $attachment_id, true );

		foreach ( $file['sizes'] as $size => $image ) {
			$thumbnail = dirname( $original_file ) . '/' . $image['file'];

			if ( ! file_exists( $thumbnail ) )
				continue;

			$response = $this->upload_file_to_s3( $thumbnail );
			if ( is_wp_error( $response ) ) {
				echo '<div class="error-div">
					<a class="dismiss" href="#" onclick="jQuery(this).parents(\'div.media-item\').slideUp(200, function(){jQuery(this).remove();});">' . __( 'Dismiss' ) . '</a>
					<strong>' . sprintf(__('&#8220;%s&#8221; has failed to upload due to an error'), esc_html( $_FILES['async-upload']['name'] ) ) . '</strong><br />' .
					esc_html( $response->get_error_message() ) . '</div>';
			}

			unlink( $thumbnail );
		}

		unlink( $original_file );

		return $file;
	}

	/**
	 * Upload an attachment to S3 when it's uploaded to WP
	 * 
	 * @param string $file
	 * @param int    $attachment_id
	 */
	public function filter_upload_attachment( $file, $attachment_id ) {

		if ( ! file_exists( $file ) || strpos( $file, WP_CONTENT_DIR ) !== 0 )
			return $file;

		$relative = str_replace( WP_CONTENT_DIR, '', $file );

		$response = wp_remote_head( $this->get_s3_url() .'/'. $relative );

		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			wp_delete_attachment( $attachment_id, true );
			
			unlink( $file );
			
			echo '<div class="error-div">
					<a class="dismiss" href="#" onclick="jQuery(this).parents(\'div.media-item\').slideUp(200, function(){jQuery(this).remove();});">' . __( 'Dismiss' ) . '</a>
					<strong>' . sprintf(__('&#8220;%s&#8221; has failed to upload due to an error'), esc_html($_FILES['async-upload']['name']) ) . '</strong><br />' .
					esc_html( 'Duplicate file name' ) . '</div>';
			exit;            
		}

		$uploaded_file = $this->upload_file_to_s3( $file );

		if ( is_wp_error( $uploaded_file ) ) {

			wp_delete_attachment( $attachment_id, true );
			unlink( $file );
			
			echo '<div class="error-div">
					<a class="dismiss" href="#" onclick="jQuery(this).parents(\'div.media-item\').slideUp(200, function(){jQuery(this).remove();});">' . __( 'Dismiss' ) . '</a>
					<strong>' . sprintf(__('&#8220;%s&#8221; has failed to upload due to an error'), esc_html( $_FILES['async-upload']['name'] ) ) . '</strong><br />' .
					esc_html( $uploaded_file->get_error_message() ) . '</div>';
			exit;
		}

		update_post_meta( $attachment_id, 's3_path', $relative );
		unlink( $file );

		return $file;
	}

	/**
	 * Replace the saved attachment URL with the bucket URL
	 */
	public function filter_get_attachment_url( $url, $attachment_id ) {
		
		if ( ! $path = get_post_meta( $attachment_id, 's3_path', true ) )
			return $url;

		return $this->get_s3_url() . $path;
	}

	/**
	 * Delete the attachment on S3 when it's deleted locally
	 */
	public function filter_delete_attachment( $attachment_id ) {

		$meta = wp_get_attachment_metadata( $attachment_id );
		$file = get_attached_file( $attachment_id, true );
		
		$this->delete_file_from_s3( $file );

		if ( isset( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size => $image ) {

				$thumbnail = dirname( $file ) . '/' . $image['file'];
				$this->delete_file_from_s3( $thumbnail );
			}
		}
	}
}
