<?php

namespace S3_Uploads;

use Aws\CacheInterface;
use Aws\LruArrayCache;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3ClientInterface;
use Exception;
use GuzzleHttp\Psr7; //phpcs:ignore -- Used in Psalm types
use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\MimeType;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface; //phpcs:ignore -- Used in Psalm types

// phpcs:disable WordPress.NamingConventions.ValidVariableName.MemberNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
// phpcs:disable WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
// phpcs:disable WordPress.NamingConventions.ValidHookName.NotLowercase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.NotSnakeCase
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fopen

/**
 * Amazon S3 stream wrapper to use "s3://<bucket>/<key>" files with PHP
 * streams, supporting "r", "w", "a", "x".
 *
 * # Opening "r" (read only) streams:
 *
 * Read only streams are truly streaming by default and will not allow you to
 * seek. This is because data read from the stream is not kept in memory or on
 * the local filesystem. You can force a "r" stream to be seekable by setting
 * the "seekable" stream context option true. This will allow true streaming of
 * data from Amazon S3, but will maintain a buffer of previously read bytes in
 * a 'php://temp' stream to allow seeking to previously read bytes from the
 * stream.
 *
 * You may pass any GetObject parameters as 's3' stream context options. These
 * options will affect how the data is downloaded from Amazon S3.
 *
 * # Opening "w" and "x" (write only) streams:
 *
 * Because Amazon S3 requires a Content-Length header, write only streams will
 * maintain a 'php://temp' stream to buffer data written to the stream until
 * the stream is flushed (usually by closing the stream with fclose).
 *
 * You may pass any PutObject parameters as 's3' stream context options. These
 * options will affect how the data is uploaded to Amazon S3.
 *
 * When opening an "x" stream, the file must exist on Amazon S3 for the stream
 * to open successfully.
 *
 * # Opening "a" (write only append) streams:
 *
 * Similar to "w" streams, opening append streams requires that the data be
 * buffered in a "php://temp" stream. Append streams will attempt to download
 * the contents of an object in Amazon S3, seek to the end of the object, then
 * allow you to append to the contents of the object. The data will then be
 * uploaded using a PutObject operation when the stream is flushed (usually
 * with fclose).
 *
 * You may pass any GetObject and/or PutObject parameters as 's3' stream
 * context options. These options will affect how the data is downloaded and
 * uploaded from Amazon S3.
 *
 * Stream context options:
 *
 * - "seekable": Set to true to create a seekable "r" (read only) stream by
 *   using a php://temp stream buffer
 * - For "unlink" only: Any option that can be passed to the DeleteObject
 *   operation
 *
 * @psalm-type StatArray = array{0: int, 1: int, 2: int|string, 3: int, 4: int, 5: int, 6: int, 7: int, 8: int, 9: int, 10: int, 11: int, 12: int, dev: int, ino: int, mode: int|string, nlink: int, uid: int, gid: int, rdev: int, size: int, atime: int, mtime: int, ctime: int, blksize: int, blocks: int}
 * @psalm-type S3ObjectResultArray = array{ContentLength: int, Size: int, LastModified: string, Key: string, Prefix?: string}
 * @psalm-type OptionsArray = array{plugin?: Plugin, cache?: CacheInterface, Bucket: string, Key: string, acl: string, seekable?: bool}
 */
class Stream_Wrapper {

	/** @var ?resource Stream context (this is set by PHP) */
	public $context;

	/** @var ?StreamInterface Underlying stream resource */
	private $body;

	/** @var ?int Size of the body that is opened */
	private $size;

	/** @var array Hash of opened stream parameters */
	private $params = [];

	/** @var ?string Mode in which the stream was opened */
	private $mode;

	/** @var ?\Iterator Iterator used with opendir() related calls */
	private $objectIterator;

	/** @var ?string The bucket that was opened when opendir() was called */
	private $openedBucket;

	/** @var ?string The prefix of the bucket that was opened with opendir() */
	private $openedBucketPrefix;

	/** @var ?string Opened bucket path */
	private $openedPath;

	/** @var ?CacheInterface Cache for object and dir lookups */
	private $cache;

	/** @var string The opened protocol (e.g., "s3") */
	private $protocol = 's3';

	/**
	 * Register the 's3://' stream wrapper
	 *
	 * @param S3ClientInterface $client   Client to use with the stream wrapper
	 * @param string            $protocol Protocol to register as.
	 * @param CacheInterface    $cache    Default cache for the protocol.
	 */
	public static function register(
		Plugin $plugin,
		$protocol = 's3',
		CacheInterface $cache = null
	) {
		if ( in_array( $protocol, stream_get_wrappers() ) ) {
			stream_wrapper_unregister( $protocol );
		}

		// Set the client passed in as the default stream context client
		stream_wrapper_register( $protocol, get_called_class(), STREAM_IS_URL );
		/** @var array{s3: array} */
		$default = stream_context_get_options( stream_context_get_default() );
		$default[ $protocol ]['plugin'] = $plugin;

		if ( $cache ) {
			$default[ $protocol ]['cache'] = $cache;
		} elseif ( ! isset( $default[ $protocol ]['cache'] ) ) {
			// Set a default cache adapter.
			$default[ $protocol ]['cache'] = new LruArrayCache();
		}

		stream_context_set_default( $default );
	}

	public function stream_close() {
		$this->body = null;
		$this->cache = null;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $path
	 * @param string $mode
	 * @param array $options
	 * @param string $opened_path
	 * @return bool
	 */
	public function stream_open( $path, $mode, $options, &$opened_path ) {
		$this->initProtocol( $path );
		$this->params = $this->getBucketKey( $path );
		$this->mode = rtrim( $mode, 'bt' );

		$errors = $this->validate( $path, $this->mode );
		if ( $errors ) {
			return $this->triggerError( $errors );
		}

		return $this->boolCall(
			function() : bool {
				switch ( $this->mode ) {
					case 'r':
						return $this->openReadStream();
					case 'a':
						return $this->openAppendStream();
					default:
						/**
						 * As we open a temp stream, we don't actually know if we have writing ability yet.
						 * This means functions like copy() will not fail correctly, as the write to s3
						 * is only attempted on stream_flush() which is too late to report to copy()
						 * et al that the write has failed.
						 *
						 * As a work around, we attempt to write an empty object.
						 *
						 * Added by Joe Hoyle
						 */
						try {
							$p = $this->params;
							$p['Body'] = '';
							$p = apply_filters( 's3_uploads_putObject_params', $p );
							$this->getClient()->putObject( $p );
						} catch ( Exception $e ) {
							return $this->triggerError( $e->getMessage() );
						}

						return $this->openWriteStream();
				}
			}
		);
	}

	public function stream_eof() : bool {
		if ( ! $this->body ) {
			return true;
		}
		return $this->body->eof();
	}

	public function stream_flush() : bool {
		if ( $this->mode == 'r' ) {
			return false;
		}

		if ( ! $this->body ) {
			return false;
		}

		if ( $this->body->isSeekable() ) {
			$this->body->seek( 0 );
		}
		$params = $this->getOptions( true );
		$params['Body'] = $this->body;

		// Attempt to guess the ContentType of the upload based on the
		// file extension of the key. Added by Joe Hoyle
		if ( ! isset( $params['ContentType'] ) && MimeType::fromFilename( $params['Key'] ) ) {
			$params['ContentType'] = MimeType::fromFilename( $params['Key'] );
		}

		/// Expires:
		if ( defined( 'S3_UPLOADS_HTTP_EXPIRES' ) ) {
			$params['Expires'] = S3_UPLOADS_HTTP_EXPIRES;
		}
		// Cache-Control:
		if ( defined( 'S3_UPLOADS_HTTP_CACHE_CONTROL' ) ) {
			/**
			 * @psalm-suppress RedundantCondition
			 */
			if ( is_numeric( S3_UPLOADS_HTTP_CACHE_CONTROL ) ) {
				$params['CacheControl'] = 'max-age=' . S3_UPLOADS_HTTP_CACHE_CONTROL;
			} else {
				$params['CacheControl'] = S3_UPLOADS_HTTP_CACHE_CONTROL;
			}
		}

		/**
		 * Filter the parameters passed to S3
		 * Theses are the parameters passed to S3Client::putObject()
		 * See; http://docs.aws.amazon.com/aws-sdk-php/latest/class-Aws.S3.S3Client.html#_putObject
		 *
		 * @param array $params S3Client::putObject parameters.
		 */
		$params = apply_filters( 's3_uploads_putObject_params', $params );

		$this->clearCacheKey( "s3://{$params['Bucket']}/{$params['Key']}" );
		return $this->boolCall(
			function () use ( $params ) {
				$bool = (bool) $this->getClient()->putObject( $params );

				/**
				 * Action when a new object has been uploaded to s3.
				 *
				 * @param array  $params S3Client::putObject parameters.
				 */
				do_action( 's3_uploads_putObject', $params );

				return $bool;
			}
		);
	}

	public function stream_read( int $count ) : ?string {
		if ( ! $this->body ) {
			return null;
		}
		return $this->body->read( $count );
	}

	public function stream_seek( int $offset, int $whence = SEEK_SET ) : bool {
		if ( ! $this->body ) {
			return false;
		}
		return ! $this->body->isSeekable()
			? false
			: $this->boolCall(
				function () use ( $offset, $whence ) {
					if ( ! $this->body ) {
						return false;
					}
					$this->body->seek( $offset, $whence );
					return true;
				}
			);
	}

	/**
	 * @param string $path
	 * @param mixed $option
	 * @param mixed $value
	 * @return boolean
	 */
	public function stream_metadata( string $path, $option, $value ) : bool {
		return false;
	}

	/**
	 * @return bool|int
	 */
	public function stream_tell() {
		return $this->boolCall(
			function() {
				if ( ! $this->body ) {
					return false;
				}
				return $this->body->tell();
			}
		);
	}

	public function stream_write( string $data ) : int {
		if ( ! $this->body ) {
			return 0;
		}
		return $this->body->write( $data );
	}

	public function unlink( string $path ) : bool {
		$this->initProtocol( $path );

		return $this->boolCall(
			function () use ( $path ) {
				$this->clearCacheKey( $path );
				$this->getClient()->deleteObject( $this->withPath( $path ) );
				return true;
			}
		);
	}

	/**
	 * @return StatArray
	 */
	public function stream_stat() {
		$stat = $this->getStatTemplate();
		$stat[7] = $this->getSize() ?? 0;
		$stat['size'] = $stat[7];
		$stat[2] = $this->mode ?? 0;
		$stat['mode'] = $stat[2];

		return $stat;
	}

	/**
	 * Provides information for is_dir, is_file, filesize, etc. Works on
	 * buckets, keys, and prefixes.
	 * @link http://www.php.net/manual/en/streamwrapper.url-stat.php
	 *
	 * @return StatArray|bool
	 */
	public function url_stat( string $path, int $flags ) {
		$this->initProtocol( $path );

		$extension = pathinfo( $path, PATHINFO_EXTENSION );
		/**
		 * If the file is actually just a path to a directory
		 * then return it as always existing. This is to work
		 * around wp_upload_dir doing file_exists checks on
		 * the uploads directory on every page load.
		 *
		 * Added by Joe Hoyle
		 */
		if ( ! $extension ) {
			return [
				0         => 0,
				'dev'     => 0,
				1         => 0,
				'ino'     => 0,
				2         => 16895,
				'mode'    => 16895,
				3         => 0,
				'nlink'   => 0,
				4         => 0,
				'uid'     => 0,
				5         => 0,
				'gid'     => 0,
				6         => -1,
				'rdev'    => -1,
				7         => 0,
				'size'    => 0,
				8         => 0,
				'atime'   => 0,
				9         => 0,
				'mtime'   => 0,
				10        => 0,
				'ctime'   => 0,
				11        => -1,
				'blksize' => -1,
				12        => -1,
				'blocks'  => -1,
			];
		}

		// Some paths come through as S3:// for some reason.
		$split = explode( '://', $path );
		$path = strtolower( $split[0] ) . '://' . $split[1];

		// Check if this path is in the url_stat cache
		/** @var StatArray|null */
		$value = $this->getCacheStorage()->get( $path );
		if ( $value ) {
			return $value;
		}

		$stat = $this->createStat( $path, $flags );

		if ( is_array( $stat ) ) {
			$this->getCacheStorage()->set( $path, $stat );
		}

		return $stat;
	}

	/**
	 * Parse the protocol out of the given path.
	 *
	 * @param $path
	 */
	private function initProtocol( string $path ) {
		$parts = explode( '://', $path, 2 );
		$this->protocol = $parts[0] ?: 's3';
	}

	/**
	 *
	 * @param string $path
	 * @param integer $flags
	 * @return StatArray|bool
	 */
	private function createStat( string $path, int $flags ) {
		$this->initProtocol( $path );
		$parts = $this->withPath( $path );

		if ( ! $parts['Key'] ) {
			return $this->statDirectory( $parts, $path, $flags );
		}

		return $this->boolCall(
			function () use ( $parts, $path ) {
				try {
					$result = $this->getClient()->headObject( $parts );
					if ( substr( $parts['Key'], -1, 1 ) == '/' &&
					$result['ContentLength'] == 0
					) {
						// Return as if it is a bucket to account for console
						// bucket objects (e.g., zero-byte object "foo/")
						return $this->formatUrlStat( $path );
					} else {
						// Attempt to stat and cache regular object
						/** @var S3ObjectResultArray */
						$result_array = $result->toArray();
						return $this->formatUrlStat( $result_array );
					}
				} catch ( S3Exception $e ) {
					// Maybe this isn't an actual key, but a prefix. Do a prefix
					// listing of objects to determine.
					$result = $this->getClient()->listObjectsV2(
						[
							'Bucket'  => $parts['Bucket'],
							'Prefix'  => rtrim( $parts['Key'], '/' ) . '/',
							'MaxKeys' => 1,
						]
					);
					if ( ! $result['Contents'] && ! $result['CommonPrefixes'] ) {
						throw new \Exception( "File or directory not found: $path" );
					}
					return $this->formatUrlStat( $path );
				}
			}, $flags
		);
	}

	/**
	 * @param array{Bucket: string, Key: string|null} $parts
	 * @param string $path
	 * @param int $flags
	 * @return StatArray|bool
	 */
	private function statDirectory( $parts, $path, $flags ) {
		// Stat "directories": buckets, or "s3://"
		if ( ! $parts['Bucket'] ||
			$this->getClient()->doesBucketExistV2( $parts['Bucket'], false )
		) {
			return $this->formatUrlStat( $path );
		}

		return $this->triggerError( "File or directory not found: $path", $flags );
	}

	/**
	 * Support for mkdir().
	 *
	 * @param string $path    Directory which should be created.
	 * @param int    $mode    Permissions. 700-range permissions map to
	 *                        ACL_PUBLIC. 600-range permissions map to
	 *                        ACL_AUTH_READ. All other permissions map to
	 *                        ACL_PRIVATE. Expects octal form.
	 * @param int    $options A bitwise mask of values, such as
	 *                        STREAM_MKDIR_RECURSIVE.
	 *
	 * @return bool
	 * @link http://www.php.net/manual/en/streamwrapper.mkdir.php
	 */
	public function mkdir( string $path, int $mode, $options ) : bool {
		$this->initProtocol( $path );
		$params = $this->withPath( $path );
		$this->clearCacheKey( $path );
		if ( ! $params['Bucket'] ) {
			return false;
		}

		if ( ! isset( $params['ACL'] ) ) {
			$params['ACL'] = $this->determineAcl( $mode );
		}

		return empty( $params['Key'] )
			? $this->createBucket( $path, $params )
			: $this->createSubfolder( $path, $params );
	}

	/**
	 * @param string $path
	 * @param mixed $options
	 * @return bool
	 */
	public function rmdir( string $path, $options ) : bool {
		$this->initProtocol( $path );
		$this->clearCacheKey( $path );
		$params = $this->withPath( $path );
		$client = $this->getClient();

		if ( ! $params['Bucket'] ) {
			return $this->triggerError( 'You must specify a bucket' );
		}

		return $this->boolCall(
			function () use ( $params, $path, $client ) {
				if ( ! $params['Key'] ) {
					$client->deleteBucket( [ 'Bucket' => $params['Bucket'] ] );
					return true;
				}
				return $this->deleteSubfolder( $path, $params );
			}
		);
	}

	/**
	 * Support for opendir().
	 *
	 * The opendir() method of the Amazon S3 stream wrapper supports a stream
	 * context option of "listFilter". listFilter must be a callable that
	 * accepts an associative array of object data and returns true if the
	 * object should be yielded when iterating the keys in a bucket.
	 *
	 * @param string $path    The path to the directory
	 *                        (e.g. "s3://dir[</prefix>]")
	 * @param string|null $options Unused option variable
	 *
	 * @return bool true on success
	 * @see http://www.php.net/manual/en/function.opendir.php
	 */
	public function dir_opendir( $path, $options ) {
		$this->initProtocol( $path );
		$this->openedPath = $path;
		$params = $this->withPath( $path );
		/** @var string|null */
		$delimiter = $this->getOption( 'delimiter' );
		/** @var callable|null $filterFn */
		$filterFn = $this->getOption( 'listFilter' );
		$op = [ 'Bucket' => $params['Bucket'] ];
		$this->openedBucket = $params['Bucket'];

		if ( $delimiter === null ) {
			$delimiter = '/';
		}

		if ( $delimiter ) {
			$op['Delimiter'] = $delimiter;
		}

		if ( $params['Key'] ) {
			// Support paths ending in "*" to allow listing of arbitrary prefixes.
			if ( substr( $params['Key'], -1, 1 ) === '*' ) {
				$params['Key'] = rtrim( $params['Key'], '*' );
				// Set the opened bucket prefix to be the directory. This is because $this->openedBucketPrefix
				// will be removed from the resulting keys, and we want to return all files in the directory
				// of the wildcard.
				$this->openedBucketPrefix = substr( $params['Key'], 0, ( strrpos( $params['Key'], '/' ) ?: 0 ) + 1 );
			} else {
				$params['Key'] = rtrim( $params['Key'], $delimiter ) . $delimiter;
				$this->openedBucketPrefix = $params['Key'];
			}
			$op['Prefix'] = $params['Key'];
		}

		// WordPress attempts to scan whole directories via wp_unique_filename(), which can be very slow
		// when there are thousands of files in a single uploads sub directory. This is due to behaviour
		// introduced in https://core.trac.wordpress.org/changeset/46822/. Essentially when a file is uploaded,
		// it's not enough to make sure no filename already exists (and append a `-1` to the end), because
		// image sizes of that image could also conflict with already existing files too. Because image sizes
		// (in the form of -800x600.jpg) can be arbitrary integers, it's not possible to iterate the filesystem
		// for all possible matching / colliding file names. WordPress core uses a preg-match on all files that
		// might conflict with the given filename.
		//
		// Fortunately, we can make use of S3 arbitrary prefixes to optimize this query. The WordPress regex
		// done via _wp_check_existing_file_names() is essentially `^$filename-...`, so we can modify the prefix
		// to include the filename, therefore only return a subset of files from S3 that are more likely to match
		// the preg_match() call.
		//
		// Essentially, wp_unique_filename( my-file.jpg ) doing a `scandir( s3://bucket/2019/04/ )` will actually result in an s3
		// listObjectsV2 query for `s3://bucket/2019/04/my-file` which means even if there are millions of files in `2019/04/` we only
		// return a much smaller subset.
		//
		// Anyone reading this far, brace yourselves for a mighty horrible hack.
		$backtrace = debug_backtrace( 0, 3 ); // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
		if ( isset( $backtrace[1]['function'] )
			&& $backtrace[1]['function'] === 'scandir'
			&& isset( $backtrace[2]['function'] )
			&& $backtrace[2]['function'] === 'wp_unique_filename' && isset( $backtrace[2]['args'][1] )
			&& isset( $op['Prefix'] )
		) {
			/** @var string $filename */
			$filename = $backtrace[2]['args'][1];
			$name = pathinfo( $filename, PATHINFO_FILENAME );
			$op['Prefix'] .= $name;
		}

		// Filter our "/" keys added by the console as directories, and ensure
		// that if a filter function is provided that it passes the filter.
		$this->objectIterator = \Aws\flatmap(
			$this->getClient()->getPaginator( 'ListObjectsV2', $op ),
			function ( Result $result ) use ( $filterFn ) {
				/** @var list<S3ObjectResultArray> */
				$contentsAndPrefixes = $result->search( '[Contents[], CommonPrefixes[]][]' );
				// Filter out dir place holder keys and use the filter fn.
				return array_filter(
					$contentsAndPrefixes,
					function ( $key ) use ( $filterFn ) {
						return ( ! $filterFn || call_user_func( $filterFn, $key ) )
							&& ( ! isset( $key['Key'] ) || substr( $key['Key'], -1, 1 ) !== '/' );
					}
				);
			}
		);

		return true;
	}

	/**
	 * Close the directory listing handles
	 *
	 * @return bool true on success
	 */
	public function dir_closedir() : bool {
		$this->objectIterator = null;
		gc_collect_cycles();

		return true;
	}

	/**
	 * This method is called in response to rewinddir()
	 *
	 * @return bool true on success
	 */
	public function dir_rewinddir() {
		return $this->boolCall(
			function() {
				if ( ! $this->openedPath ) {
					return false;
				}
				$this->objectIterator = null;
				$this->dir_opendir( $this->openedPath, null );
				return true;
			}
		);
	}

	/**
	 * This method is called in response to readdir()
	 *
	 * @return string|bool Should return a string representing the next filename, or
	 *                false if there is no next file.
	 * @link http://www.php.net/manual/en/function.readdir.php
	 */
	public function dir_readdir() {
		// Skip empty result keys
		if ( ! $this->objectIterator || ! $this->objectIterator->valid() ) {
			return false;
		}

		// First we need to create a cache key. This key is the full path to
		// then object in s3: protocol://bucket/key.
		// Next we need to create a result value. The result value is the
		// current value of the iterator without the opened bucket prefix to
		// emulate how readdir() works on directories.
		// The cache key and result value will depend on if this is a prefix
		// or a key.
		/** @var S3ObjectResultArray */
		$cur = $this->objectIterator->current();
		if ( isset( $cur['Prefix'] ) ) {
			// Include "directories". Be sure to strip a trailing "/"
			// on prefixes.
			$result = rtrim( $cur['Prefix'], '/' );
			$key = $this->formatKey( $result );
			$stat = $this->formatUrlStat( $key );
		} else {
			$result = $cur['Key'];
			$key = $this->formatKey( $cur['Key'] );
			$stat = $this->formatUrlStat( $cur );
		}

		// Cache the object data for quick url_stat lookups used with
		// RecursiveDirectoryIterator.
		$this->getCacheStorage()->set( $key, $stat );
		$this->objectIterator->next();

		// Remove the prefix from the result to emulate other stream wrappers.
		return $this->openedBucketPrefix
			? substr( $result, strlen( $this->openedBucketPrefix ) )
			: $result;
	}

	private function formatKey( string $key ) : string {
		$protocol = explode( '://', $this->openedPath ?? '' )[0];
		return "{$protocol}://{$this->openedBucket}/{$key}";
	}

	/**
	 * Called in response to rename() to rename a file or directory. Currently
	 * only supports renaming objects.
	 *
	 * @param string $path_from the path to the file to rename
	 * @param string $path_to   the new path to the file
	 *
	 * @return bool true if file was successfully renamed
	 * @link http://www.php.net/manual/en/function.rename.php
	 */
	public function rename( $path_from, $path_to ) {
		// PHP will not allow rename across wrapper types, so we can safely
		// assume $path_from and $path_to have the same protocol
		$this->initProtocol( $path_from );
		$partsFrom = $this->withPath( $path_from );
		$partsTo = $this->withPath( $path_to );
		$this->clearCacheKey( $path_from );
		$this->clearCacheKey( $path_to );

		if ( ! $partsFrom['Key'] || ! $partsTo['Key'] ) {
			return $this->triggerError(
				'The Amazon S3 stream wrapper only '
				. 'supports copying objects'
			);
		}

		return $this->boolCall(
			function () use ( $partsFrom, $partsTo ) {
				$options = $this->getOptions( true );
				// Copy the object and allow overriding default parameters if
				// desired, but by default copy metadata
				$this->getClient()->copy(
					$partsFrom['Bucket'],
					$partsFrom['Key'],
					$partsTo['Bucket'],
					$partsTo['Key'],
					isset( $options['acl'] ) ? $options['acl'] : 'private',
					$options
				);
				// Delete the original object
				$this->getClient()->deleteObject(
					[
						'Bucket' => $partsFrom['Bucket'],
						'Key'    => $partsFrom['Key'],
					] + $options
				);
				return true;
			}
		);
	}

	public function stream_cast( int $cast_as ) : bool {
		return false;
	}

	/**
	 * Validates the provided stream arguments for fopen and returns an array
	 * of errors.
	 *
	 * @param string $path
	 * @param string $mode
	 * @return string[]
	 */
	private function validate( $path, $mode ) {
		$errors = [];

		if ( ! $this->getOption( 'Key' ) ) {
			$errors[] = 'Cannot open a bucket. You must specify a path in the '
				. 'form of s3://bucket/key';
		}

		if ( ! in_array( $mode, [ 'r', 'w', 'a', 'x' ] ) ) {
			$errors[] = "Mode not supported: {$mode}. "
				. "Use one 'r', 'w', 'a', or 'x'.";
		}

		// When using mode "x" validate if the file exists before attempting
		// to read
		/** @var string */
		$bucket = $this->getOption( 'Bucket' );
		/** @var string */
		$key = $this->getOption( 'Key' );
		if ( $mode == 'x' &&
			$this->getClient()->doesObjectExistV2(
				$bucket,
				$key,
				false,
				$this->getOptions( true )
			)
		) {
			$errors[] = "{$path} already exists on Amazon S3";
		}

		return $errors;
	}

	/**
	 * Get the stream context options available to the current stream
	 *
	 * @param bool $removeContextData Set to true to remove contextual kvp's
	 *                                like 'client' from the result.
	 *
	 * @return OptionsArray
	 */
	private function getOptions( $removeContextData = false ) {
		// Context is not set when doing things like stat
		if ( $this->context === null ) {
			$options = [];
		} else {
			$options = stream_context_get_options( $this->context );
			/** @var array{client?: S3ClientInterface, cache?: CacheInterface, Bucket: string, Key: string, acl: string, seekable?: bool} */
			$options = isset( $options[ $this->protocol ] )
				? $options[ $this->protocol ]
				: [];
		}

		$default = stream_context_get_options( stream_context_get_default() );
		/** @var array{client?: S3ClientInterface, cache?: CacheInterface, Bucket: string, Key: string, acl: string, seekable?: bool} */
		$default = isset( $default[ $this->protocol ] )
			? $default[ $this->protocol ]
			: [];
		/** @var array{client?: S3ClientInterface, cache?: CacheInterface, Bucket: string, Key: string, acl: string, seekable?: bool} */
		$result = $this->params + $options + $default;

		if ( $removeContextData ) {
			unset( $result['client'], $result['seekable'], $result['cache'] );
		}

		return $result;
	}

	/**
	 * Get a specific stream context option
	 *
	 * @param string $name
	 * @return mixed
	 */
	private function getOption( $name ) {
		$options = $this->getOptions();
		return $options[ $name ] ?? null;
	}

	/**
	 * Gets the client from the stream context
	 *
	 * @return S3ClientInterface
	 * @throws \RuntimeException if no client has been configured
	 */
	private function getClient() : S3ClientInterface {
		/** @var Plugin|null */
		$plugin = $this->getOption( 'plugin' );
		if ( ! $plugin ) {
			throw new \RuntimeException( 'No plugin in stream context' );
		}

		return $plugin->s3();
	}

	/**
	 * Get the bucket and key for a given path.
	 *
	 * @param string $path
	 * @return array{Bucket: string, Key: string|null}
	 */
	private function getBucketKey( string $path ) : array {
		// Remove the protocol
		$parts = explode( '://', $path );
		// Get the bucket, key
		$parts = explode( '/', $parts[1], 2 );

		return [
			'Bucket' => $parts[0],
			'Key'    => isset( $parts[1] ) ? $parts[1] : null,
		];
	}

	/**
	 * Get the bucket and key from the passed path (e.g. s3://bucket/key)
	 *
	 * @param string $path Path passed to the stream wrapper
	 *
	 * @return array{Bucket: string, Key: string|null} Hash of 'Bucket', 'Key', and custom params from the context
	 */
	private function withPath( $path ) {
		$params = $this->getOptions( true );

		return $this->getBucketKey( $path ) + $params;
	}

	private function openReadStream() : bool {
		$client = $this->getClient();
		$command = $client->getCommand( 'GetObject', $this->getOptions( true ) );
		if ( is_array( $command['@http'] ) ) {
			$command['@http']['stream'] = true;
		}
		/** @var array{Body: StreamInterface, ContentLength: int} */
		$result = $client->execute( $command );
		$this->size = $result['ContentLength'];
		$this->body = $result['Body'];

		// Wrap the body in a caching entity body if seeking is allowed
		if ( $this->getOption( 'seekable' ) && ! $this->body->isSeekable() ) {
			$this->body = new CachingStream( $this->body );
		}

		return true;
	}

	private function openWriteStream() : bool {
		$this->body = new Stream( fopen( 'php://temp', 'r+' ) );
		return true;
	}

	private function openAppendStream() : bool {
		try {
			// Get the body of the object and seek to the end of the stream
			$client = $this->getClient();
			/** @var array */
			$request = $this->getOptions( true );
			/** @var StreamInterface */
			$this->body = $client->getObject( $request )['Body'];
			$this->body->seek( 0, SEEK_END );
			return true;
		} catch ( S3Exception $e ) {
			// The object does not exist, so use a simple write stream
			return $this->openWriteStream();
		}
	}

	/**
	 * Trigger one or more errors
	 *
	 * @param string[]|string $errors Errors to trigger
	 * @param int        $flags  If set to STREAM_URL_STAT_QUIET, then no
	 *                             error or exception occurs
	 *
	 * @return bool Returns false
	 */
	private function triggerError( $errors, $flags = null ) {
		// This is triggered with things like file_exists()
		if ( $flags && $flags & STREAM_URL_STAT_QUIET ) {
			return false;
		}

		// This is triggered when doing things like lstat() or stat()
		trigger_error( implode( "\n", (array) $errors ), E_USER_WARNING ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return false;
	}

	/**
	 * Prepare a url_stat result array
	 *
	 * @param S3ObjectResultArray|null|false|string $result Data to add
	 *
	 * @return StatArray Returns the modified url_stat result
	 */
	private function formatUrlStat( $result = null ) {
		$stat = $this->getStatTemplate();
		switch ( gettype( $result ) ) {
			case 'NULL':
			case 'string':
				// Directory with 0777 access - see "man 2 stat".
				$stat[2] = 0040777;
				$stat['mode'] = 0040777;
				break;
			case 'array':
				// Regular file with 0777 access - see "man 2 stat".
				$stat[2] = 0100777;
				$stat['mode'] = 0100777;
				// Pluck the content-length if available.
				if ( isset( $result['ContentLength'] ) ) {
					$stat[7] = $result['ContentLength'];
					$stat['size'] = $result['ContentLength'];
				} elseif ( isset( $result['Size'] ) ) {
					$stat[7] = $result['Size'];
					$stat['size'] = $stat[7];
				}
				if ( isset( $result['LastModified'] ) ) {
					// ListObjects or HeadObject result
					$stat[10] = strtotime( $result['LastModified'] );
					$stat['ctime'] = $stat[10];
					$stat[9] = $stat[10];
					$stat['mtime'] = $stat[10];
				}
		}

		return $stat;
	}

	/**
	 * Creates a bucket for the given parameters.
	 *
	 * @param string $path   Stream wrapper path
	 * @param array{Bucket: string} $params A result of StreamWrapper::withPath()
	 *
	 * @return bool Returns true on success or false on failure
	 */
	private function createBucket( $path, array $params ) {
		if ( $this->getClient()->doesBucketExistV2( $params['Bucket'], false ) ) {
			return $this->triggerError( "Bucket already exists: {$path}" );
		}

		return $this->boolCall(
			function () use ( $params, $path ) {
				$this->getClient()->createBucket( $params );
				$this->clearCacheKey( $path );
				return true;
			}
		);
	}

	/**
	 * Creates a pseudo-folder by creating an empty "/" suffixed key
	 *
	 * @param string $path   Stream wrapper path
	 * @param array{Key: string, Bucket: string}  $params A result of StreamWrapper::withPath()
	 *
	 * @return bool
	 */
	private function createSubfolder( string $path, array $params ) {
		// Ensure the path ends in "/" and the body is empty.
		$params['Key'] = rtrim( $params['Key'], '/' ) . '/';
		$params['Body'] = '';

		// Fail if this pseudo directory key already exists
		if ( $this->getClient()->doesObjectExistV2(
			$params['Bucket'],
			$params['Key']
		)
		) {
			return $this->triggerError( "Subfolder already exists: {$path}" );
		}

		return $this->boolCall(
			function () use ( $params, $path ) {
				$this->getClient()->putObject( $params );
				$this->clearCacheKey( $path );
				return true;
			}
		);
	}

	/**
	 * Deletes a nested subfolder if it is empty.
	 *
	 * @param string $path   Path that is being deleted (e.g., 's3://a/b/c')
	 * @param array{Bucket: string, Key: string}  $params A result of StreamWrapper::withPath()
	 *
	 * @return bool
	 */
	private function deleteSubfolder( string $path, array $params ) : bool {
		// Use a key that adds a trailing slash if needed.
		$prefix = rtrim( $params['Key'], '/' ) . '/';
		/** @var array{Contents: list<array{ Key: string }>, CommonPrefixes:array} */
		$result = $this->getClient()->listObjectsV2(
			[
				'Bucket'  => $params['Bucket'],
				'Prefix'  => $prefix,
				'MaxKeys' => 1,
			]
		);

		// Check if the bucket contains keys other than the placeholder
		$contents = $result['Contents'];
		if ( $contents ) {
			return ( count( $contents ) > 1 || $contents[0]['Key'] != $prefix )
				? $this->triggerError( 'Subfolder is not empty' )
				: $this->unlink( rtrim( $path, '/' ) . '/' );
		}

		return $result['CommonPrefixes']
			? $this->triggerError( 'Subfolder contains nested folders' )
			: true;
	}

	/**
	 * Determine the most appropriate ACL based on a file mode.
	 *
	 * @param int $mode File mode
	 *
	 * @return 'public-read'|'authenticated-read'|'private'
	 */
	private function determineAcl( int $mode ) : string {
		switch ( substr( decoct( $mode ), 0, 1 ) ) {
			case '7':
				return 'public-read';
			case '6':
				return 'authenticated-read';
			default:
				return 'private';
		}
	}

	/**
	 * Gets a URL stat template with default values
	 *
	 * @return StatArray
	 *
	 */
	private function getStatTemplate() {
		return [
			0  => 0,
			'dev'     => 0,
			1  => 0,
			'ino'     => 0,
			2  => 0,
			'mode'    => 0,
			3  => 0,
			'nlink'   => 0,
			4  => 0,
			'uid'     => 0,
			5  => 0,
			'gid'     => 0,
			6  => -1,
			'rdev'    => -1,
			7  => 0,
			'size'    => 0,
			8  => 0,
			'atime'   => 0,
			9  => 0,
			'mtime'   => 0,
			10 => 0,
			'ctime'   => 0,
			11 => -1,
			'blksize' => -1,
			12 => -1,
			'blocks'  => -1,
		];
	}

	/**
	 * Invokes a callable and triggers an error if an exception occurs while
	 * calling the function.
	 *
	 * @psalm-template T
	 * @psalm-param callable():T $fn
	 * @param int      $flags
	 *
	 * @psalm-return T|bool
	 */
	private function boolCall( callable $fn, $flags = null ) {
		try {
			return $fn();
		} catch ( \Exception $e ) {
			return $this->triggerError( $e->getMessage(), $flags );
		}
	}

	/**
	 * @return CacheInterface
	 */
	private function getCacheStorage() : CacheInterface {
		if ( ! $this->cache ) {
			/** @var CacheInterface */
			$this->cache = $this->getOption( 'cache' ) ?: new LruArrayCache();
		}

		return $this->cache;
	}

	/**
	 * Clears a specific stat cache value from the stat cache and LRU cache.
	 *
	 * @param string $key S3 path (s3://bucket/key).
	 */
	private function clearCacheKey( $key ) {
		clearstatcache( true, $key );
		$this->getCacheStorage()->remove( $key );
	}

	/**
	 * Returns the size of the opened object body.
	 *
	 * @return int|null
	 */
	private function getSize() {
		if ( ! $this->body ) {
			return null;
		}
		$size = $this->body->getSize();

		return $size !== null ? $size : $this->size;
	}
}
