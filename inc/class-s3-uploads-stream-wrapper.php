<?php

class S3_Uploads_Stream_Wrapper extends Aws\S3\StreamWrapper {

	/**
	 * Register the 's3://' stream wrapper
	 *
	 * @param S3Client $client Client to use with the stream wrapper
	 */
	public static function register( Aws\S3\S3Client $client) {
		stream_wrapper_register( 's3', __CLASS__, STREAM_IS_URL );
		static::$client = $client;
	}

	// Override
	public function stream_flush() {

		/// Expires:
		if ( defined( 'S3_UPLOADS_HTTP_EXPIRES' ) ) {
			$this->params[ 'Expires' ] = S3_UPLOADS_HTTP_EXPIRES;
		}

		// Cache-Control:
		if ( defined( 'S3_UPLOADS_HTTP_CACHE_CONTROL' ) ) {
			if ( is_numeric( S3_UPLOADS_HTTP_CACHE_CONTROL ) ) {
				$this->params[ 'CacheControl' ] = 'max-age='. S3_UPLOADS_HTTP_CACHE_CONTROL;
			} else {
				$this->params[ 'CacheControl' ] = S3_UPLOADS_HTTP_CACHE_CONTROL;
			}
		}

		/**
		 * Filter the parameters passed to S3
		 * Theses are the parameters passed to S3Client::putObject()
		 * See; http://docs.aws.amazon.com/aws-sdk-php/latest/class-Aws.S3.S3Client.html#_putObject
		 *
		 * @param array $params S3Client::putObject paramteres.
		 */
		$this->params = apply_filters( 's3_uploads_putObject_params', $this->params );

		return parent::stream_flush();

	}

	/**
	 * @param string $path
	 * @param string $mode
	 * @param int    $options
	 * @param string $opened_path
	 *
	 * @return bool
	 */
	public function stream_open( $path, $mode, $options, &$opened_path ) {

		$result = parent::stream_open( $path, $mode, $options, $opened_path );

		if ( ! $result ) {
			return $result;
		}

		$mode_short = substr( $mode, 0, 1 );

		if ( $mode_short === 'r' || $mode_short === 'a' ) {
			return $result;
		}

		/**
		 * As we open a temp stream, we don't actually know if we have writing ability yet.
		 * This means functions like copy() will not fail correctly, as the write to s3
		 * is only attemped on stream_flush() which is too late to report to copy()
		 * et al that the write has failed.
		 *
		 * As a work around, we attempt to write an empty object.
		 */
		try {
			$p = $this->params;
			$p['Body'] = '';
			static::$client->putObject($p);
		} catch (\Exception $e) {
			return $this->triggerError($e->getMessage());
		}

		return $result;
	}

	/**
	 * Provides information for is_dir, is_file, filesize, etc. Works on buckets, keys, and prefixes
	 *
	 * This is overrided to handle some optimizations with directories, else wp_upload_dir() causes
	 * a stat() on every page load (atleast once).
	 *
	 * @param string $path
	 * @param int    $flags
	 *
	 * @return array Returns an array of stat data
	 * @link http://www.php.net/manual/en/streamwrapper.url-stat.php
	 */
	public function url_stat( $path, $flags ) {
		$extension = pathinfo($path, PATHINFO_EXTENSION);

		/**
		 * If the file is actually just a path to a directory
		 * then return it as always existing. This is to work
		 * around wp_upload_dir doing file_exists checks on
		 * the uploads directory on every page load
		 */
		if ( ! $extension ) {

			return array (
				0 => 0,
				'dev' => 0,
				1 => 0,
				'ino' => 0,
				2 => 16895,
				'mode' => 16895,
				3 => 0,
				'nlink' => 0,
				4 => 0,
				'uid' => 0,
				5 => 0,
				'gid' => 0,
				6 => -1,
				'rdev' => -1,
				7 => 0,
				'size' => 0,
				8 => 0,
				'atime' => 0,
				9 => 0,
				'mtime' => 0,
				10 => 0,
				'ctime' => 0,
				11 => -1,
				'blksize' => -1,
				12 => -1,
				'blocks' => -1,
			);
		}

		// Check if this path is in the url_stat cache
		if ( isset ( self::$nextStat[ $path ] ) ) {
			return self::$nextStat[ $path ];
		}

		$parts = $this->getParams( $path );

		// Stat a bucket or just s3://
		if ( ! $parts['Key'] && ( ! $parts['Bucket'] || self::$client->doesBucketExist( $parts['Bucket'] ) ) ) {
			return $this->formatUrlStat( $path );
		}

		// You must pass either a bucket or a bucket + key
		if ( ! $parts['Key'] ) {
			return $this->triggerError( "File or directory not found: {$path}", $flags );
		}

		try {
			// Attempt to stat and cache regular object
			return $this->formatUrlStat( self::$client->headObject( $parts )->toArray() );
		} catch ( Exception $e ) {
			return $this->triggerError( $e->getMessage(), $flags );
		}
	}

	public function stream_metadata( $path, $option, $value ) {
		// not implemented
	}
}
