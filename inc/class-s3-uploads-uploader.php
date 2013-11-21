<?php

class S3_Uploads_Uploader {

	private $bucket;
	private $key;
	private $secret;

	/**
	 * @param string $bucket 
	 * @param string $key
	 * @param string $secret
	 */
	function __construct( $bucket, $key, $secret ) {
		$this->bucket = $bucket;
		$this->key = $key;
		$this->secret = $secret;
	}

	public function get_s3_url() {
		return 'https://' . $this->bucket . '.s3.amazonaws.com';
	}

	/**
	 * Upload a file to S3
	 * 
	 * @param string $file_path The file system path
	 * @return string The path to the uploaded file
	 */
	public function upload_file_to_s3( $file_path ) {

		$relative = str_replace( ABSPATH, '', $file_path );

		try {
			$this->s3()->putObject(array(
				'Bucket' => $this->bucket,
				'Key'    => $relative,
				'Body'   => fopen( $file_path, 'r' ),
				'ACL'	=> Aws\S3\Enum\CannedAcl::PUBLIC_READ
			));
		} catch( Exception $e ) {
			return new WP_Error( 's3-upload-error', $e->getMessage() );
		}
		return $relative;
	}

	/**
	 * Delete a file from S3
	 * 
	 * @param string $file_path s3 path to file
	 * @return bool|WP_Error
	 */
	public function delete_file_from_s3( $file_path ) {

		try {
			$this->s3()->deleteObject( array(
				"Bucket" => $this->bucket,
				"Key" => $file_path
			));
		} catch( Exception $e ) {
			return new WP_Error( 's3-delete-error' );
		}

		return true;
	}

	/**
	 * @return Aws\S3\S3Client
	 */
	private function s3() {

		require_once dirname( __FILE__ ) . '/aws-sdk/aws-autoloader.php';

		if ( ! empty( $this->s3 ) )
			return $this->s3;

		$this->s3 = Aws\Common\Aws::factory( array( 'key' => $this->key, 'secret' => $this->secret ) )->get( 's3' );

		return $this->s3;
	}

}