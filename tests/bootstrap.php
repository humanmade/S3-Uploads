<?php
/**
 * Bootstrap the plugin unit testing environment.
 *
 * @package WordPress
 * @subpackage JSON API
*/

// Support for:
// 1. `WP_DEVELOP_DIR` environment variable
// 2. Plugin installed inside of WordPress.org developer checkout
// 3. Tests checked out to /tmp
if ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} else if ( file_exists( '../../../../tests/phpunit/includes/bootstrap.php' ) ) {
	$test_root = '../../../../tests/phpunit';
} else if ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
	$test_root = '/tmp/wordpress-tests-lib';
}

require $test_root . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../s3-uploads.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

if ( getenv( 'S3_UPLOADS_BUCKET' ) ) {
	define( 'S3_UPLOADS_BUCKET', getenv( 'S3_UPLOADS_BUCKET' ) );
}

if ( getenv( 'S3_UPLOADS_KEY' ) ) {
	define( 'S3_UPLOADS_KEY', getenv( 'S3_UPLOADS_KEY' ) );
}

if ( getenv( 'S3_UPLOADS_SECRET' ) ) {
	define( 'S3_UPLOADS_SECRET', getenv( 'S3_UPLOADS_SECRET' ) );
}

if ( getenv( 'S3_UPLOADS_REGION' ) ) {
	define( 'S3_UPLOADS_REGION', getenv( 'S3_UPLOADS_REGION' ) );
}

require $test_root . '/includes/bootstrap.php';
