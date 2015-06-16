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

	/**
	 * As s3 doesn't have directories, we expect that mkdir does not cause any s3
	 * connectivity.
	 */
	public function test_file_exists_on_dir_does_not_cause_network_activity() {

		stream_wrapper_unregister( 's3' );

		// incorrect secret so we'll ato fail if any writing / reading is attempted
		$params = array( 'key' => S3_UPLOADS_KEY, 'secret' => 123 );
		$s3 = Aws\Common\Aws::factory( $params )->get( 's3' );

		S3_Uploads_Stream_Wrapper::register( $s3 );
		stream_context_set_option( stream_context_get_default(), 's3', 'ACL', Aws\S3\Enum\CannedAcl::PUBLIC_READ );

		$bucket_root = strtok( S3_UPLOADS_BUCKET, '/' );

		// result would fail as we don't have permission to write here.
		$result = file_exists( 's3://' . $bucket_root . '/some_dir' );
		$this->assertTrue( $result );

		$result = is_dir( 's3://' . $bucket_root . '/some_dir' );
		$this->assertTrue( $result );
	}

	public function test_http_expires_headers() {
		$expires = strtotime( "+15 days" );
		define( 'S3_UPLOADS_HTTP_EXPIRES', $expires );

		copy( dirname( __FILE__ ) . '/data/canola.jpg', 's3://' . S3_UPLOADS_BUCKET . '/canola.jpg' );

		$request = wp_remote_head( 'https://s3.amazonaws.com/' . S3_UPLOADS_BUCKET . '/canola.jpg' );
		$headers = wp_remote_retrieve_headers( $request );

		$this->assertEquals( $expires, strtotime( $headers['expires'] ) );
	}

	public function test_http_cache_control_headers() {

		define( 'S3_UPLOADS_HTTP_CACHE_CONTROL', 'private, max-age=600' );

		copy( dirname( __FILE__ ) . '/data/canola.jpg', 's3://' . S3_UPLOADS_BUCKET . '/canola.jpg' );

		$request = wp_remote_head( 'https://s3.amazonaws.com/' . S3_UPLOADS_BUCKET . '/canola.jpg' );
		$headers = wp_remote_retrieve_headers( $request );

		$this->assertEquals( 'private, max-age=600', $headers['cache-control'] );
	}
}