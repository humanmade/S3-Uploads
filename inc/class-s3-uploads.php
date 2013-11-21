<?php

class S3_Uploads {

	private static $instance;

	/**
	 * @var S3_Uploads_WordPress_Uploads_Uploader
	 */
	public $wordpress_uploads_uploader;

	/**
	 * 
	 * @return S3_Uploads
	 */
	public static function get_instance() {

		if ( ! self::$instance )
			self::$instance = new S3_Uploads( S3_UPLOADS_BUCKET, S3_UPLOADS_KEY, S3_UPLOADS_SECRET );

		return self::$instance;
	}

	public function __construct( $bucket, $key, $secret ) {
		$this->wordpress_uploads_uploader = new S3_Uploads_WordPress_Uploads_Uploader( $bucket, $key, $secret );
	}
}