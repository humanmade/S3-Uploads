<?php

class Test_S3_Uploads_Stream_Wrapper extends WP_UnitTestCase {

	protected $s3 = null;

	public function setUp() {

	}

	public function tearDown() {
		stream_wrapper_unregister( 's3' );
		S3_Uploads\Plugin::get_instance()->register_stream_wrapper();
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

		$uploads = new S3_Uploads\Plugin( S3_UPLOADS_BUCKET, S3_UPLOADS_KEY, '123', null, S3_UPLOADS_REGION );
		$uploads->register_stream_wrapper();

		$bucket_root = strtok( S3_UPLOADS_BUCKET, '/' );
		$result = @copy( dirname( __FILE__ ) . '/data/sunflower.jpg', 's3://' . $bucket_root . '/sunflower.jpg' );

		$this->assertFalse( $result );
	}

	public function test_rename_via_stream_wrapper_fails_on_invalid_permission() {

		copy( dirname( __FILE__ ) . '/data/sunflower.jpg', 's3://' . S3_UPLOADS_BUCKET . '/sunflower.jpg' );

		stream_wrapper_unregister( 's3' );

		$uploads = new S3_Uploads\Plugin( S3_UPLOADS_BUCKET, S3_UPLOADS_KEY, '123', null, S3_UPLOADS_REGION );
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

	public function test_list_directory_with_wildcard() {
		$upload_dir = wp_upload_dir();

		file_put_contents( $upload_dir['path'] . '/my-file-scaled.jpg', '' );
		file_put_contents( $upload_dir['path'] . '/some-file-scaled.jpg', '' );
		$files = scandir( $upload_dir['path'] . '/my-file*' );
		$this->assertEquals(
			[
				'my-file-scaled.jpg',
			],
			$files
		);
	}

	public function test_rename_directory_via_stream_wrapper() {
		$upload_dir = wp_upload_dir();
		$test_dir = $upload_dir['path'] . '/test-dir';
		$renamed_dir = $upload_dir['path'] . '/renamed-dir';

		// Create a directory with files
		mkdir( $test_dir, 0755, true );
		$test_file1 = $test_dir . '/file1.txt';
		$test_file2 = $test_dir . '/subdir/file2.txt';

		file_put_contents( $test_file1, 'content1' );
		mkdir( $test_dir . '/subdir', 0755, true );
		file_put_contents( $test_file2, 'content2' );

		// Rename the directory
		$result = rename( $test_dir, $renamed_dir );
		$this->assertTrue( $result, 'Directory rename should succeed' );

		// Verify original directory files don't exist
		$this->assertFalse( file_exists( $test_dir . '/file1.txt' ), 'Original file should not exist' );
		$this->assertFalse( file_exists( $test_dir . '/subdir/file2.txt' ), 'Original subdir file should not exist' );

		// Verify renamed directory files exist
		$this->assertTrue( file_exists( $renamed_dir . '/file1.txt' ), 'Renamed file should exist' );
		$this->assertTrue( file_exists( $renamed_dir . '/subdir/file2.txt' ), 'Renamed subdir file should exist' );

		// Verify file contents
		$this->assertEquals( 'content1', file_get_contents( $renamed_dir . '/file1.txt' ), 'File1 content should match' );
		$this->assertEquals( 'content2', file_get_contents( $renamed_dir . '/subdir/file2.txt' ), 'File2 content should match' );
	}

	public function test_rename_empty_directory_via_stream_wrapper() {
		$upload_dir = wp_upload_dir();
		$test_dir = $upload_dir['path'] . '/empty-dir';
		$renamed_dir = $upload_dir['path'] . '/empty-renamed';

		// Create an empty directory
		mkdir( $test_dir, 0755, true );

		// Rename the empty directory
		$result = rename( $test_dir, $renamed_dir );
		$this->assertTrue( $result, 'Empty directory rename should succeed' );

		// Verify renamed directory exists
		$this->assertTrue( is_dir( $renamed_dir ), 'Renamed empty directory should exist' );
		
		// Note: For empty directories, file_exists() may still return true because
		// S3 checks for objects with that prefix. The directory marker should have been moved.
		// We verify the renamed directory exists instead of checking the original doesn't.
	}

	public function test_rename_directory_with_multiple_files_via_stream_wrapper() {
		$upload_dir = wp_upload_dir();
		$test_dir = $upload_dir['path'] . '/multi-dir';
		$renamed_dir = $upload_dir['path'] . '/multi-renamed';

		// Create a directory with multiple files
		mkdir( $test_dir, 0755, true );
		$files = [ 'file1.txt', 'file2.txt', 'file3.txt', 'nested/deep/file4.txt' ];
		$contents = [ 'content1', 'content2', 'content3', 'content4' ];

		foreach ( $files as $index => $file ) {
			$file_path = $test_dir . '/' . $file;
			$dir_path = dirname( $file_path );
			if ( ! is_dir( $dir_path ) ) {
				mkdir( $dir_path, 0755, true );
			}
			file_put_contents( $file_path, $contents[ $index ] );
		}

		// Rename the directory
		$result = rename( $test_dir, $renamed_dir );
		$this->assertTrue( $result, 'Directory with multiple files rename should succeed' );

		// Verify all files were moved correctly
		foreach ( $files as $index => $file ) {
			$renamed_file = $renamed_dir . '/' . $file;
			$this->assertTrue( file_exists( $renamed_file ), "Renamed file {$file} should exist" );
			$this->assertEquals( $contents[ $index ], file_get_contents( $renamed_file ), "File {$file} content should match" );
		}

		// Verify original directory files don't exist
		foreach ( $files as $file ) {
			$original_file = $test_dir . '/' . $file;
			$this->assertFalse( file_exists( $original_file ), "Original file {$file} should not exist" );
		}
	}
}
