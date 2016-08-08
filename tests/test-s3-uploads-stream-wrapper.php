<?php

class Test_S3_Uploads_Stream_Wrapper extends WP_UnitTestCase {

	protected $s3 = null;

	public function setUp() {

	}

	public function tearDown() {
		stream_wrapper_unregister( 's3' );
		S3_Uploads::get_instance()->register_stream_wrapper();
	}

	public function test_stream_wrapper_is_registered() {
		$this->assertTrue( in_array( 's3', stream_get_wrappers() ) );
	}

	public function test_copy_via_stream_wrapper() {

		$local_path = dirname( __FILE__ ) . '/data/sunflower.jpg';
		$remote_path = 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg';
		$result = copy( $local_path, $remote_path );
		$this->assertTrue( $result );
		$this->assertEquals( file_get_contents( $local_path ), file_get_contents( $remote_path ) );
	}

	public function test_rename_via_stream_wrapper() {

		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg' );
		$result = rename( 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg', 's3://' . S3_UPLOADS_BUCKET . '/sunflower-test.jpg' );
		$this->assertTrue( $result );
		$this->assertTrue( file_exists( 's3://' . S3_UPLOADS_BUCKET . '/sunflower-test.jpg' ) );
	}

	public function test_unlink_via_stream_wrapper() {

		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg' );
		$result = unlink( 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg' );
		$this->assertTrue( $result );
		$this->assertFalse( file_exists( 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg' ) );
	}

	public function test_copy_via_stream_wrapper_fails_on_invalid_permission() {

		stream_wrapper_unregister( 's3' );

		$uploads = new S3_Uploads( S3_UPLOADS_BUCKET, S3_UPLOADS_KEY, '123', null, S3_UPLOADS_REGION );
		$uploads->register_stream_wrapper();

		$bucket_root = strtok( S3_UPLOADS_BUCKET, '/' );
		$result = @copy( dirname( __FILE__ ) . '/data/sunflower.jpg', 's3://' . $bucket_root . '/sunflower.jpg' );

		$this->assertFalse( $result );
	}

	public function test_rename_via_stream_wrapper_fails_on_invalid_permission() {

		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg' );

		stream_wrapper_unregister( 's3' );

		$uploads = new S3_Uploads( S3_UPLOADS_BUCKET, S3_UPLOADS_KEY, '123', null, S3_UPLOADS_REGION );
		$uploads->register_stream_wrapper();

		$bucket_root = strtok( S3_UPLOADS_BUCKET, '/' );
		$result = @rename( 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg', 's3://' . $bucket_root . '/sunflower.jpg' );

		$this->assertFalse( $result );
	}

	/**
	 * As s3 doesn't have directories, we expect that mkdir does not cause any s3
	 * connectivity.
	 */
	public function test_file_exists_on_dir_does_not_cause_network_activity() {

		$bucket_root = strtok( S3_UPLOADS_BUCKET, '/' );

		// result would fail as we don't have permission to write here.
		$result = file_exists( 's3://' . $bucket_root . '/some_dir' );
		$this->assertTrue( $result );

		$result = is_dir( 's3://' . $bucket_root . '/some_dir' );
		$this->assertTrue( $result );
	}

	public function get_file_exists_via_stream_wrapper() {
		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg' );
		$this->assertTrue( file_exists( 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg' ) );
		$this->assertFalse( file_exists( 's3://' . S3_UPLOADS_BUCKET . '/sunflower-missing.jpg' ) );
	}

	public function test_getimagesize_via_stream_wrapper() {

		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg' );
		$file = 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg';

		$image = getimagesize( $file );

		$this->assertEquals( array(
			640,
			419,
			2,
			'width="640" height="419"',
			'bits' => 8,
			'channels' => 3,
			'mime' => 'image/jpeg',
		), $image );
	}

	public function test_stream_wrapper_supports_seeking() {

		$file = 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg';
		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', $file );

		$f = fopen( $file, 'r' );
		$result = fseek( $f, 0, SEEK_END );
		fclose( $f );

		$this->assertEquals( 0, $result );
	}

	public function test_wp_handle_upload() {

		$path = tempnam( sys_get_temp_dir(), 'sunflower' ) . '.jpg';
		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', $path );
		$contents = file_get_contents( $path );
		$file = array(
			'error'    => null,
			'tmp_name' => $path,
			'name'     => 'can.jpg',
			'size'     => filesize( $path ),
 		);

		$result = wp_handle_upload( $file, array( 'test_form' => false, 'test_size' => false, 'action' => 'wp_handle_sideload' ) );

		$this->assertTrue( empty( $result['error'] ) );
		$this->assertTrue( file_exists( $result['file'] ) );
		$this->assertEquals( $contents, file_get_contents( $result['file'] ) );
	}
}
