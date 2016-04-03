<?php

/**
 * Local streamwrapper that writes files to the upload dir
 *
 * This is for the most part taken from Drupal, with some modifications.
 */
class S3_Uploads_Local_Stream_Wrapper {
	/**
	 * Stream context resource.
	 *
	 * @var resource
	 */
	public $context;

	/**
	 * A generic resource handle.
	 *
	 * @var resource
	 */
	public $handle = null;

	/**
	 * Instance URI (stream).
	 *
	 * A stream is referenced as "scheme://target".
	 *
	 * @var string
	 */
	protected $uri;

	/**
	 * Gets the path that the wrapper is responsible for.
	 *
	 * @return string
	 *   String specifying the path.
	 */
	static function getDirectoryPath() {
		$upload_dir = S3_Uploads::get_instance()->get_original_upload_dir();
		return $upload_dir['basedir'] . '/s3';
	}

	function setUri( $uri ) {
		$this->uri = $uri;
	}

	function getUri() {
		return $this->uri;
	}

	/**
	 * Returns the local writable target of the resource within the stream.
	 *
	 * This function should be used in place of calls to realpath() or similar
	 * functions when attempting to determine the location of a file. While
	 * functions like realpath() may return the location of a read-only file, this
	 * method may return a URI or path suitable for writing that is completely
	 * separate from the URI used for reading.
	 *
	 * @param string $uri
	 *   Optional URI.
	 *
	 * @return string|bool
	 *   Returns a string representing a location suitable for writing of a file,
	 *   or FALSE if unable to write to the file such as with read-only streams.
	 */
	protected function getTarget( $uri = null ) {
		if ( ! isset( $uri ) ) {
			$uri = $this->uri;
		}

		list( $scheme, $target) = explode( '://', $uri, 2 );

		// Remove erroneous leading or trailing, forward-slashes and backslashes.
		return trim( $target, '\/' );
	}

	static function getMimeType( $uri, $mapping = null ) {

		$extension = '';
		$file_parts = explode( '.', basename( $uri ) );

		// Remove the first part: a full filename should not match an extension.
		array_shift( $file_parts );

		// Iterate over the file parts, trying to find a match.
		// For my.awesome.image.jpeg, we try:
		//   - jpeg
		//   - image.jpeg, and
		//   - awesome.image.jpeg
		while ( $additional_part = array_pop( $file_parts ) ) {
			$extension = strtolower( $additional_part . ( $extension ? '.' . $extension : '' ) );
			if ( isset( $mapping['extensions'][ $extension ] ) ) {
				return $mapping['mimetypes'][ $mapping['extensions'][ $extension ] ];
			}
		}

		return 'application/octet-stream';
	}

	function chmod( $mode ) {
		$output = @chmod( $this->getLocalPath(), $mode );
		// We are modifying the underlying file here, so we have to clear the stat
		// cache so that PHP understands that URI has changed too.
		clearstatcache( true, $this->getLocalPath() );
		return $output;
	}

	function realpath() {
		return $this->getLocalPath();
	}

	/**
	 * Returns the canonical absolute path of the URI, if possible.
	 *
	 * @param string $uri
	 *   (optional) The stream wrapper URI to be converted to a canonical
	 *   absolute path. This may point to a directory or another type of file.
	 *
	 * @return string|bool
	 *   If $uri is not set, returns the canonical absolute path of the URI
	 *   previously. If $uri is set and valid for this class, returns its canonical absolute
	 *   path, as determined by the realpath() function. If $uri is set but not
	 *   valid, returns FALSE.
	 */
	protected function getLocalPath( $uri = null ) {
		if ( ! isset( $uri ) ) {
			$uri = $this->uri;
		}
		$path = $this->getDirectoryPath() . '/' . $this->getTarget( $uri );
		$realpath = $path;

		$directory = realpath( $this->getDirectoryPath() );

		if ( ! $realpath || ! $directory || strpos( $realpath, $directory ) !== 0 ) {
			return false;
		}
		return $realpath;
	}

	/**
	 * Support for fopen(), file_get_contents(), file_put_contents() etc.
	 *
	 * @param string $uri
	 *   A string containing the URI to the file to open.
	 * @param int $mode
	 *   The file mode ("r", "wb" etc.).
	 * @param int $options
	 *   A bit mask of STREAM_USE_PATH and STREAM_REPORT_ERRORS.
	 * @param string $opened_path
	 *   A string containing the path actually opened.
	 *
	 * @return bool
	 *   Returns TRUE if file was opened successfully.
	 *
	 * @see http://php.net/manual/streamwrapper.stream-open.php
	 */
	public function stream_open( $uri, $mode, $options, &$opened_path ) {
		$this->uri = $uri;
		$path = $this->getLocalPath();
		$this->handle = ( $options & STREAM_REPORT_ERRORS ) ? fopen( $path, $mode ) : @fopen( $path, $mode );

		if ( (bool) $this->handle && $options & STREAM_USE_PATH ) {
			$opened_path = $path;
		}

		return (bool) $this->handle;
	}

	/**
	 * Support for flock().
	 *
	 * @param int $operation
	 *   One of the following:
	 *   - LOCK_SH to acquire a shared lock (reader).
	 *   - LOCK_EX to acquire an exclusive lock (writer).
	 *   - LOCK_UN to release a lock (shared or exclusive).
	 *   - LOCK_NB if you don't want flock() to block while locking (not
	 *     supported on Windows).
	 *
	 * @return bool
	 *   Always returns TRUE at the present time.
	 *
	 * @see http://php.net/manual/streamwrapper.stream-lock.php
	 */
	public function stream_lock( $operation ) {
		if ( in_array( $operation, array( LOCK_SH, LOCK_EX, LOCK_UN, LOCK_NB ) ) ) {
			return flock( $this->handle, $operation );
		}

		return true;
	}

	/**
	 * Support for fread(), file_get_contents() etc.
	 *
	 * @param int $count
	 *   Maximum number of bytes to be read.
	 *
	 * @return string|bool
	 *   The string that was read, or FALSE in case of an error.
	 *
	 * @see http://php.net/manual/streamwrapper.stream-read.php
	 */
	public function stream_read( $count ) {
		return fread( $this->handle, $count );
	}

	/**
	 * Support for fwrite(), file_put_contents() etc.
	 *
	 * @param string $data
	 *   The string to be written.
	 *
	 * @return int
	 *   The number of bytes written.
	 *
	 * @see http://php.net/manual/streamwrapper.stream-write.php
	 */
	public function stream_write( $data ) {
		return fwrite( $this->handle, $data );
	}

	/**
	 * Support for feof().
	 *
	 * @return bool
	 *   TRUE if end-of-file has been reached.
	 *
	 * @see http://php.net/manual/streamwrapper.stream-eof.php
	 */
	public function stream_eof() {
		return feof( $this->handle );
	}

	/**
	 * Support for fseek().
	 *
	 * @param int $offset
	 *   The byte offset to got to.
	 * @param int $whence
	 *   SEEK_SET, SEEK_CUR, or SEEK_END.
	 *
	 * @return bool
	 *   TRUE on success.
	 *
	 * @see http://php.net/manual/streamwrapper.stream-seek.php
	 */
	public function stream_seek( $offset, $whence ) {
		// fseek returns 0 on success and -1 on a failure.
		// stream_seek   1 on success and  0 on a failure.
		return ! fseek( $this->handle, $offset, $whence );
	}

	/**
	 * Support for fflush().
	 *
	 * @return bool
	 *   TRUE if data was successfully stored (or there was no data to store).
	 *
	 * @see http://php.net/manual/streamwrapper.stream-flush.php
	 */
	public function stream_flush() {
		return fflush( $this->handle );
	}

	/**
	 * Support for ftell().
	 *
	 * @return bool
	 *   The current offset in bytes from the beginning of file.
	 *
	 * @see http://php.net/manual/streamwrapper.stream-tell.php
	 */
	public function stream_tell() {
		return ftell( $this->handle );
	}

	/**
	 * Support for fstat().
	 *
	 * @return bool
	 *   An array with file status, or FALSE in case of an error - see fstat()
	 *   for a description of this array.
	 *
	 * @see http://php.net/manual/streamwrapper.stream-stat.php
	 */
	public function stream_stat() {
		return fstat( $this->handle );
	}

	/**
	 * Support for fclose().
	 *
	 * @return bool
	 *   TRUE if stream was successfully closed.
	 *
	 * @see http://php.net/manual/streamwrapper.stream-close.php
	 */
	public function stream_close() {
		return fclose( $this->handle );
	}

	/**
	 * Gets the underlying stream resource for stream_select().
	 *
	 * @param int $cast_as
	 *   Can be STREAM_CAST_FOR_SELECT or STREAM_CAST_AS_STREAM.
	 *
	 * @return resource|false
	 *   The underlying stream resource or FALSE if stream_select() is not
	 *   supported.
	 *
	 * @see http://php.net/manual/streamwrapper.stream-cast.php
	 */
	public function stream_cast( $cast_as ) {
		return false;
	}

	/**
	 * Support for unlink().
	 *
	 * @param string $uri
	 *   A string containing the URI to the resource to delete.
	 *
	 * @return bool
	 *   TRUE if resource was successfully deleted.
	 *
	 * @see http://php.net/manual/streamwrapper.unlink.php
	 */
	public function unlink( $uri ) {
		$this->uri = $uri;
		return unlink( $this->getLocalPath() );
	}

	/**
	 * Support for rename().
	 *
	 * @param string $from_uri,
	 *   The URI to the file to rename.
	 * @param string $to_uri
	 *   The new URI for file.
	 *
	 * @return bool
	 *   TRUE if file was successfully renamed.
	 *
	 * @see http://php.net/manual/streamwrapper.rename.php
	 */
	public function rename( $from_uri, $to_uri ) {
		return rename( $this->getLocalPath( $from_uri ), $this->getLocalPath( $to_uri ) );
	}

	/**
	 * Support for mkdir().
	 *
	 * @param string $uri
	 *   A string containing the URI to the directory to create.
	 * @param int $mode
	 *   Permission flags - see mkdir().
	 * @param int $options
	 *   A bit mask of STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE.
	 *
	 * @return bool
	 *   TRUE if directory was successfully created.
	 *
	 * @see http://php.net/manual/streamwrapper.mkdir.php
	 */
	public function mkdir( $uri, $mode, $options ) {
		$this->uri = $uri;
		$recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);
		if ( $recursive ) {
			// $this->getLocalPath() fails if $uri has multiple levels of directories
			// that do not yet exist.
			$localpath = $this->getDirectoryPath() . '/' . $this->getTarget( $uri );
		} else {
			$localpath = $this->getLocalPath( $uri );
		}
		if ( $options & STREAM_REPORT_ERRORS ) {
			return mkdir( $localpath, $mode, $recursive );
		} else {
			return @mkdir( $localpath, $mode, $recursive );
		}
	}

	/**
	 * Support for rmdir().
	 *
	 * @param string $uri
	 *   A string containing the URI to the directory to delete.
	 * @param int $options
	 *   A bit mask of STREAM_REPORT_ERRORS.
	 *
	 * @return bool
	 *   TRUE if directory was successfully removed.
	 *
	 * @see http://php.net/manual/streamwrapper.rmdir.php
	 */
	public function rmdir( $uri, $options ) {
		$this->uri = $uri;
		if ( $options & STREAM_REPORT_ERRORS ) {
			return rmdir( $this->getLocalPath() );
		} else {
			return @rmdir( $this->getLocalPath() );
		}
	}

	/**
	 * Support for stat().
	 *
	 * @param string $uri
	 *   A string containing the URI to get information about.
	 * @param int $flags
	 *   A bit mask of STREAM_URL_STAT_LINK and STREAM_URL_STAT_QUIET.
	 *
	 * @return array
	 *   An array with file status, or FALSE in case of an error - see fstat()
	 *   for a description of this array.
	 *
	 * @see http://php.net/manual/streamwrapper.url-stat.php
	 */
	public function url_stat( $uri, $flags ) {
		$this->uri = $uri;
		$path = $this->getLocalPath();
		// Suppress warnings if requested or if the file or directory does not
		// exist. This is consistent with PHP's plain filesystem stream wrapper.
		if ( $flags & STREAM_URL_STAT_QUIET || ! file_exists( $path ) ) {
			return @stat( $path );
		} else {
			return stat( $path );
		}
	}

	/**
	 * Support for opendir().
	 *
	 * @param string $uri
	 *   A string containing the URI to the directory to open.
	 * @param int $options
	 *   Unknown (parameter is not documented in PHP Manual).
	 *
	 * @return bool
	 *   TRUE on success.
	 *
	 * @see http://php.net/manual/streamwrapper.dir-opendir.php
	 */
	public function dir_opendir( $uri, $options ) {
		$this->uri = $uri;
		$this->handle = opendir( $this->getLocalPath() );

		return (bool) $this->handle;
	}

	/**
	 * Support for readdir().
	 *
	 * @return string
	 *   The next filename, or FALSE if there are no more files in the directory.
	 *
	 * @see http://php.net/manual/streamwrapper.dir-readdir.php
	 */
	public function dir_readdir() {
		return readdir( $this->handle );
	}

	/**
	 * Support for rewinddir().
	 *
	 * @return bool
	 *   TRUE on success.
	 *
	 * @see http://php.net/manual/streamwrapper.dir-rewinddir.php
	 */
	public function dir_rewinddir() {
		rewinddir( $this->handle );
		// We do not really have a way to signal a failure as rewinddir() does not
		// have a return value and there is no way to read a directory handler
		// without advancing to the next file.
		return true;
	}

	/**
	 * Support for closedir().
	 *
	 * @return bool
	 *   TRUE on success.
	 *
	 * @see http://php.net/manual/streamwrapper.dir-closedir.php
	 */
	public function dir_closedir() {
		closedir( $this->handle );
		// We do not really have a way to signal a failure as closedir() does not
		// have a return value.
		return true;
	}
}
