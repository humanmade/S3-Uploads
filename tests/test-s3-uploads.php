<?php

class Test_S3_Uploads extends WP_UnitTestCase {

	protected $s3 = null;

	public function setUp(): void {
		parent::setUp();

		// start the tests with nothing added
		S3_Uploads\Plugin::get_instance()->tear_down();
	}

	public function tearDown(): void {
		// reenable for other tests
		S3_Uploads\Plugin::get_instance()->setup();
		parent::tearDown();
	}

	/**
	 * Test s3 uploads sets up all the necessary hooks
	 */
	public function test_setup() {

		S3_Uploads\Plugin::get_instance()->setup();

		$this->assertEquals( 10, has_action( 'upload_dir', array( S3_Uploads\Plugin::get_instance(), 'filter_upload_dir' ) ) );
		$this->assertEquals( 9, has_action( 'wp_image_editors', array( S3_Uploads\Plugin::get_instance(), 'filter_editors' ) ) );
		$this->assertEquals( 10, has_action( 'wp_handle_sideload_prefilter', array( S3_Uploads\Plugin::get_instance(), 'filter_sideload_move_temp_file_to_s3' ) ) );

		$this->assertTrue( in_array( 's3', stream_get_wrappers() ) );
		S3_Uploads\Plugin::get_instance()->tear_down();
	}

	/**
	 * Test s3 uploads sets up all the necessary hooks
	 */
	public function test_tear_down() {

		S3_Uploads\Plugin::get_instance()->setup();
		S3_Uploads\Plugin::get_instance()->tear_down();

		$this->assertFalse( has_action( 'upload_dir', array( S3_Uploads\Plugin::get_instance(), 'filter_upload_dir' ) ) );
		$this->assertFalse( has_action( 'wp_image_editors', array( S3_Uploads\Plugin::get_instance(), 'filter_editors' ) ) );
		$this->assertFalse( has_action( 'wp_handle_sideload_prefilter', array( S3_Uploads\Plugin::get_instance(), 'filter_sideload_move_temp_file_to_s3' ) ) );

		$this->assertFalse( in_array( 's3', stream_get_wrappers() ) );
	}

	public function test_s3_uploads_enabled() {

		$this->assertTrue( S3_Uploads\enabled() );

		update_option( 's3_uploads_enabled', 'enabled' );
		$this->assertTrue( S3_Uploads\enabled() );

		delete_option( 's3_uploads_enabled' );
		define( 'S3_UPLOADS_AUTOENABLE', false );

		$this->assertFalse( S3_Uploads\enabled() );

		update_option( 's3_uploads_enabled', 'enabled' );
		$this->assertTrue( S3_Uploads\enabled() );
	}

	public function test_get_s3_client() {

		$s3 = S3_Uploads\Plugin::get_instance()->s3();

		$this->assertInstanceOf( 'Aws\\S3\\S3Client', $s3 );
	}

	public function test_generate_attachment_metadata() {
		S3_Uploads\Plugin::get_instance()->setup();
		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', $upload_dir['path'] . '/sunflower.jpg' );
		$test_file = $upload_dir['path'] . '/sunflower.jpg';
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );

		$meta_data = wp_generate_attachment_metadata( $attachment_id, $test_file );

		$this->assertEquals( array(
			'file'      => 'sunflower-150x150.jpg',
			'width'     => 150,
			'height'    => 150,
			'mime-type' => 'image/jpeg',
		), $meta_data['sizes']['thumbnail'] );

		$this->assertTrue( 'X-T10' === $meta_data['image_meta']['camera'] );
		$this->assertTrue( '500' === $meta_data['image_meta']['iso'] );

		$wp_upload_dir = wp_upload_dir();
		$this->assertTrue( file_exists( $wp_upload_dir['path'] . '/sunflower-150x150.jpg' ) );
	}

	public function test_generate_attachment_metadata_for_mp4() {
		S3_Uploads\Plugin::get_instance()->setup();
		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . '/data/video.m4v', $upload_dir['path'] . '/video.m4v' );
		$test_file = $upload_dir['path'] . '/video.m4v';
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			'post_mime_type' => 'video/mp4',
			'post_excerpt'   => 'A sample caption',
		) );

		$meta_data = wp_generate_attachment_metadata( $attachment_id, $test_file );
		// Video mimetype can vary depending on platform configuration
		$this->assertTrue( in_array( $meta_data['mime_type'], [ 'video/quicktime', 'video/mp4' ], true ) );
		$this->assertEquals( 320, $meta_data['width'] );
		$this->assertEquals( 240, $meta_data['height'] );
	}

	public function test_image_sizes_are_deleted_on_attachment_delete() {
		S3_Uploads\Plugin::get_instance()->setup();
		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', $upload_dir['path'] . '/sunflower.jpg' );
		$test_file = $upload_dir['path'] . '/sunflower.jpg';
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );

		$meta_data = wp_generate_attachment_metadata( $attachment_id, $test_file );
		wp_update_attachment_metadata( $attachment_id, $meta_data );
		foreach ( $meta_data['sizes'] as $size ) {
			$this->assertTrue( file_exists( $upload_dir['path'] . '/' . $size['file'] ) );
		}

		wp_delete_attachment( $attachment_id, true );
		foreach ( $meta_data['sizes'] as $size ) {
			$this->assertFalse( file_exists( $upload_dir['path'] . '/' . $size['file'] ), sprintf( 'File %s was not deleted.', $upload_dir['path'] . '/' . $size['file'] ) );
		}
	}

	public function test_generate_attachment_metadata_for_pdf() {
		S3_Uploads\Plugin::get_instance()->setup();
		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . '/data/gdpr.pdf', $upload_dir['path'] . '/gdpr.pdf' );
		$test_file = $upload_dir['path'] . '/gdpr.pdf';
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			'post_mime_type' => 'application/pdf',
			'post_excerpt'   => 'A sample caption',
		) );

		$meta_data = wp_generate_attachment_metadata( $attachment_id, $test_file );

		$this->assertEquals( array(
			'file'      => 'gdpr-pdf-106x150.jpg',
			'width'     => 106,
			'height'    => 150,
			'mime-type' => 'image/jpeg',
		), $meta_data['sizes']['thumbnail'] );


		$wp_upload_dir = wp_upload_dir();
		$this->assertTrue( file_exists( $wp_upload_dir['path'] . '/gdpr-pdf-106x150.jpg' ) );
	}

	function test_get_s3_bucket_location() {

		$uploads = new S3_Uploads\Plugin( 'hmn-uploads', S3_UPLOADS_KEY, S3_UPLOADS_SECRET, null, S3_UPLOADS_REGION );

		$region = $uploads->get_s3_bucket_region();
		$this->assertEquals( 'us-east-1', $region );
	}

	function test_get_s3_bucket() {
		$uploads = new S3_Uploads\Plugin( 'hmn-uploads/something', S3_UPLOADS_KEY, S3_UPLOADS_SECRET, null, S3_UPLOADS_REGION );

		$this->assertEquals( 'hmn-uploads', $uploads->get_s3_bucket() );
	}

	function test_wp_unique_filename() {
		S3_Uploads\Plugin::get_instance()->setup();
		$upload_dir = wp_upload_dir();

		file_put_contents( $upload_dir['path'] . '/my-file-scaled.jpg', '' );
		$filename = wp_unique_filename( $upload_dir['path'], 'my-file.jpg' );
		$this->assertEquals( 'my-file-1.jpg', $filename );

		$filename = wp_unique_filename( $upload_dir['path'], 'my-new-file.jpg' );
		$this->assertEquals( 'my-new-file.jpg', $filename );
	}

	function test_get_attachment_files() {
		S3_Uploads\Plugin::get_instance()->setup();
		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', $upload_dir['path'] . '/sunflower.jpg' );
		$test_file = $upload_dir['path'] . '/sunflower.jpg';
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );

		$files = S3_Uploads\Plugin::get_instance()->get_attachment_files( $attachment_id );
		$this->assertIsArray( $files );
		$this->assertNotEmpty( $files );
		$this->assertContains( $upload_dir['path'] . '/sunflower.jpg', $files );
	}

	function test_get_attachment_files_intermediate_sizes() {
		S3_Uploads\Plugin::get_instance()->setup();
		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', $upload_dir['path'] . '/sunflower.jpg' );
		$test_file = $upload_dir['path'] . '/sunflower.jpg';
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );

		// Generate metadata to create intermediate sizes
		wp_generate_attachment_metadata( $attachment_id, $test_file );

		$files = S3_Uploads\Plugin::get_instance()->get_attachment_files( $attachment_id );
		$this->assertIsArray( $files );
		$this->assertNotEmpty( $files );
		$this->assertContains( $upload_dir['path'] . '/sunflower-300x196.jpg', $files );
		$this->assertContains( $upload_dir['path'] . '/sunflower-150x150.jpg', $files );
	}

	function test_get_attachment_files_intermediate_sizes_in_subdir() {
		S3_Uploads\Plugin::get_instance()->setup();
		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', $upload_dir['path'] . '/a/sub/dir/sunflower.jpg' );
		$test_file = $upload_dir['path'] . '/a/sub/dir/sunflower.jpg';
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );

		// Generate metadata to create intermediate sizes
		wp_generate_attachment_metadata( $attachment_id, $test_file );

		$files = S3_Uploads\Plugin::get_instance()->get_attachment_files( $attachment_id );
		$this->assertIsArray( $files );
		$this->assertNotEmpty( $files );
		$this->assertContains( $upload_dir['path'] . '/a/sub/dir/sunflower.jpg', $files );
		$this->assertContains( $upload_dir['path'] . '/a/sub/dir/sunflower-300x196.jpg', $files );
		$this->assertContains( $upload_dir['path'] . '/a/sub/dir/sunflower-150x150.jpg', $files );
	}


	function test_get_s3_location_for_url() {
		$plugin = S3_Uploads\Plugin::get_instance();
		$plugin->setup();

		// Canonical S3 bucket URL.
		$canonical_url = "https://" . S3_UPLOADS_BUCKET . ".s3.amazonaws.com/uploads/2026/09/photo.jpg";
		$location      = $plugin->get_s3_location_for_url( $canonical_url );
		$this->assertIsArray( $location );
		$this->assertEquals( S3_UPLOADS_BUCKET, $location["bucket"] );
		$this->assertEquals( "uploads/2026/09/photo.jpg", $location["key"] );

		// Base upload URL from wp_upload_dir.
		$upload_dir = wp_upload_dir();
		$media_url  = $upload_dir["baseurl"] . "/2026/09/test-image.jpg";
		$location   = $plugin->get_s3_location_for_url( $media_url );
		$this->assertIsArray( $location );
		$this->assertEquals( S3_UPLOADS_BUCKET, $location["bucket"] );

		// Unrelated external URL returns null.
		$this->assertNull( $plugin->get_s3_location_for_url( "https://example.com/other.jpg" ) );
	}

	function test_get_s3_location_for_path() {
		$plugin = S3_Uploads\Plugin::get_instance();

		$valid_path = "s3://" . S3_UPLOADS_BUCKET . "/2026/09/photo.jpg";
		$location   = $plugin->get_s3_location_for_path( $valid_path );
		$this->assertIsArray( $location );
		$this->assertEquals( S3_UPLOADS_BUCKET, $location["bucket"] );
		$this->assertEquals( "2026/09/photo.jpg", $location["key"] );

		// Non-s3 stream or local path returns null.
		$this->assertNull( $plugin->get_s3_location_for_path( "/var/www/uploads/test.jpg" ) );
		$this->assertNull( $plugin->get_s3_location_for_path( "https://example.com/test.jpg" ) );
	}

	function test_wp_filter_resource_hints() {
		$plugin = S3_Uploads\Plugin::get_instance();

		$hints = $plugin->wp_filter_resource_hints( [], "dns-prefetch" );
		$this->assertContains( $plugin->get_s3_url(), $hints );

		// Other relation types should not receive the S3 URL.
		$hints_prerender = $plugin->wp_filter_resource_hints( [], "prerender" );
		$this->assertEmpty( $hints_prerender );
	}

	function test_is_private_attachment() {
		$plugin = S3_Uploads\Plugin::get_instance();
		$attachment_id = self::factory()->attachment->create_object( "image.jpg", 0 );

		// Default is not private.
		$this->assertFalse( $plugin->is_private_attachment( $attachment_id ) );

		// Filter to mark attachment as private.
		$filter = function ( $is_private, $id ) use ( $attachment_id ) {
			return $id === $attachment_id ? true : $is_private;
		};
		add_filter( "s3_uploads_is_attachment_private", $filter, 10, 2 );

		$this->assertTrue( $plugin->is_private_attachment( $attachment_id ) );

		remove_filter( "s3_uploads_is_attachment_private", $filter, 10 );
	}

	function test_add_s3_signed_params_to_attachment_url() {
		$plugin = S3_Uploads\Plugin::get_instance();
		$plugin->setup();

		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . "/data/sunflower.jpg", $upload_dir["path"] . "/signed-test.jpg" );
		$test_file     = $upload_dir["path"] . "/signed-test.jpg";
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			"post_mime_type" => "image/jpeg",
		) );

		$url = wp_get_attachment_url( $attachment_id );

		// When not private, URL is returned unmodified.
		$unsigned_url = $plugin->add_s3_signed_params_to_attachment_url( $url, $attachment_id );
		$this->assertEquals( $url, $unsigned_url );

		// Mark attachment as private.
		add_filter( "s3_uploads_is_attachment_private", "__return_true" );

		$signed_url = $plugin->add_s3_signed_params_to_attachment_url( $url, $attachment_id );
		$this->assertNotEquals( $url, $signed_url );
		$this->assertStringContainsString( "X-Amz-Signature", $signed_url );

		// Custom expiry filter.
		$expiry_filter = function () {
			return "+2 hours";
		};
		add_filter( "s3_uploads_private_attachment_url_expiry", $expiry_filter );
		$signed_url_custom_expiry = $plugin->add_s3_signed_params_to_attachment_url( $url, $attachment_id );
		$this->assertStringContainsString( "X-Amz-Signature", $signed_url_custom_expiry );
		remove_filter( "s3_uploads_private_attachment_url_expiry", $expiry_filter );

		// Strips previous query params to avoid corruption.
		$url_with_query = $url . "?foo=bar";
		$cleaned_signed_url = $plugin->add_s3_signed_params_to_attachment_url( $url_with_query, $attachment_id );
		$this->assertStringNotContainsString( "foo=bar", $cleaned_signed_url );
		$this->assertStringContainsString( "X-Amz-Signature", $cleaned_signed_url );

		remove_filter( "s3_uploads_is_attachment_private", "__return_true" );
	}

	function test_add_s3_signed_params_to_attachment_image_src() {
		$plugin = S3_Uploads\Plugin::get_instance();
		$plugin->setup();

		// Empty/false image inputs.
		$this->assertFalse( $plugin->add_s3_signed_params_to_attachment_image_src( false, 1 ) );
		$raw_image = array( "https://example.com/img.jpg", 150, 150 );
		$this->assertEquals( $raw_image, $plugin->add_s3_signed_params_to_attachment_image_src( $raw_image, 0 ) );
		$this->assertEquals( $raw_image, $plugin->add_s3_signed_params_to_attachment_image_src( $raw_image, "" ) );

		// Valid private attachment image.
		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . "/data/sunflower.jpg", $upload_dir["path"] . "/src-test.jpg" );
		$test_file     = $upload_dir["path"] . "/src-test.jpg";
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			"post_mime_type" => "image/jpeg",
		) );

		add_filter( "s3_uploads_is_attachment_private", "__return_true" );

		$orig_src   = array( wp_get_attachment_url( $attachment_id ), 150, 150 );
		$signed_src = $plugin->add_s3_signed_params_to_attachment_image_src( $orig_src, $attachment_id );

		$this->assertIsArray( $signed_src );
		$this->assertStringContainsString( "X-Amz-Signature", $signed_src[0] );
		$this->assertEquals( 150, $signed_src[1] );
		$this->assertEquals( 150, $signed_src[2] );

		remove_filter( "s3_uploads_is_attachment_private", "__return_true" );
	}

	function test_add_s3_signed_params_to_attachment_image_srcset() {
		$plugin = S3_Uploads\Plugin::get_instance();
		$plugin->setup();

		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . "/data/sunflower.jpg", $upload_dir["path"] . "/srcset-test.jpg" );
		$test_file     = $upload_dir["path"] . "/srcset-test.jpg";
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			"post_mime_type" => "image/jpeg",
		) );

		add_filter( "s3_uploads_is_attachment_private", "__return_true" );

		$url     = wp_get_attachment_url( $attachment_id );
		$sources = array(
			300 => array(
				"url"        => $url,
				"descriptor" => "w",
				"value"      => 300,
			),
		);

		$signed_sources = $plugin->add_s3_signed_params_to_attachment_image_srcset( $sources, array(), $url, array(), $attachment_id );
		$this->assertStringContainsString( "X-Amz-Signature", $signed_sources[300]["url"] );

		remove_filter( "s3_uploads_is_attachment_private", "__return_true" );
	}

	function test_set_attachment_files_acl() {
		$plugin = S3_Uploads\Plugin::get_instance();
		$plugin->setup();

		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . "/data/sunflower.jpg", $upload_dir["path"] . "/acl-test.jpg" );
		$test_file     = $upload_dir["path"] . "/acl-test.jpg";
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			"post_mime_type" => "image/jpeg",
		) );

		$action_fired = false;
		$action_hook  = function ( $id, $acl ) use ( &$action_fired, $attachment_id ) {
			if ( $id === $attachment_id && "private" === $acl ) {
				$action_fired = true;
			}
		};
		add_action( "s3_uploads_set_attachment_files_acl", $action_hook, 10, 2 );

		$result = $plugin->set_attachment_files_acl( $attachment_id, "private" );
		$this->assertNull( $result );
		$this->assertTrue( $action_fired, "Action s3_uploads_set_attachment_files_acl must fire when setting ACL." );

		remove_action( "s3_uploads_set_attachment_files_acl", $action_hook, 10 );
	}

	function test_set_attachment_private_on_generate_attachment_metadata() {
		$plugin = S3_Uploads\Plugin::get_instance();
		$plugin->setup();

		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . "/data/sunflower.jpg", $upload_dir["path"] . "/meta-acl-test.jpg" );
		$test_file     = $upload_dir["path"] . "/meta-acl-test.jpg";
		$attachment_id = self::factory()->attachment->create_object( $test_file, 0, array(
			"post_mime_type" => "image/jpeg",
		) );

		add_filter( "s3_uploads_is_attachment_private", "__return_true" );

		$metadata = array( "width" => 150, "height" => 150 );
		$result   = $plugin->set_attachment_private_on_generate_attachment_metadata( $metadata, $attachment_id );

		$this->assertEquals( $metadata, $result );

		remove_filter( "s3_uploads_is_attachment_private", "__return_true" );
	}

	function test_privacy_personal_data_export_dir_and_file_move() {
		$plugin = S3_Uploads\Plugin::get_instance();
		$plugin->setup();

		// S3 destination path should be overridden to a temp directory.
		$s3_dir   = "s3://" . S3_UPLOADS_BUCKET . "/exports/";
		$temp_dir = S3_Uploads\set_wp_privacy_exports_dir( $s3_dir );
		$this->assertNotEquals( $s3_dir, $temp_dir );
		$this->assertStringStartsWith( get_temp_dir(), $temp_dir );
		$this->assertTrue( is_dir( $temp_dir ) );
		$this->assertTrue( file_exists( $temp_dir . "index.html" ) );

		// Non-S3 path should be returned unchanged.
		$local_dir = "/var/www/html/exports/";
		$this->assertEquals( $local_dir, S3_Uploads\set_wp_privacy_exports_dir( $local_dir ) );

		// Move temp personal data file to S3.
		$dummy_archive = $temp_dir . "export-user-1.zip";
		file_put_contents( $dummy_archive, "dummy zip content" );
		$this->assertTrue( file_exists( $dummy_archive ) );

		S3_Uploads\move_temp_personal_data_to_s3( $dummy_archive );

		// Temp file should have been unlinked after move.
		$this->assertFalse( file_exists( $dummy_archive ) );

		$upload_dir  = wp_upload_dir();
		$destination = trailingslashit( $upload_dir["basedir"] ) . "wp-personal-data-exports/export-user-1.zip";
		$this->assertTrue( file_exists( $destination ) );
		$this->assertEquals( "dummy zip content", file_get_contents( $destination ) );
	}
}
