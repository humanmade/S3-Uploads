<?php

/*
Plugin Name: S3 Uploads
Description: Store uploads in S3
Author: Human Made Limited
Version: 1.0
Author URI: http://hmn.md
*/

require_once dirname( __FILE__ ) . '/inc/class-s3-uploads.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/inc/class-s3-uploads-wp-cli-command.php';
}

add_action( 'plugins_loaded', 's3_uploads_init' );

function s3_uploads_init() {
	if ( ! defined( 'S3_UPLOADS_BUCKET' ) || ! defined( 'S3_UPLOADS_KEY' ) || ! defined( 'S3_UPLOADS_SECRET' ) ) {
		return;
	}

	// Make sure the plugin is enabled when autoenable is on, or in a CLI system
	if ( ( ! defined( 'WP_CLI') || ! WP_CLI ) &&
	     ( defined( 'S3_UPLOADS_AUTOENABLE' ) && false === S3_UPLOADS_AUTOENABLE ) &&
	     'enabled' !== get_option( 's3_uploads_enabled' ) ) {
		return;
	}

	$instance = S3_Uploads::get_instance();
	$instance->register_stream_wrapper();

	add_filter( 'upload_dir', array( $instance, 'filter_upload_dir' ) );
	add_filter( 'wp_image_editors', array( $instance, 'filter_editors' ), 9 );
	remove_filter( 'admin_notices', 'wpthumb_errors' );

	add_action( 'wp_handle_sideload_prefilter', array( $instance, 'filter_sideload_move_temp_file_to_s3' ) );
}