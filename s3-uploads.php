<?php

/*
Plugin Name: S3 Uploads
Description: Store uploads in S3
Author: Human Made Limited
Version: 3.0.0-beta
Author URI: http://hmn.md
*/

require_once __DIR__ . '/inc/namespace.php';

add_action( 'plugins_loaded', 'S3_Uploads\\init' );
spl_autoload_register( 'S3_Uploads\\autoload' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 's3-uploads', __NAMESPACE__ . '\\WP_CLI_Command' );
}
