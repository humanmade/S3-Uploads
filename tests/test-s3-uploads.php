<?php

class Test_S3_Uploads extends WP_UnitTestCase {

	protected $s3 = null;

	public function setUp() {

		// start the tests with nothing added
		S3_Uploads::get_instance()->tear_down();
	}

	public function tearDown() {
		// reenable for other tests
		S3_Uploads::get_instance()->setup();
	}

	/**
	 * Test s3 uploads sets up all the necessary hooks
	 */
	public function test_setup() {

		S3_Uploads::get_instance()->setup();

		$this->assertEquals( 10, has_action( 'upload_dir', array( S3_Uploads::get_instance(), 'filter_upload_dir' ) ) );
		$this->assertEquals( 9, has_action( 'wp_image_editors', array( S3_Uploads::get_instance(), 'filter_editors' ) ) );
		$this->assertEquals( 10, has_action( 'wp_handle_sideload_prefilter', array( S3_Uploads::get_instance(), 'filter_sideload_move_temp_file_to_s3' ) ) );

		$this->assertTrue( in_array( 's3', stream_get_wrappers() ) );
		S3_Uploads::get_instance()->tear_down();
	}

	/**
	 * Test s3 uploads sets up all the necessary hooks
	 */
	public function test_tear_down() {

		S3_Uploads::get_instance()->setup();
		S3_Uploads::get_instance()->tear_down();

		$this->assertFalse( has_action( 'upload_dir', array( S3_Uploads::get_instance(), 'filter_upload_dir' ) ) );
		$this->assertFalse( has_action( 'wp_image_editors', array( S3_Uploads::get_instance(), 'filter_editors' ) ) );
		$this->assertFalse( has_action( 'wp_handle_sideload_prefilter', array( S3_Uploads::get_instance(), 'filter_sideload_move_temp_file_to_s3' ) ) );

		$this->assertFalse( in_array( 's3', stream_get_wrappers() ) );
	}

	public function test_s3_uploads_enabled() {

		$this->assertTrue( s3_uploads_enabled() );

		update_option( 's3_uploads_enabled', 'enabled' );
		$this->assertTrue( s3_uploads_enabled() );

		delete_option( 's3_uploads_enabled' );
		define( 'S3_UPLOADS_AUTOENABLE', false );

		$this->assertFalse( s3_uploads_enabled() );

		update_option( 's3_uploads_enabled', 'enabled' );
		$this->assertTrue( s3_uploads_enabled() );
	}

	public function test_get_s3_client() {

		$s3 = S3_Uploads::get_instance()->s3();

		$this->assertInstanceOf( 'Aws\\S3\\S3Client', $s3 );
	}

	public function test_generate_attachment_metadata() {
		S3_Uploads::get_instance()->setup();
		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . '/data/canola.jpg', $upload_dir['path'] . '/canola.jpg' );
		$test_file = $upload_dir['path'] . '/canola.jpg';
		$attachment_id = $this->factory->attachment->create_object( $test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );

		$meta_data = wp_generate_attachment_metadata( $attachment_id, $test_file );

		$this->assertEquals( array(
			'file'      => 'canola-150x150.jpg',
			'width'     => 150,
			'height'    => 150,
			'mime-type' => 'image/jpeg',
		), $meta_data['sizes']['thumbnail'] );

		$wp_upload_dir = wp_upload_dir();
		$this->assertTrue( file_exists( $wp_upload_dir['path'] . '/canola-150x150.jpg' ) );
	}

	public function test_image_sizes_are_deleted_on_attachment_delete() {
		S3_Uploads::get_instance()->setup();
		$upload_dir = wp_upload_dir();
		copy( dirname( __FILE__ ) . '/data/canola.jpg', $upload_dir['path'] . '/canola.jpg' );
		$test_file = $upload_dir['path'] . '/canola.jpg';
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
}
