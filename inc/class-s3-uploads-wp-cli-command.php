<?php

class S3_Uploads_WP_CLI_Command extends WP_CLI_Command {

	/**
	 * @subcommand migrate-attachments
	 * @synposis [--delete-local]
	 */
	public function migrate_attachments_to_s3( $args, $args_assoc ) {

		$attachments = new WP_Query( array(
			'post_type' => 'attachment',
			'posts_per_page' => -1,
			'post_status' => 'all'
		));

		WP_CLI::line( sprintf( 'Attempting to move %d attachments to S3', $attachments->found_posts ) );

		foreach ( $attachments->posts as $attachment ) {

			$this->migrate_attachment_to_s3( array( $attachment->ID ), $args_assoc );
		}

		WP_CLI::success( 'Moved all attachment to S3. If you wish to update references in your database run: ' );
		WP_CLI::line( '' );

		$old_upload_dir = S3_Uploads::get_instance()->get_original_upload_dir();
		$upload_dir = wp_upload_dir();

		WP_CLI::Line( sprintf( 'wp search-replace "%s" "%s"', $old_upload_dir['baseurl'], $upload_dir['baseurl'] ) );
	}

	/**
	 * Migrate a single attachment's files to S3
	 * 
	 * @subcommand migrate-attachment
	 * @synposis <attachment-id> [--delete-local]
	 */
	public function migrate_attachment_to_s3( $args, $args_assoc ) {

		$old_upload_dir = S3_Uploads::get_instance()->get_original_upload_dir();
		$upload_dir = wp_upload_dir();

		$files = array( get_post_meta( $args[0], '_wp_attached_file', true ) );

		$meta_data = wp_get_attachment_metadata( $args[0] );

		if ( ! empty( $meta_data['sizes'] ) ) {
			foreach ( $meta_data['sizes'] as $file ) {
				$files[] = path_join( dirname( $meta_data['file'] ), $file['file'] );
			}
		}

		foreach ( $files as $file ) {
			if ( file_exists( $path = $old_upload_dir['basedir'] . '/' . $file ) ) {

				if ( ! copy( $path, $upload_dir['basedir'] . '/' . $file ) ) {
					WP_CLI::line( sprintf( 'Failed to moved %s to S3', $file ) );
				} else {
					if ( ! empty( $args_assoc['delete-local'] ) ) {
						unlink( $path );
					}
					WP_CLI::success( sprintf( 'Moved file %s to S3', $file ) );

				}

			} else {
				WP_CLI::line( sprintf( 'Already moved to %s S3', $file ) );
			}
		}

	}

}

WP_CLI::add_command( 's3-uploads', 'S3_Uploads_WP_CLI_Command' );