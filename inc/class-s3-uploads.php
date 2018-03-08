<?php

class S3_Uploads {

	private static $instance;
	private        $bucket;
	private        $bucket_url;
	private        $key;
	private        $secret;

	public $original_upload_dir;
	public $original_file;

	/**
	 *
	 * @return S3_Uploads
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new S3_Uploads(
				S3_UPLOADS_BUCKET,
				defined( 'S3_UPLOADS_KEY' ) ? S3_UPLOADS_KEY : null,
				defined( 'S3_UPLOADS_SECRET' ) ? S3_UPLOADS_SECRET : null,
				defined( 'S3_UPLOADS_BUCKET_URL' ) ? S3_UPLOADS_BUCKET_URL : null,
				S3_UPLOADS_REGION
			);
		}

		return self::$instance;
	}

	public function __construct( $bucket, $key, $secret, $bucket_url = null, $region = null ) {

		$this->bucket     = $bucket;
		$this->key        = $key;
		$this->secret     = $secret;
		$this->bucket_url = $bucket_url;
		$this->region     = $region;
	}

	/**
	 * Setup the hooks, urls filtering etc for S3 Uploads
	 */
	public function setup() {
		$this->register_stream_wrapper();

		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		add_filter( 'wp_image_editors', array( $this, 'filter_editors' ), 9 );
		add_action( 'delete_attachment', array( $this, 'set_original_file' ) );
		add_filter( 'wp_delete_file', array( $this, 'wp_filter_delete_file' ) );
		add_filter( 'wp_read_image_metadata', array( $this, 'wp_filter_read_image_metadata' ), 10, 2 );
		add_filter( 'wp_resource_hints', array( $this, 'wp_filter_resource_hints' ), 10, 2 );
		remove_filter( 'admin_notices', 'wpthumb_errors' );

		add_action( 'wp_handle_sideload_prefilter', array( $this, 'filter_sideload_move_temp_file_to_s3' ) );
	}

	/**
	 * Tear down the hooks, url filtering etc for S3 Uploads
	 */
	public function tear_down() {

		stream_wrapper_unregister( 's3' );
		remove_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		remove_filter( 'wp_image_editors', array( $this, 'filter_editors' ), 9 );
		remove_filter( 'wp_handle_sideload_prefilter', array( $this, 'filter_sideload_move_temp_file_to_s3' ) );
		remove_filter( 'wp_delete_file', array( $this, 'wp_filter_delete_file' ) );
	}

	/**
	 * Register the stream wrapper for s3
	 */
	public function register_stream_wrapper() {
		if ( defined( 'S3_UPLOADS_USE_LOCAL' ) && S3_UPLOADS_USE_LOCAL ) {
			stream_wrapper_register( 's3', 'S3_Uploads_Local_Stream_Wrapper', STREAM_IS_URL );
		} else {
			S3_Uploads_Stream_Wrapper::register( $this->s3() );
			$objectAcl = defined( 'S3_UPLOADS_OBJECT_ACL' ) ? S3_UPLOADS_OBJECT_ACL : 'public-read';
			stream_context_set_option( stream_context_get_default(), 's3', 'ACL', $objectAcl );
		}

		stream_context_set_option( stream_context_get_default(), 's3', 'seekable', true );
	}

	public function filter_upload_dir( $dirs ) {

		$this->original_upload_dir = $dirs;

		$dirs['path']    = str_replace( WP_CONTENT_DIR, 's3://' . $this->bucket, $dirs['path'] );
		$dirs['basedir'] = str_replace( WP_CONTENT_DIR, 's3://' . $this->bucket, $dirs['basedir'] );

		if ( ! defined( 'S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL' ) || ! S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL ) {

			if ( defined( 'S3_UPLOADS_USE_LOCAL' ) && S3_UPLOADS_USE_LOCAL ) {
				$dirs['url']     = str_replace( 's3://' . $this->bucket, $dirs['baseurl'] . '/s3/' . $this->bucket, $dirs['path'] );
				$dirs['baseurl'] = str_replace( 's3://' . $this->bucket, $dirs['baseurl'] . '/s3/' . $this->bucket, $dirs['basedir'] );

			} else {
				$dirs['url']     = str_replace( 's3://' . $this->bucket, $this->get_s3_url(), $dirs['path'] );
				$dirs['baseurl'] = str_replace( 's3://' . $this->bucket, $this->get_s3_url(), $dirs['basedir'] );
			}
		}

		return $dirs;
	}

	/**
	 * Capture the full path to the original file being deleted. This
	 * is used when determining whether an absolute or relative path
	 * should be used when deleting the file.
	 *
	 * @param $post_id
	 */
	public function set_original_file( $post_id ) {
		$this->original_file = get_attached_file( $post_id );
	}

	/**
	 * When WordPress removes files, it's expecting to do so on
	 * absolute file paths, as such it breaks when using uris for
	 * file paths (such as s3://...). We have to filter the file_path
	 * to only return the relative section, to play nice with WordPress
	 * handling.
	 *
	 * @param  string $file_path
	 * @return string
	 */
	public function wp_filter_delete_file( $file_path ) {
		$dir = wp_upload_dir();

		// When `wp_delete_file()` is called directly, it expects an absolute path.
		if ( ! $this->original_file || $file_path === $this->original_file ) {
			return $file_path;
		}

		return str_replace( trailingslashit( $dir['basedir'] ), '', $file_path );
	}

	public function get_s3_url() {
		if ( $this->bucket_url ) {
			return $this->bucket_url;
		}

		$bucket = strtok( $this->bucket, '/' );
		$path   = substr( $this->bucket, strlen( $bucket ) );

		return apply_filters( 's3_uploads_bucket_url', 'https://' . $bucket . '.s3.amazonaws.com' . $path );
	}

	/**
	 * Get the S3 bucket name
	 *
	 * @return string
	 */
	public function get_s3_bucket() {
		return $bucket = strtok( $this->bucket, '/' );
	}

	public function get_s3_bucket_region() {
		return $this->region;
	}

	public function get_original_upload_dir() {

		if ( empty( $this->original_upload_dir ) ) {
			wp_upload_dir();
		}

		return $this->original_upload_dir;
	}

	/**
	 * @return Aws\S3\S3Client
	 */
	public function s3() {

		if ( ! empty( $this->s3 ) ) {
			return $this->s3;
		}

		$params = array( 'version' => 'latest' );

		if ( $this->key && $this->secret ) {
			$params['credentials']['key']    = $this->key;
			$params['credentials']['secret'] = $this->secret;
		}

		if ( $this->region ) {
			$params['signature'] = 'v4';
			$params['region']    = $this->region;
		}

		if ( defined( 'WP_PROXY_HOST' ) && defined( 'WP_PROXY_PORT' ) ) {
			$proxy_auth    = '';
			$proxy_address = WP_PROXY_HOST . ':' . WP_PROXY_PORT;

			if ( defined( 'WP_PROXY_USERNAME' ) && defined( 'WP_PROXY_PASSWORD' ) ) {
				$proxy_auth = WP_PROXY_USERNAME . ':' . WP_PROXY_PASSWORD . '@';
			}

			$params['request.options']['proxy'] = $proxy_auth . $proxy_address;
		}

		$params   = apply_filters( 's3_uploads_s3_client_params', $params );
		$this->s3 = Aws\S3\S3Client::factory( $params );

		return $this->s3;
	}

	public function filter_editors( $editors ) {

		if ( ( $position = array_search( 'WP_Image_Editor_Imagick', $editors ) ) !== false ) {
			unset( $editors[ $position ] );
		}

		array_unshift( $editors, 'S3_Uploads_Image_Editor_Imagick' );

		return $editors;
	}

	/**
	 * Copy the file from /tmp to an s3 dir so handle_sideload doesn't fail due to
	 * trying to do a rename() on the file cross streams. This is somewhat of a hack
	 * to work around the core issue https://core.trac.wordpress.org/ticket/29257
	 *
	 * @param array File array
	 * @return array
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
	 * Filters wp_read_image_metadata. exif_read_data() doesn't work on
	 * file streams so we need to make a temporary local copy to extract
	 * exif data from.
	 *
	 * @param array  $meta
	 * @param string $file
	 * @return array|bool
	 */
	public function wp_filter_read_image_metadata( $meta, $file ) {
		remove_filter( 'wp_read_image_metadata', array( $this, 'wp_filter_read_image_metadata' ), 10 );
		$temp_file = $this->copy_image_from_s3( $file );
		$meta      = wp_read_image_metadata( $temp_file );
		add_filter( 'wp_read_image_metadata', array( $this, 'wp_filter_read_image_metadata' ), 10, 2 );
		unlink( $temp_file );
		return $meta;
	}

	/**
	 * Add the DNS address for the S3 Bucket to list for DNS prefetch.
	 *
	 * @param $hints
	 * @param $relation_type
	 * @return array
	 */
	function wp_filter_resource_hints( $hints, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$hints[] = $this->get_s3_url();
		}

		return $hints;
	}

	/**
	 * Get a local copy of the file.
	 *
	 * @param  string $file
	 * @return string
	 */
	public function copy_image_from_s3( $file ) {
		$temp_filename = wp_tempnam( $file );
		copy( $file, $temp_filename );
		return $temp_filename;
	}
}
