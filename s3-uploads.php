<?php

/*
Plugin Name: S3 Uploads
Description: Store uploads in S3
Author: Human Made Limited
Version: 1.0
Author URI: http://hmn.md
*/

require_once dirname( __FILE__ ) . '/inc/class-s3-uploads.php';
require_once dirname( __FILE__ ) . '/inc/class-s3-uploads-uploader.php';
require_once dirname( __FILE__ ) . '/inc/class-s3-uploads-wordpress-uploads-uploader.php';

add_action( 'plugins_loaded', function() {
	S3_Uploads::get_instance();
});