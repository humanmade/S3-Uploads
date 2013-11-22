<?php

/**
 * Upload all images to S3
 * Adapted from Neeki Patel's "Uploads S3" plugin
 */
class S3_Uploads_WordPress_Uploads_Uploader extends S3_Uploads_Uploader {

	public function __construct( $bucket, $key, $secret ) {

		parent::__construct( $bucket, $key, $secret );
		add_filter( 'wp_get_attachment_url', array( $this, 'filter_get_attachment_url' ), 9, 2 );
		add_filter( 'get_attached_file', array( $this, 'filter_get_attached_file' ), 10, 2 );

		add_filter( 'wp_handle_upload', array( $this, 'filter_wp_handle_upload' ) );
		add_filter( 'load_image_to_edit_path', array( $this, 'filter_load_image_to_edit_path' ) );

	}

	public function filter_wp_handle_upload( $file ) {

		$path = str_replace( WP_CONTENT_DIR . '/', '', $file['file'] );
		$new_file = 's3://' . $this->bucket . '/' . $path;
		copy( $file['file'], $new_file );
		unlink( $file['file'] );

		$file['file'] = $new_file;
		$file['url'] = $this->get_s3_url() . '/' . $path;

		return $file;
	}

	public function filter_load_image_to_edit_path( $url ) {

		return $this->get_s3_path_for_public_url( $url );
	}

	/**
	 * Replace the saved attachment URL with the bucket URL
	 */
	public function filter_get_attachment_url( $url, $attachment_id ) {

		$path = get_post_meta( $attachment_id, '_wp_attached_file', true );

		if ( strpos( $path, 's3://' ) !== 0 )
			return $url;

		preg_match( '#s3://.+?/(.+)#', $path, $matches );

		return $this->get_s3_url() . '/' . $matches[1];
	}

	public function filter_get_attached_file( $path, $id ) {

		if ( strpos( $path, 's3://' ) ) {
			return get_post_meta( $id, '_wp_attached_file', true );
		}

		return $path;
	}

	public function is_attachment_uploaded( $attachment_id ) {
		return (bool) get_post_meta( $attachment_id, 's3_path', true );
	}

	private function get_public_url_for_s3_path( $s3_path ) {

		preg_match( '#s3://.+?/(.+)#', $path, $matches );

		return $this->get_s3_url() . '/' . $matches[1];
	}

	private function get_s3_path_for_public_url( $url ) {

		return str_replace( $this->get_s3_url(), 's3://' . $this->bucket, $url );
	}
}
