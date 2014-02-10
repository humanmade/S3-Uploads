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
	}

	/**
	 * Migrate a single attachment's files to S3
	 * 
	 * @subcommand migrate-attachment
	 * @synposis <attachment-id> [--delete-local]
	 */
	public function migrate_attachment_to_s3( $args, $args_assoc ) {

		$old_upload_dir = S3_Uploads::get_instance()->get_original_upload_dir();

		$file = get_post_meta( $args[0], '_wp_attached_file', true );

		if ( file_exists( $path = $old_upload_dir['basedir'] . '/' . $file ) ) {

			copy( $path, get_attached_file( $args[0] ) );
			WP_CLI::success( sprintf( 'Moved attachment %d to S3', $args[0] ) ); 
		} else {
			WP_CLI::line( 'Already moved to S3' );
		}
	}
}

WP_CLI::add_command( 's3-uploads', 'S3_Uploads_WP_CLI_Command' );