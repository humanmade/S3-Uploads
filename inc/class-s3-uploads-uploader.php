<?php

class S3_Uploads_Uploader {

	protected $bucket;
	protected $key;
	protected $secret;

	/**
	 * @param string $bucket 
	 * @param string $key
	 * @param string $secret
	 */
	function __construct( $bucket, $key, $secret ) {
		$this->bucket = $bucket;
		$this->key = $key;
		$this->secret = $secret;

		$this->s3()->registerStreamWrapper();
		stream_context_set_option( stream_context_get_default(), 's3', 'ACL', Aws\S3\Enum\CannedAcl::PUBLIC_READ );
	}

	public function get_s3_url() {
		return 'https://' . $this->bucket . '.s3.amazonaws.com';
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