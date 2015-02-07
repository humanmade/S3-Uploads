<?php

class S3_Uploads_Stream_Wrapper extends Aws\S3\StreamWrapper {

	/**
	 * Register the 's3://' stream wrapper
	 *
	 * @param S3Client $client Client to use with the stream wrapper
	 */
	public static function register( Aws\S3\S3Client $client)
	{
		if (in_array('s3', stream_get_wrappers())) {
			stream_wrapper_unregister('s3');
		}

		stream_wrapper_register('s3', __CLASS__, STREAM_IS_URL);
		static::$client = $client;
	}

	public function stream_metadata( $path, $option, $value ) {
		// not implemented
	}
}