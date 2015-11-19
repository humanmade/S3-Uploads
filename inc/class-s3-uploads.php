<?php

/**
 * Main plugin class. Takes care of the setup needed to make WordPress interact with S3.
 */
class S3_Uploads {
	/**
	 * Single instance of the class.
	 *
	 * @var S3_Uploads
	 */
	private static $instance;

	/**
	 * Single instance of the AWS SDK Client to interact with S3.
	 *
	 * @var Aws\S3\S3Client
	 */
	private $s3;

	/**
	 * Name of the S3 bucket to connect to.
	 *
	 * @var string
	 */
	private $bucket;

	/**
	 * AWS Region to connect to.
	 *
	 * @var string
	 */
	private $region;

	/**
	 * Access Key ID from the AWS user credentials.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Secret Access Key from the AWS user credentials.
	 *
	 * @var string
	 */
	private $secret;

	/**
	 * URL of the S3 Bucket.
	 *
	 * @var string
	 */
	private $bucket_url;

	/**
	 * Array of the original (i.e. before the plugin's filtering) upload directory data with keys of 'path', 'url',
	 * 'subdir, 'basedir', and 'error'.
	 *
	 * @var array
	 */
	private $original_upload_dir;

	/**
	 * Return the single instance of S3_Uploads.
	 *
	 * @return S3_Uploads
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new S3_Uploads(
				S3_UPLOADS_BUCKET,
				S3_UPLOADS_REGION,
				S3_UPLOADS_KEY,
				S3_UPLOADS_SECRET,
				defined( 'S3_UPLOADS_BUCKET_URL' ) ? S3_UPLOADS_BUCKET_URL : null
			);
		}

		return self::$instance;
	}

	/**
	 * Constructor. Stores the passed arguments as member variables.
	 *
	 * @param      $bucket     string Name of the S3 bucket to connect to.
	 * @param      $region     string AWS Region to connect to.
	 * @param      $key        string Access Key ID from the AWS user credentials.
	 * @param      $secret     string Secret Access Key from the AWS user credentials.
	 * @param null $bucket_url string Optional: URL of the S3 Bucket.
	 */
	public function __construct( $bucket, $region, $key, $secret, $bucket_url = null ) {
		$this->bucket     = $bucket;
		$this->region     = $region;
		$this->key        = $key;
		$this->secret     = $secret;
		$this->bucket_url = $bucket_url;
	}

	/**
	 * Register the stream wrapper, and hook up the necessary filters.
	 */
	public function setup() {
		$this->register_stream_wrapper();

		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		add_filter( 'wp_image_editors', array( $this, 'filter_editors' ), 9 );
		add_action( 'wp_handle_sideload_prefilter', array( $this, 'filter_sideload_move_temp_file_to_s3' ) );

		remove_filter( 'admin_notices', 'wpthumb_errors' );
	}

	/**
	 * Deregister the stream wrapper, and unhook all filters.
	 */
	public function tear_down() {
		stream_wrapper_unregister( 's3' );

		remove_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		remove_filter( 'wp_image_editors', array( $this, 'filter_editors' ), 9 );
		remove_action( 'wp_handle_sideload_prefilter', array( $this, 'filter_sideload_move_temp_file_to_s3' ) );
	}

	/**
	 * Register the stream wrapper for S3.
	 *
	 * Depending on the plugin's configuration, the stream wrapper is either based on the AWS SDK S3 wrapper, or a
	 * wrapper simulating S3 interactions via the local file system.
	 */
	public function register_stream_wrapper() {
		if ( defined( 'S3_UPLOADS_USE_LOCAL' ) && S3_UPLOADS_USE_LOCAL ) {
			stream_wrapper_register( 's3', 'S3_Uploads_Local_Stream_Wrapper', STREAM_IS_URL );
		} else {
			S3_Uploads_Stream_Wrapper::register( $this->s3() );
			stream_context_set_option( stream_context_get_default(), 's3', 'ACL', 'public-read' );
		}

		stream_context_set_option( stream_context_get_default(), 's3', 'seekable', true );
	}

	/**
	 * Filter the upload directory data used by WordPress to point to S3 instead of the local file system.
	 *
	 * @param $dirs array Array of the original upload directory data.
	 *
	 * @return array Array with the updated data to point to S3.
	 */
	public function filter_upload_dir( $dirs ) {
		$this->original_upload_dir = $dirs;

		$dirs['path']    = str_replace( WP_CONTENT_DIR, 's3://' . $this->bucket, $dirs['path'] );
		$dirs['basedir'] = str_replace( WP_CONTENT_DIR, 's3://' . $this->bucket, $dirs['basedir'] );

		if ( ! defined( 'S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL' ) || ! S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL ) {
			if ( defined( 'S3_UPLOADS_USE_LOCAL' ) && S3_UPLOADS_USE_LOCAL ) {
				$dirs['url']     = str_replace( 's3://' . $this->bucket, $dirs['baseurl'] . '/s3/' . $this->bucket, $dirs['path'] );
				$dirs['baseurl'] = str_replace( 's3://' . $this->bucket, $dirs['baseurl'] . '/s3/' . $this->bucket, $dirs['basedir'] );

			} else {
				$dirs['url']     = str_replace( 's3://' . $this->bucket, $this->get_bucket_url(), $dirs['path'] );
				$dirs['baseurl'] = str_replace( 's3://' . $this->bucket, $this->get_bucket_url(), $dirs['basedir'] );
			}
		}

		return $dirs;
	}

	/**
	 * Replace the Core `WP_Image_Editor_Imagick` class with the `S3_Uploads_Image_Editor_Imagick` child class to
	 * handle saving edited images to S3.
	 *
	 * @param array $editors List of available WordPress Core image editor classes.
	 *
	 * @return array Updated list of image editor classes.
	 */
	public function filter_editors( $editors ) {
		if ( ( $position = array_search( 'WP_Image_Editor_Imagick', $editors ) ) !== false ) {
			unset( $editors[ $position ] );
		}

		array_unshift( $editors, 'S3_Uploads_Image_Editor_Imagick' );

		return $editors;
	}

	/**
	 * Copy the file from /tmp to an s3 dir so handle_sideload doesn't fail due to trying to do a rename() on the file
	 * cross streams. This is somewhat of a hack to work around the WordPress Core issue #29257.
	 * See https://core.trac.wordpress.org/ticket/29257
	 *
	 * See `_wp_handle_upload()` for the details concerning the `wp_handle_sideload_prefilter` action.
	 *
	 * @param $file array An array of data for a single file.
	 *
	 * @return array Updated array of data for a single file.
	 */
	public function filter_sideload_move_temp_file_to_s3( array $file ) {
		$upload_dir = wp_upload_dir();
		$new_path   = $upload_dir['basedir'] . '/tmp/' . basename( $file['tmp_name'] );

		copy( $file['tmp_name'], $new_path );
		unlink( $file['tmp_name'] );
		$file['tmp_name'] = $new_path;

		return $file;
	}

	/**
	 * Return the URL of the S3 Bucket.
	 *
	 * When the URL is not defined explicitly in the settings, a standard URL is generated from the bucket name.
	 *
	 * @return string URL of the S3 Bucket.
	 */
	public function get_bucket_url() {
		if ( $this->bucket_url ) {
			return $this->bucket_url;
		}

		$bucket = strtok( $this->bucket, '/' );
		$path   = substr( $this->bucket, strlen( $bucket ) );

		return apply_filters( 's3_uploads_bucket_url', 'https://' . $bucket . '.s3.amazonaws.com' . $path );
	}

	/**
	 * Return the data of the original upload directory.
	 *
	 * @return array Array of the original upload directory data.
	 */
	public function get_original_upload_dir() {
		if ( empty( $this->original_upload_dir ) ) {
			wp_upload_dir();
		}

		return $this->original_upload_dir;
	}

	/**
	 * Return the unique instance of the AWS SDK S3 Client.
	 *
	 * @return Aws\S3\S3Client Unique instance of the AWS SDK S3 Client.
	 */
	public function s3() {
		if ( ! empty( $this->s3 ) ) {
			return $this->s3;
		}

		$params = array();

		$params['credentials']['key']    = $this->key;
		$params['credentials']['secret'] = $this->secret;
		$params['version']               = '2006-03-01';
		$params['signature']             = 'v4';
		$params['region']                = $this->region;

		if ( defined( 'WP_PROXY_HOST' ) && defined( 'WP_PROXY_PORT' ) ) {
			$proxy_auth    = '';
			$proxy_address = WP_PROXY_HOST . ':' . WP_PROXY_PORT;

			if ( defined( 'WP_PROXY_USERNAME' ) && defined( 'WP_PROXY_PASSWORD' ) ) {
				$proxy_auth = WP_PROXY_USERNAME . ':' . WP_PROXY_PASSWORD . '@';
			}

			$params['request.options']['proxy'] = $proxy_auth . $proxy_address;
		}

		$params = apply_filters( 's3_uploads_s3_client_params', $params );

		$this->s3 = new Aws\S3\S3Client( $params );

		return $this->s3;
	}
}
