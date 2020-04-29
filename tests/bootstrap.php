<?php
/**
 * Bootstrap the plugin unit testing environment.
 *
 * @package WordPress
 * @subpackage JSON API
*/

// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Give access to tests_add_filter() function.
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../s3-uploads.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

tests_add_filter( 's3_uploads_s3_client_params', function ( array $params ) : array {
	$params['endpoint'] = 'http://172.17.0.2:9000';
	return $params;
} );

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

// Start up the WP testing environment.
require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';
