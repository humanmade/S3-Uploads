<?php

/*
Plugin Name: S3 Uploads
Description: Store uploads in S3
Author: assmb.ly / Human Made Limited
Version: 3.0.6-assmbly
Author URI: https://hmn.md
*/

require_once __DIR__ . '/inc/namespace.php';

add_action( 'plugins_loaded', 'S3_Uploads\\init', 0 );
