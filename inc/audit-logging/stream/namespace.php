<?php
/**
 * S3 Uploads Audit Logging using Stream.
 */

namespace S3_Uploads\Audit_Logging\Stream;

/**
 * Bootstrap Stream audit logging.
 *
 * @return void
 */
function bootstrap(): void {
	if ( ! class_exists( 'WP_Stream\\Plugin' ) ) {
		return;
	}

	add_filter( 'wp_stream_connectors', __NAMESPACE__ . '\\register_connector' );
}

/**
 * Register the connectors.
 *
 * @param array $classes Array of connector class names.
 *
 * @return array
 */
function register_connector( array $classes ): array {
	require plugin_dir_path( __FILE__ ) . '/class-connector.php';

	$classes['s3uploads'] = new Connector();

	return $classes;
}
