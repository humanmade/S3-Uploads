<?php

class Test_S3_Uploads extends WP_UnitTestCase {

	protected $s3 = null;

	public function setUp() {

		// start the tests with nothing added
		S3_Uploads\Plugin::get_instance()->tear_down();
	}

	public function tearDown() {
		// reenable for other tests
		S3_Uploads\Plugin::get_instance()->setup();
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
		$attachment_id = $this->factory->attachment->create_object( $test_file, 0, array(
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
		$attachment_id = $this->factory->attachment->create_object( $test_file, 0, array(
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
		$attachment_id = $this->factory->attachment->create_object( $test_file, 0, array(
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
		$attachment_id = $this->factory->attachment->create_object( $test_file, 0, array(
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

}
