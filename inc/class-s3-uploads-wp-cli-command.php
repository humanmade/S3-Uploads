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

	/**
	 * Create an AWS IAM user for S3 Uploads to user
	 *
	 * @subcommand create-iam-user
	 * @synopsis --admin-key=<key> --admin-secret=<secret>
	 */
	public function create_iam_user( $args, $args_assoc ) {

		require_once dirname( __FILE__ ) . '/aws-sdk/aws-autoloader.php';

		$username = 's3-uploads-' . sanitize_title( home_url() );

		try {
			$iam = Aws\Common\Aws::factory( array( 'key' => $args_assoc['admin-key'], 'secret' => $args_assoc['admin-secret'] ) )->get( 'iam' );

			$iam->createUser( array(
				'UserName' => $username
			));

			$credentials = $iam->createAccessKey( array(
				'UserName' => $username
			))['AccessKey'];

			$iam->putUserPolicy( array(
				'UserName' => $username,
				'PolicyName' => $username . '-policy',
				'PolicyDocument' => $this->get_iam_policy()
			));

		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI::success( sprintf( 'Created new IAM user %s. The Access Credentials are displayed below', $username ) );

		WP_CLI\Utils\format_items( 'table', array( (object) $credentials ), array( 'AccessKeyId', 'SecretAccessKey' ) );

	}

	private function get_iam_policy() {
		return '{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "Stmt1392016154000",
      "Effect": "Allow",
      "Action": [
        "s3:AbortMultipartUpload",
        "s3:DeleteObject",
        "s3:GetBucketAcl",
        "s3:GetBucketLocation",
        "s3:GetBucketPolicy",
        "s3:GetObject",
        "s3:GetObjectAcl",
        "s3:ListBucket",
        "s3:ListBucketMultipartUploads",
        "s3:ListMultipartUploadParts",
        "s3:PutObject",
        "s3:PutObjectAcl"
      ],
      "Resource": [
        "arn:aws:s3:::' . S3_UPLOADS_BUCKET . '/*"
      ]
    }
  ]
}';
	}

	/**
	 * Create AWS IAM Policy that S3 Uploads requires
	 *
	 * It's typically not a good idea to use access keys that have full access to your S3 account,
	 * as if the keys are compromised through the WordPress site somehow, you don't
	 * want to give full control via those keys.
	 *
	 * @subcommand generate-iam-policy
	 */
	public function generate_iam_policy() {

		WP_Cli::print_value( $this->get_iam_policy() );

	}
}

WP_CLI::add_command( 's3-uploads', 'S3_Uploads_WP_CLI_Command' );