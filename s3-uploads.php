<?php

/*
Plugin Name: S3 Uploads
Description: Store uploads in S3
Author: Human Made Limited
Version: 3.0.8
Author URI: https://hmn.md
*/

require_once __DIR__ . '/inc/namespace.php';
require_once __DIR__ . '/inc/audit-logging/namespace.php';

add_action( 'plugins_loaded', 'S3_Uploads\\init', 0 );
add_action( 'plugins_loaded', 'S3_Uploads\\Audit_Logging\\bootstrap', 11 );
