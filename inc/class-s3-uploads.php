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

	public function __construct( string $bucket, string $key, string $secret, string $bucket_url = null, string $region = null ) {

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
		add_action( 'delete_attachment', array( $this, 'delete_attachment_files' ) );
		add_filter( 'wp_read_image_metadata', array( $this, 'wp_filter_read_image_metadata' ), 10, 2 );
		add_filter( 'wp_resource_hints', array( $this, 'wp_filter_resource_hints' ), 10, 2 );
		remove_filter( 'admin_notices', 'wpthumb_errors' );

		add_action( 'wp_handle_sideload_prefilter', array( $this, 'filter_sideload_move_temp_file_to_s3' ) );

		add_action( 'wp_get_attachment_url', array( $this, 'add_s3_signed_params_to_attachment_url' ), 10, 2 );
		add_action( 'wp_get_attachment_image_src', array( $this, 'add_s3_signed_params_to_attachment_image_src' ), 10, 2 );
		add_action( 'wp_calculate_image_srcset', array( $this, 'add_s3_signed_params_to_attachment_image_srcset' ), 10, 5 );
	}

	/**
	 * Tear down the hooks, url filtering etc for S3 Uploads
	 */
	public function tear_down() {

		stream_wrapper_unregister( 's3' );
		remove_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		remove_filter( 'wp_image_editors', array( $this, 'filter_editors' ), 9 );
		remove_filter( 'wp_handle_sideload_prefilter', array( $this, 'filter_sideload_move_temp_file_to_s3' ) );

		remove_action( 'wp_get_attachment_url', array( $this, 'add_s3_signed_params_to_attachment_url' ), 10, 2 );
		remove_action( 'wp_get_attachment_image_src', array( $this, 'add_s3_signed_params_to_attachment_image_src' ), 10, 2 );
		remove_action( 'wp_calculate_image_srcset', array( $this, 'add_s3_signed_params_to_attachment_image_srcset' ), 10, 5 );
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
	 * Delete all attachment files from S3 when an attachment is deleted.
	 *
	 * WordPress Core's handling of deleting files for attachments via
	 * wp_delete_attachment_files is not compatible with remote streams, as
	 * it makes many assumptions about local file paths. The hooks also do
	 * not exist to be able to modify their behavior. As such, we just clean
	 * up the s3 files when an attachment is removed, and leave WordPress to try
	 * a failed attempt at mangling the s3:// urls.
	 *
	 * @param $post_id
	 */
	public function delete_attachment_files( $post_id ) {
		$meta = wp_get_attachment_metadata( $post_id );
		$file = get_attached_file( $post_id );

		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $sizeinfo ) {
				$intermediate_file = str_replace( basename( $file ), $sizeinfo['file'], $file );
				wp_delete_file( $intermediate_file );
			}
		}

		wp_delete_file( $file );
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
	 * Reverse a file url in the uploads directory to the params needed for S3.
	 *
	 * @param string $url
	 * @return array
	 */
	public function get_s3_location_for_url( string $url ) : array {
		$s3_url = 'https://' . $this->get_s3_bucket() . '.s3.amazonaws.com/';
		if ( strpos( $url, $s3_url ) === 0 ) {
			$parsed = parse_url( $url );
			return [
				'bucket' => $this->get_s3_bucket(),
				'key'    => ltrim( $parsed['path'], '/' ),
				'query'  => $parsed['query'] ?? null,
			];
		}

		$upload_dir = wp_upload_dir();
		$path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
		$parsed = parse_url( $path );
		return [
			'bucket' => $parsed['host'],
			'key'    => ltrim( $parsed['path'], '/' ),
			'query'  => $parsed['query'] ?? null,
		];
	}

	/**
	 * @return Aws\S3\S3Client
	 */
	public function s3() {

		if ( ! empty( $this->s3 ) ) {
			return $this->s3;
		}

		$this->s3 = $this->get_aws_sdk()->createS3();
		return $this->s3;
	}

	/**
	 * Get the AWS Sdk.
	 *
	 * @return AWS\Sdk
	 */
	public function get_aws_sdk() : AWS\Sdk {
		$sdk = apply_filters( 's3_uploads_aws_sdk', null, $this );
		if ( $sdk ) {
			return $sdk;
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

		$params = apply_filters( 's3_uploads_s3_client_params', $params );

		$sdk = new Aws\Sdk( $params );
		return $sdk;
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
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}
		$temp_filename = wp_tempnam( $file );
		copy( $file, $temp_filename );
		return $temp_filename;
	}

	/**
	 * Check if the attachment is private.
	 *
	 * @param integer $attachment_id
	 * @return boolean
	 */
	public function is_private_attachment( int $attachment_id ) : bool {
		return apply_filters( 's3_uploads_is_attachment_private', true, $attachment_id );
	}

	/**
	 * Add the S3 signed params onto an image for for a given attachment.
	 *
	 * This function determines whether the attachment needs a signed URL, so is safe to
	 * pass any URL.
	 *
	 * @param string $url
	 * @param integer $post_id
	 * @return string
	 */
	public function add_s3_signed_params_to_attachment_url( string $url, int $post_id ) : string {
		if ( ! $this->is_private_attachment( $post_id ) ) {
			return $url;
		}
		$path = $this->get_s3_location_for_url( $url );
		$cmd = $this->s3()->getCommand('GetObject', [
			'Bucket' => $path['bucket'],
			'Key' => $path['key'],
		]);

		$url = (string) $this->s3()->createPresignedRequest( $cmd, '+10 minutes' )->getUri();
		if ( $path['query'] ) {
			$url .= '&' . $path['query'];
		}

		return $url;
	}

	/**
	 * Add the S3 signed params to an image src array.
	 *
	 * @param array $image
	 * @param integer $post_id
	 * @return array
	 */
	public function add_s3_signed_params_to_attachment_image_src( array $image, int $post_id ) : array {
		$image[0] = $this->add_s3_signed_params_to_attachment_url( $image[0], $post_id );
		return $image;
	}

	/**
	 * Add the S3 signed params to the image srcset (response image) sizes.
	 *
	 * @param array $sources
	 * @param array $sizes
	 * @param string $src
	 * @param array $meta
	 * @param integer $post_id
	 * @return array
	 */
	public function add_s3_signed_params_to_attachment_image_srcset( array $sources, array $sizes, string $src, array $meta, int $post_id ) : array {
		foreach ( $sources as &$source ) {
			$source['url'] = $this->add_s3_signed_params_to_attachment_url( $source['url'], $post_id );
		}
		return $sources;
	}
}
