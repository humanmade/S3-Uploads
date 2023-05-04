<?php

namespace S3_Uploads;

use Aws;
use Exception;
use WP_Error;

/**
 * @psalm-consistent-constructor
 */
class Plugin {

	/**
	 * The S3 bucket with path.
	 *
	 * @var string
	 */
	private $bucket;

	/**
	 * The URL that resolves to the S3 bucket.
	 *
	 * @var ?string
	 */
	private $bucket_url;

	/**
	 * AWS IAM access key used for S3 Access.
	 *
	 * @var ?string
	 */
	private $key;

	/**
	 * AWS IAM access key secret used for S3 Access.
	 *
	 * @var ?string
	 */
	private $secret;

	/**
	 * Original wp_upload_dir() before being replaced by S3 Uploads.
	 *
	 * @var ?array{path: string, basedir: string, baseurl: string, url: string}
	 */
	public $original_upload_dir;

	/**
	 * @var ?string
	 */
	private $region = null;

	/**
	 * @var ?Aws\S3\S3Client
	 */
	private $s3 = null;

	/**
	 * @var ?static
	 */
	private static $instance = null;

	/**
	 *
	 * @return static
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new static(
				S3_UPLOADS_BUCKET,
				defined( 'S3_UPLOADS_KEY' ) ? S3_UPLOADS_KEY : null,
				defined( 'S3_UPLOADS_SECRET' ) ? S3_UPLOADS_SECRET : null,
				defined( 'S3_UPLOADS_BUCKET_URL' ) ? S3_UPLOADS_BUCKET_URL : null,
				S3_UPLOADS_REGION
			);
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param string $bucket
	 * @param ?string $key
	 * @param ?string $secret
	 * @param ?string $bucket_url
	 * @param ?string $region
	 */
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

		add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );
		add_filter( 'wp_image_editors', [ $this, 'filter_editors' ], 9 );
		add_action( 'delete_attachment', [ $this, 'delete_attachment_files' ] );
		add_filter( 'wp_read_image_metadata', [ $this, 'wp_filter_read_image_metadata' ], 10, 2 );
		add_filter( 'wp_resource_hints', [ $this, 'wp_filter_resource_hints' ], 10, 2 );

		add_action( 'wp_handle_sideload_prefilter', [ $this, 'filter_sideload_move_temp_file_to_s3' ] );
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'set_filesize_in_attachment_meta' ], 10, 2 );

		add_action( 'wp_get_attachment_url', [ $this, 'add_s3_signed_params_to_attachment_url' ], 10, 2 );
		add_action( 'wp_get_attachment_image_src', [ $this, 'add_s3_signed_params_to_attachment_image_src' ], 10, 2 );
		add_action( 'wp_calculate_image_srcset', [ $this, 'add_s3_signed_params_to_attachment_image_srcset' ], 10, 5 );

		add_filter( 'wp_generate_attachment_metadata', [ $this, 'set_attachment_private_on_generate_attachment_metadata' ], 10, 2 );

		add_filter( 'pre_wp_unique_filename_file_list', [ $this, 'get_files_for_unique_filename_file_list' ], 10, 3 );
	}

	/**
	 * Tear down the hooks, url filtering etc for S3 Uploads
	 */
	public function tear_down() {

		stream_wrapper_unregister( 's3' );
		remove_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );
		remove_filter( 'wp_image_editors', [ $this, 'filter_editors' ], 9 );
		remove_filter( 'wp_handle_sideload_prefilter', [ $this, 'filter_sideload_move_temp_file_to_s3' ] );
		remove_filter( 'wp_generate_attachment_metadata', [ $this, 'set_filesize_in_attachment_meta' ] );

		remove_action( 'wp_get_attachment_url', [ $this, 'add_s3_signed_params_to_attachment_url' ] );
		remove_action( 'wp_get_attachment_image_src', [ $this, 'add_s3_signed_params_to_attachment_image_src' ] );
		remove_action( 'wp_calculate_image_srcset', [ $this, 'add_s3_signed_params_to_attachment_image_srcset' ] );

		remove_filter( 'wp_generate_attachment_metadata', [ $this, 'set_attachment_private_on_generate_attachment_metadata' ] );
	}

	/**
	 * Register the stream wrapper for s3
	 */
	public function register_stream_wrapper() {
		if ( defined( 'S3_UPLOADS_USE_LOCAL' ) && S3_UPLOADS_USE_LOCAL ) {
			stream_wrapper_register( 's3', Local_Stream_Wrapper::class, STREAM_IS_URL );
		} else {
			Stream_Wrapper::register( $this );
			$acl = defined( 'S3_UPLOADS_OBJECT_ACL' ) ? S3_UPLOADS_OBJECT_ACL : 'public-read';
			stream_context_set_option( stream_context_get_default(), 's3', 'ACL', $acl );
		}

		stream_context_set_option( stream_context_get_default(), 's3', 'seekable', true );
	}

	/**
	 * Get the s3:// path for the bucket.
	 */
	public function get_s3_path() : string {
		return 's3://' . $this->bucket;
	}

	/**
	 * Overwrite the default wp_upload_dir.
	 *
	 * @param array{path: string, basedir: string, baseurl: string, url: string} $dirs
	 * @return array{path: string, basedir: string, baseurl: string, url: string}
	 */
	public function filter_upload_dir( array $dirs ) : array {

		$this->original_upload_dir = $dirs;
		$s3_path = $this->get_s3_path();

		$dirs['path']    = str_replace( WP_CONTENT_DIR, $s3_path, $dirs['path'] );
		$dirs['basedir'] = str_replace( WP_CONTENT_DIR, $s3_path, $dirs['basedir'] );

		if ( ! defined( 'S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL' ) || ! S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL ) {

			if ( defined( 'S3_UPLOADS_USE_LOCAL' ) && S3_UPLOADS_USE_LOCAL ) {
				$dirs['url']     = str_replace( $s3_path, $dirs['baseurl'] . '/s3/' . $this->bucket, $dirs['path'] );
				$dirs['baseurl'] = str_replace( $s3_path, $dirs['baseurl'] . '/s3/' . $this->bucket, $dirs['basedir'] );

			} else {
				$dirs['url']     = str_replace( $s3_path, $this->get_s3_url(), $dirs['path'] );
				$dirs['baseurl'] = str_replace( $s3_path, $this->get_s3_url(), $dirs['basedir'] );
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
	 * @param int $post_id
	 */
	public function delete_attachment_files( int $post_id ) {
		$meta = wp_get_attachment_metadata( $post_id );
		$file = get_attached_file( $post_id );
		if ( ! $file ) {
			return;
		}

		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $sizeinfo ) {
				$intermediate_file = str_replace( basename( $file ), $sizeinfo['file'], $file );
				wp_delete_file( $intermediate_file );
			}
		}

		wp_delete_file( $file );
	}

	/**
	 * Get the S3 URL base for uploads.
	 *
	 * @return string
	 */
	public function get_s3_url() : string {
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
	public function get_s3_bucket() : string {
		return strtok( $this->bucket, '/' );
	}

	/**
	 * Get the region of the S3 bucket.
	 *
	 * @return string
	 */
	public function get_s3_bucket_region() : ?string {
		return $this->region;
	}

	/**
	 * Get the original upload directory before it was replaced by S3 uploads.
	 *
	 * @return array{path: string, basedir: string, baseurl: string, url: string}
	 */
	public function get_original_upload_dir() : array {

		if ( empty( $this->original_upload_dir ) ) {
			wp_upload_dir();
		}

		/**
		 * @var array{path: string, basedir: string, baseurl: string, url: string}
		 */
		$upload_dir = $this->original_upload_dir;
		return $upload_dir;
	}

	/**
	 * Reverse a file url in the uploads directory to the params needed for S3.
	 *
	 * @param string $url
	 * @return array{bucket: string, key: string, query: string|null}|null
	 */
	public function get_s3_location_for_url( string $url ) : ?array {
		$s3_url = 'https://' . $this->get_s3_bucket() . '.s3.amazonaws.com/';
		if ( strpos( $url, $s3_url ) === 0 ) {
			$parsed = wp_parse_url( $url );
			return [
				'bucket' => $this->get_s3_bucket(),
				'key'    => isset( $parsed['path'] ) ? ltrim( $parsed['path'], '/' ) : '',
				'query'  => $parsed['query'] ?? null,
			];
		}
		$upload_dir = wp_upload_dir();

		if ( strpos( $url, $upload_dir['baseurl'] ) === false ) {
			return null;
		}

		$path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
		$parsed = wp_parse_url( $path );
		if ( ! isset( $parsed['host'] ) || ! isset( $parsed['path'] ) ) {
			return null;
		}
		return [
			'bucket' => $parsed['host'],
			'key'    => ltrim( $parsed['path'], '/' ),
			'query'  => $parsed['query'] ?? null,
		];
	}

	/**
	 * Reverse a file path in the uploads directory to the params needed for S3.
	 *
	 * @param string $url
	 * @return array{key: string, bucket: string}
	 */
	public function get_s3_location_for_path( string $path ) : ?array {
		$parsed = wp_parse_url( $path );
		if ( ! isset( $parsed['path'] ) || ! isset( $parsed['host'] ) || ! isset( $parsed['scheme'] ) || $parsed['scheme'] !== 's3' ) {
			return null;
		}
		return [
			'bucket' => $parsed['host'],
			'key'    => ltrim( $parsed['path'], '/' ),
		];
	}

	/**
	 * @return Aws\S3\S3Client
	 */
	public function s3() : Aws\S3\S3Client {

		if ( ! empty( $this->s3 ) ) {
			return $this->s3;
		}

		$this->s3 = $this->get_aws_sdk()->createS3();
		return $this->s3;
	}

	/**
	 * Get the AWS Sdk.
	 *
	 * @return Aws\Sdk
	 */
	public function get_aws_sdk() : Aws\Sdk {
		/** @var null|Aws\Sdk */
		$sdk = apply_filters( 's3_uploads_aws_sdk', null, $this );
		if ( $sdk ) {
			return $sdk;
		}

		$params = [ 'version' => 'latest' ];

		if ( $this->key && $this->secret ) {
			$params['credentials']['key'] = $this->key;
			$params['credentials']['secret'] = $this->secret;
		}

		if ( $this->region ) {
			$params['signature'] = 'v4';
			$params['region'] = $this->region;
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

	public function filter_editors( array $editors ) : array {
		$position = array_search( 'WP_Image_Editor_Imagick', $editors );
		if ( $position !== false ) {
			unset( $editors[ $position ] );
		}

		array_unshift( $editors, __NAMESPACE__ . '\\Image_Editor_Imagick' );

		return $editors;
	}
	/**
	 * Copy the file from /tmp to an s3 dir so handle_sideload doesn't fail due to
	 * trying to do a rename() on the file cross streams. This is somewhat of a hack
	 * to work around the core issue https://core.trac.wordpress.org/ticket/29257
	 *
	 * @param array{tmp_name: string} $file File array
	 * @return array{tmp_name: string}
	 */
	public function filter_sideload_move_temp_file_to_s3( array $file ) {
		$upload_dir = wp_upload_dir();
		$new_path = $upload_dir['basedir'] . '/tmp/' . basename( $file['tmp_name'] );

		copy( $file['tmp_name'], $new_path );
		unlink( $file['tmp_name'] );
		$file['tmp_name'] = $new_path;

		return $file;
	}

	/**
	 * Store the attachment filesize in the attachment meta array.
	 *
	 * Getting the filesize of an image in S3 involves a remote HEAD request,
	 * which is a bit slower than a local filesystem operation would be. As a
	 * result, operations like `wp_prepare_attachments_for_js' take substantially
	 * longer to complete against s3 uploads than if they were performed with a
	 * local filesystem.i
	 *
	 * Saving the filesize in the attachment metadata when the image is
	 * uploaded allows core to skip this stat when retrieving and formatting it.
	 *
	 * @param array{file?: string} $metadata      Attachment metadata.
	 * @param int                  $attachment_id Attachment ID.
	 * @return array{file?: string, filesize?: int} Attachment metadata array, with "filesize" value added.
	 */
	function set_filesize_in_attachment_meta( array $metadata, int $attachment_id ) : array {
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return $metadata;
		}
		if ( ! isset( $metadata['filesize'] ) && file_exists( $file ) ) {
			$metadata['filesize'] = filesize( $file );
		}

		return $metadata;
	}

	/**
	 * Filters wp_read_image_metadata. exif_read_data() doesn't work on
	 * file streams so we need to make a temporary local copy to extract
	 * exif data from.
	 *
	 * @param array $meta
	 * @param string $file
	 * @return array|bool
	 */
	public function wp_filter_read_image_metadata( array $meta, string $file ) {
		remove_filter( 'wp_read_image_metadata', [ $this, 'wp_filter_read_image_metadata' ], 10 );
		$temp_file = $this->copy_image_from_s3( $file );
		$meta = wp_read_image_metadata( $temp_file );
		add_filter( 'wp_read_image_metadata', [ $this, 'wp_filter_read_image_metadata' ], 10, 2 );
		unlink( $temp_file );
		return $meta;
	}

	/**
	 * Add the DNS address for the S3 Bucket to list for DNS prefetch.
	 *
	 * @param array $hints
	 * @param string $relation_type
	 * @return array
	 */
	function wp_filter_resource_hints( array $hints, string $relation_type ) : array {
		if (
			( defined( 'S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL' ) && S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL ) ||
			( defined( 'S3_UPLOADS_USE_LOCAL' ) && S3_UPLOADS_USE_LOCAL )
		) {
			return $hints;
		}

		if ( 'dns-prefetch' === $relation_type ) {
			$hints[] = $this->get_s3_url();
		}

		return $hints;
	}

	/**
	 * Get a local copy of the file.
	 *
	 * @param string $file
	 * @return string
	 */
	public function copy_image_from_s3( string $file ) : string {
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
		/**
		 * Filters whether an attachment should be private.
		 *
		 * @param bool Whether the attachment is private.
		 * @param int  The attachment ID.
		 */
		$private = apply_filters( 's3_uploads_is_attachment_private', false, $attachment_id );
		return $private;
	}

	/**
	 * Update the ACL (Access Control List) for an attachments files.
	 *
	 * @param integer $attachment_id
	 * @param 'public-read'|'private' $acl public-read|private
	 * @return WP_Error|null
	 */
	public function set_attachment_files_acl( int $attachment_id, string $acl ) : ?WP_Error {
		$files = static::get_attachment_files( $attachment_id );
		$locations = array_map( [ $this, 'get_s3_location_for_path' ], $files );
		// Remove any null items in the array from get_s3_location_for_path().
		$locations = array_filter( $locations );
		$s3 = $this->s3();
		$commands = [];
		foreach ( $locations as $location ) {
			$commands[] = $s3->getCommand( 'putObjectAcl', [
				'Bucket' => $location['bucket'],
				'Key' => $location['key'],
				'ACL' => $acl,
			] );
		}

		try {
			Aws\CommandPool::batch( $s3, $commands );
		} catch ( Exception $e ) {
			return new WP_Error( $e->getCode(), $e->getMessage() );
		}

		return null;
	}

	/**
	 * Get all the files stored for a given attachment.
	 *
	 * @param integer $attachment_id
	 * @return list<string> Array of all full paths to the attachment's files.
	 */
	public static function get_attachment_files( int $attachment_id ) : array {
		$uploadpath = wp_get_upload_dir();
		/** @var string */
		$main_file = get_attached_file( $attachment_id );
		$files = [ $main_file ];

		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( isset( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size => $sizeinfo ) {
				$files[] = $uploadpath['basedir'] . $sizeinfo['file'];
			}
		}

		/** @var string|false */
		$original_image = get_post_meta( $attachment_id, 'original_image', true );
		if ( $original_image ) {
			$files[] = $uploadpath['basedir'] . $original_image;
		}

		/** @var array<string,array{file: string}> */
		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		if ( $backup_sizes ) {
			foreach ( $backup_sizes as $size => $sizeinfo ) {
				// Backup sizes only store the backup filename, which is relative to the
				// main attached file, unlike the metadata sizes array.
				$files[] = path_join( dirname( $main_file ), $sizeinfo['file'] );
			}
		}

		$files = apply_filters( 's3_uploads_get_attachment_files', $files, $attachment_id );

		return $files;
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
		if ( ! $path ) {
			return $url;
		}
		$cmd = $this->s3()->getCommand(
			'GetObject',
			[
				'Bucket' => $path['bucket'],
				'Key' => $path['key'],
			]
		);

		$presigned_url_expires = apply_filters( 's3_uploads_private_attachment_url_expiry', '+6 hours', $post_id );
		$query = $this->s3()->createPresignedRequest( $cmd, $presigned_url_expires )->getUri()->getQuery();

		// The URL could have query params on it already (such as being an already signed URL),
		// but query params will mean the S3 signed URL will become corrupt. So, we have to
		// remove all query params.
		$url = strtok( $url, '?' ) . '?' . $query;
		$url = apply_filters( 's3_uploads_presigned_url', $url, $post_id );

		return $url;
	}

	/**
	 * Add the S3 signed params to an image src array.
	 *
	 * @param array{0: string, 1: int, 2: int}|false $image
	 * @param integer|"" $post_id The post id, due to WordPress hook, this can be "", so can't just hint as int.
	 * @return array{0: string, 1: int, 2: int}|false
	 */
	public function add_s3_signed_params_to_attachment_image_src( $image, $post_id ) {
		if ( ! $image || ! $post_id ) {
			return $image;
		}

		$image[0] = $this->add_s3_signed_params_to_attachment_url( $image[0], $post_id );
		return $image;
	}

	/**
	 * Add the S3 signed params to the image srcset (response image) sizes.
	 *
	 * @param array{url: string, descriptor: string, value: int}[] $sources
	 * @param array $sizes
	 * @param string $src
	 * @param array $meta
	 * @param integer $post_id
	 * @return array{url: string, descriptor: string, value: int}[]
	 */
	public function add_s3_signed_params_to_attachment_image_srcset( array $sources, array $sizes, string $src, array $meta, int $post_id ) : array {
		foreach ( $sources as &$source ) {
			$source['url'] = $this->add_s3_signed_params_to_attachment_url( $source['url'], $post_id );
		}
		return $sources;
	}

	/**
	 * Whenever attachment metadata is generated, set the attachment files to private if it's a private attachment.
	 *
	 * @param array $metadata    The attachment metadata.
	 * @param int $attachment_id The attachment ID
	 * @return array
	 */
	public function set_attachment_private_on_generate_attachment_metadata( array $metadata, int $attachment_id ) : array {
		if ( $this->is_private_attachment( $attachment_id ) ) {
			$this->set_attachment_files_acl( $attachment_id, 'private' );
		}

		return $metadata;
	}

	/**
	 * Override the files used for wp_unique_filename() comparisons
	 *
	 * @param array|null $files
	 * @param string $dir
	 * @return array
	 */
	public function get_files_for_unique_filename_file_list( ?array $files, string $dir, string $filename ) : array {
		$name = pathinfo( $filename, PATHINFO_FILENAME );
		// The s3:// streamwrapper support listing by partial prefixes with wildcards.
		// For example, scandir( s3://bucket/2019/06/my-image* )
		return (array) scandir( trailingslashit( $dir ) . $name . '*' );
	}
}
