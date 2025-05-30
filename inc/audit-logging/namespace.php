<?php
/**
 * S3 Uploads Audit Logging.
 */

namespace S3_Uploads\Audit_Logging;

/**
 * Bootstrap audit logging.
 *
 * @return void
 */
function bootstrap(): void {
	if ( ! defined( 'S3_UPLOADS_ENABLE_AUDIT_LOGGING' ) || ! S3_UPLOADS_ENABLE_AUDIT_LOGGING ) {
		return;
	}

	require_once __DIR__ . '/stream/namespace.php';

	Stream\bootstrap();
}
