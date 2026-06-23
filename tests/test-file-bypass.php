<?php

use S3_Uploads\File_Bypass;

/**
 * Tests for the File_Bypass class and the bypass integration in the S3 stream wrapper.
 *
 * Bypass rules are configured via the `s3_uploads_bypass_rules` filter in each test so that
 * PHP constants (which can only be defined once) are not required. File_Bypass::reset() is
 * called in setUp/tearDown to clear the rule cache between tests.
 */
class Test_File_Bypass extends WP_UnitTestCase {

	/** @var string Temporary directory used for 'local' action tests. */
	private $tmp_dir;

	public function setUp() {
		parent::setUp();
		File_Bypass::reset();
		$this->tmp_dir = sys_get_temp_dir() . '/s3-uploads-bypass-tests-' . uniqid();
		mkdir( $this->tmp_dir, 0755, true );
	}

	public function tearDown() {
		parent::tearDown();
		File_Bypass::reset();
		remove_all_filters( 's3_uploads_bypass_rules' );
		$this->rmdir_recursive( $this->tmp_dir );
	}

	// -------------------------------------------------------------------------
	// File_Bypass::match() — unit tests (no S3 needed)
	// -------------------------------------------------------------------------

	public function test_match_returns_null_when_no_rules() {
		$this->assertNull( File_Bypass::match( 's3://tests/image.jpg' ) );
	}

	public function test_match_returns_rule_on_exact_basename() {
		add_filter( 's3_uploads_bypass_rules', function() {
			return [ [ 'pattern' => '.htaccess', 'action' => 'void' ] ];
		} );

		$rule = File_Bypass::match( 's3://tests/uploads/2024/.htaccess' );
		$this->assertNotNull( $rule );
		$this->assertEquals( 'void', $rule['action'] );
	}

	public function test_match_returns_rule_on_glob_basename() {
		add_filter( 's3_uploads_bypass_rules', function() {
			return [ [ 'pattern' => '*.htaccess', 'action' => 'void' ] ];
		} );

		$rule = File_Bypass::match( 's3://tests/wp-content/uploads/.htaccess' );
		$this->assertNotNull( $rule );
		$this->assertEquals( 'void', $rule['action'] );
	}

	public function test_match_returns_null_for_non_matching_file() {
		add_filter( 's3_uploads_bypass_rules', function() {
			return [ [ 'pattern' => '.htaccess', 'action' => 'void' ] ];
		} );

		$this->assertNull( File_Bypass::match( 's3://tests/uploads/sunflower.jpg' ) );
	}

	public function test_match_returns_first_matching_rule() {
		add_filter( 's3_uploads_bypass_rules', function() {
			return [
				[ 'pattern' => '*.htaccess', 'action' => 'void' ],
				[ 'pattern' => '*.htaccess', 'action' => 'exists' ],
			];
		} );

		$rule = File_Bypass::match( 's3://tests/.htaccess' );
		$this->assertEquals( 'void', $rule['action'] );
	}

	public function test_match_exists_action() {
		add_filter( 's3_uploads_bypass_rules', function() {
			return [ [ 'pattern' => 'index.php', 'action' => 'exists' ] ];
		} );

		$rule = File_Bypass::match( 's3://tests/uploads/index.php' );
		$this->assertNotNull( $rule );
		$this->assertEquals( 'exists', $rule['action'] );
	}

	public function test_match_local_action_with_target() {
		add_filter( 's3_uploads_bypass_rules', function() {
			return [ [ 'pattern' => '*.log', 'action' => 'local', 'target' => '/tmp/logs' ] ];
		} );

		$rule = File_Bypass::match( 's3://tests/uploads/debug.log' );
		$this->assertNotNull( $rule );
		$this->assertEquals( 'local', $rule['action'] );
		$this->assertEquals( '/tmp/logs', $rule['target'] );
	}

	// -------------------------------------------------------------------------
	// Stream wrapper — void action
	// -------------------------------------------------------------------------

	public function test_void_write_returns_true_without_hitting_s3() {
		$this->add_void_rule( '*.htaccess' );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/.htaccess';
		$result = file_put_contents( $path, 'deny from all' );

		// file_put_contents returns the number of bytes written (or false on failure).
		$this->assertNotFalse( $result );
	}

	public function test_void_file_does_not_exist_in_s3() {
		$this->add_void_rule( '*.htaccess' );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/.htaccess';
		file_put_contents( $path, 'deny from all' );

		$this->assertFalse( file_exists( $path ) );
	}

	public function test_void_unlink_returns_true() {
		$this->add_void_rule( '*.htaccess' );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/.htaccess';
		$this->assertTrue( unlink( $path ) );
	}

	public function test_void_read_fails() {
		$this->add_void_rule( '*.htaccess' );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/.htaccess';
		// Reading a void file should fail (file does not exist).
		$result = @file_get_contents( $path );
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// Stream wrapper — exists action
	// -------------------------------------------------------------------------

	public function test_exists_file_exists_returns_true_without_writing() {
		$this->add_exists_rule( 'index.php' );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/index.php';
		// File was never written, but the rule makes it appear to exist.
		$this->assertTrue( file_exists( $path ) );
	}

	public function test_exists_write_returns_true_without_hitting_s3() {
		$this->add_exists_rule( 'index.php' );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/index.php';
		$result = file_put_contents( $path, '<?php // silence' );
		$this->assertNotFalse( $result );
	}

	public function test_exists_file_still_exists_after_write() {
		$this->add_exists_rule( 'index.php' );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/index.php';
		file_put_contents( $path, '<?php // silence' );
		$this->assertTrue( file_exists( $path ) );
	}

	public function test_exists_read_returns_empty_content() {
		$this->add_exists_rule( 'index.php' );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/index.php';
		$content = file_get_contents( $path );
		// File appears to exist with empty content.
		$this->assertSame( '', $content );
	}

	public function test_exists_unlink_returns_true() {
		$this->add_exists_rule( 'index.php' );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/index.php';
		$this->assertTrue( unlink( $path ) );
	}

	// -------------------------------------------------------------------------
	// Stream wrapper — local action with custom target
	// -------------------------------------------------------------------------

	public function test_local_write_creates_file_in_target_dir() {
		$this->add_local_rule( '*.log', $this->tmp_dir );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/debug.log';
		$result = file_put_contents( $path, 'log entry' );
		$this->assertNotFalse( $result );

		$expected_local = $this->tmp_dir . '/uploads/debug.log';
		$this->assertFileExists( $expected_local );
		$this->assertSame( 'log entry', file_get_contents( $expected_local ) );
	}

	public function test_local_write_does_not_create_s3_object() {
		$this->add_local_rule( '*.log', $this->tmp_dir );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/debug.log';
		file_put_contents( $path, 'log entry' );

		// The stream wrapper's url_stat for a local file checks the local path, not S3.
		// To confirm no S3 object was created, bypass the local rule and check via S3.
		File_Bypass::reset();
		remove_all_filters( 's3_uploads_bypass_rules' );
		$this->assertFalse( file_exists( $path ) );
	}

	public function test_local_read_returns_file_contents() {
		$this->add_local_rule( '*.log', $this->tmp_dir );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/debug.log';
		file_put_contents( $path, 'hello local' );

		// Reset and re-add rule to read back through the stream wrapper.
		File_Bypass::reset();
		$this->add_local_rule( '*.log', $this->tmp_dir );

		$content = file_get_contents( $path );
		$this->assertSame( 'hello local', $content );
	}

	public function test_local_file_exists_returns_true_when_file_present() {
		$this->add_local_rule( '*.log', $this->tmp_dir );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/debug.log';
		file_put_contents( $path, 'data' );

		File_Bypass::reset();
		$this->add_local_rule( '*.log', $this->tmp_dir );

		$this->assertTrue( file_exists( $path ) );
	}

	public function test_local_file_exists_returns_false_when_file_absent() {
		$this->add_local_rule( '*.log', $this->tmp_dir );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/missing.log';
		$this->assertFalse( file_exists( $path ) );
	}

	public function test_local_unlink_removes_local_file() {
		$this->add_local_rule( '*.log', $this->tmp_dir );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/debug.log';
		file_put_contents( $path, 'data' );

		File_Bypass::reset();
		$this->add_local_rule( '*.log', $this->tmp_dir );

		$result = unlink( $path );
		$this->assertTrue( $result );

		$expected_local = $this->tmp_dir . '/uploads/debug.log';
		$this->assertFileDoesNotExist( $expected_local );
	}

	public function test_local_creates_nested_directories() {
		$this->add_local_rule( '*.log', $this->tmp_dir );

		$path = 's3://' . S3_UPLOADS_BUCKET . '/uploads/2024/06/app.log';
		file_put_contents( $path, 'nested' );

		$expected_local = $this->tmp_dir . '/uploads/2024/06/app.log';
		$this->assertFileExists( $expected_local );
	}

	// -------------------------------------------------------------------------
	// Ensure non-bypassed files are unaffected
	// -------------------------------------------------------------------------

	public function test_regular_files_are_not_bypassed() {
		$this->add_void_rule( '*.htaccess' );

		$local_path = dirname( __FILE__ ) . '/data/sunflower.jpg';
		$remote_path = 's3://' . S3_UPLOADS_BUCKET . '/sunflower-bypass-test.jpg';

		$result = copy( $local_path, $remote_path );
		$this->assertTrue( $result );
		$this->assertTrue( file_exists( $remote_path ) );

		// Cleanup.
		unlink( $remote_path );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function add_void_rule( string $pattern ) : void {
		add_filter( 's3_uploads_bypass_rules', function() use ( $pattern ) {
			return [ [ 'pattern' => $pattern, 'action' => 'void' ] ];
		} );
	}

	private function add_exists_rule( string $pattern ) : void {
		add_filter( 's3_uploads_bypass_rules', function() use ( $pattern ) {
			return [ [ 'pattern' => $pattern, 'action' => 'exists' ] ];
		} );
	}

	private function add_local_rule( string $pattern, string $target ) : void {
		add_filter( 's3_uploads_bypass_rules', function() use ( $pattern, $target ) {
			return [ [ 'pattern' => $pattern, 'action' => 'local', 'target' => $target ] ];
		} );
	}

	private function rmdir_recursive( string $dir ) : void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = array_diff( scandir( $dir ), [ '.', '..' ] );
		foreach ( $items as $item ) {
			$full = $dir . '/' . $item;
			is_dir( $full ) ? $this->rmdir_recursive( $full ) : unlink( $full );
		}
		rmdir( $dir );
	}
}
