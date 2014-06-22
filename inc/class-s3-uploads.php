<?php

class S3_Uploads {

	private static $instance;
	private $bucket;
	private $bucket_hostname;
	private $key;
	private $secret;

	public $original_upload_dir;

	/**
	 * 
	 * @return S3_Uploads
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new S3_Uploads( S3_UPLOADS_BUCKET, S3_UPLOADS_KEY, S3_UPLOADS_SECRET, defined( 'S3_UPLOADS_BUCKET_HOSTNAME' ) ? S3_UPLOADS_BUCKET_HOSTNAME : null );
		}

		return self::$instance;
	}

	public function __construct( $bucket, $key, $secret, $bucket_hostname = null ) {
		
		$this->bucket = $bucket;
		$this->key = $key;
		$this->secret = $secret;
		$this->bucket_hostname = $bucket_hostname ? '//' . $bucket_hostname : 'https://' . strtok( $this->bucket, '/' ) . '.s3.amazonaws.com';

		$this->s3()->registerStreamWrapper();
		stream_context_set_option( stream_context_get_default(), 's3', 'seekable', true );
		stream_context_set_option( stream_context_get_default(), 's3', 'ACL', Aws\S3\Enum\CannedAcl::PUBLIC_READ );
		

	}

	public function filter_upload_dir( $dirs ) {

		$this->original_upload_dir = $dirs;

		$dirs['path'] = str_replace( WP_CONTENT_DIR, 's3://' . $this->bucket, $dirs['path'] );
		$dirs['basedir'] = str_replace( WP_CONTENT_DIR, 's3://' . $this->bucket, $dirs['basedir'] );

		if ( ! defined( 'S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL' ) || ! S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL ) {
			$dirs['url'] = str_replace( WP_CONTENT_URL, $this->get_s3_url(), $dirs['url'] );
			$dirs['baseurl'] = str_replace( WP_CONTENT_URL, $this->get_s3_url(), $dirs['baseurl'] );
		}

		return $dirs;
	}

	public function get_s3_url() {
		return $this->bucket_hostname . substr( $this->bucket, strlen( strtok( $this->bucket, '/' ) ) );
	}

	public function get_original_upload_dir() {

		if ( empty( $this->original_upload_dir ) )
			wp_upload_dir();

		return $this->original_upload_dir;
	}
	
	/**
	 * @return Aws\S3\S3Client
	 */
	public function s3() {

		require_once dirname( __FILE__ ) . '/aws-sdk/aws-autoloader.php';

		if ( ! empty( $this->s3 ) )
			return $this->s3;

		$this->s3 = Aws\Common\Aws::factory( array( 'key' => $this->key, 'secret' => $this->secret ) )->get( 's3' );

		return $this->s3;
	}

	public function filter_editors( $editors ) {

		if ( ( $position = array_search( 'WP_Image_Editor_Imagick', $editors ) ) !== false ) {
			unset($editors[$position]);
		}

		return $editors;
	}
}