<?php

/*
Plugin Name: S3 Uploads
Description: Store uploads in S3
Author: Human Made Limited
Version: 1.0
Author URI: http://hmn.md
*/

require_once dirname( __FILE__ ) . '/inc/class-s3-uploads.php';

if ( defined( 'WP_CLI' ) && WP_CLI )
	require_once dirname( __FILE__ ) . '/inc/class-s3-uploads-wp-cli-command.php';

add_action( 'plugins_loaded', function() {
	S3_Uploads::get_instance();
});

add_filter( 'wp_image_editors', function( $editors ) {

	if ( ( $position = array_search( 'WP_Image_Editor_Imagick', $editors ) ) !== false ) {
		unset($editors[$position]);
	}

	return $editors;
}, 9 );

/**
 * WP Thumb compatibility. WP Thumb supports storing references to images
 * in the database, so it doesn't need to stat() the files. As we 
 * are storing the files on S3, we need this functionality as statting is
 * too slow.
 */
add_filter( 'wpthumb_save_location', function() {
	return 'WP_Thumb_Save_Location_Database';
});