<?php

class Test_S3_Uploads_Stream_Wrapper extends WP_UnitTestCase {

	protected $s3 = null;

	public function setUp() {

	}

	public function test_stream_wrapper_is_registered() {
		$this->assertTrue( in_array( 's3', stream_get_wrappers() ) );
	}

	public function test_copy_via_stream_wrapper() {

		$result = copy( dirname( __FILE__ ) . '/data/canola.jpg', 's3://' . S3_UPLOADS_BUCKET . '/canola.jpg' );
		$this->assertTrue( $result );
	}

	public function test_rename_via_stream_wrapper() {

		copy( dirname( __FILE__ ) . '/data/canola.jpg', 's3://' . S3_UPLOADS_BUCKET . '/canola.jpg' );
		$result = rename( 's3://' . S3_UPLOADS_BUCKET . '/canola.jpg', 's3://' . S3_UPLOADS_BUCKET . '/canola-test.jpg' );
		$this->assertTrue( $result );
		$this->assertTrue( file_exists( 's3://' . S3_UPLOADS_BUCKET . '/canola-test.jpg' ) );
	}

	public function test_unlink_via_stream_wrapper() {

		copy( dirname( __FILE__ ) . '/data/canola.jpg', 's3://' . S3_UPLOADS_BUCKET . '/canola.jpg' );
		$result = unlink( 's3://' . S3_UPLOADS_BUCKET . '/canola.jpg' );
		$this->assertTrue( $result );
		$this->assertFalse( file_exists( 's3://' . S3_UPLOADS_BUCKET . '/canola.jpg' ) );
	}

	public function test_copy_via_stream_wrapper_fails_on_invalid_permission() {
		stream_wrapper_unregister( 's3' );
		$params = array( 'key' => S3_UPLOADS_KEY, 'secret' => S3_UPLOADS_SECRET );
		$s3 = Aws\Common\Aws::factory( $params )->get( 's3' );

		S3_Uploads_Stream_Wrapper::register( $s3 );
		stream_context_set_option( stream_context_get_default(), 's3', 'ACL', Aws\S3\Enum\CannedAcl::PUBLIC_READ );

		$bucket_root = strtok( S3_UPLOADS_BUCKET, '/' );
		$result = @copy( dirname( __FILE__ ) . '/data/canola.jpg', 's3://' . $bucket_root . '/canola.jpg' );

		$this->assertFalse( $result );
	}

	public function test_rename_via_stream_wrapper_fails_on_invalid_permission() {

		stream_wrapper_unregister( 's3' );
		$params = array( 'key' => S3_UPLOADS_KEY, 'secret' => S3_UPLOADS_SECRET );
		$s3 = Aws\Common\Aws::factory( $params )->get( 's3' );

		S3_Uploads_Stream_Wrapper::register( $s3 );
		stream_context_set_option( stream_context_get_default(), 's3', 'ACL', Aws\S3\Enum\CannedAcl::PUBLIC_READ );

		copy( dirname( __FILE__ ) . '/data/canola.jpg', 's3://' . S3_UPLOADS_BUCKET . '/canola.jpg' );
		$bucket_root = strtok( S3_UPLOADS_BUCKET, '/' );
		$result = @rename( 's3://' . S3_UPLOADS_BUCKET . '/canola.jpg', 's3://' . $bucket_root . '/canola.jpg' );

		$this->assertFalse( $result );
	}
}