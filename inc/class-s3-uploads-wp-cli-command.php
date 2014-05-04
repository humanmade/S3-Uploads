<?php

class S3_Uploads_WP_CLI_Command extends WP_CLI_Command {

	/**
	 * @subcommand migrate-attachments
	 * @synopsis [--delete-local]
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
	 * @synopsis <attachment-id> [--delete-local]
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
	 * @synopsis --admin-key=<key> --admin-secret=<secret> [--username=<username>]
	 */
	public function create_iam_user( $args, $args_assoc ) {

		require_once dirname( __FILE__ ) . '/aws-sdk/aws-autoloader.php';

		if ( empty( $args_assoc['username'] ) ) {
			$username = 's3-uploads-' . sanitize_title( home_url() );
		} else {
			$username = $args_assoc['username'];
		}

		try {
			$iam = Aws\Common\Aws::factory( array( 'key' => $args_assoc['admin-key'], 'secret' => $args_assoc['admin-secret'] ) )->get( 'iam' );

			$iam->createUser( array(
				'UserName' => $username
			));

			$credentials = $iam->createAccessKey( array(
				'UserName' => $username
			));

			$credentials = $credentials['AccessKey'];

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

		$bucket = strtok( S3_UPLOADS_BUCKET, '/' );

		$path = null;

		if ( strpos( S3_UPLOADS_BUCKET, '/' ) ) {
			$path = str_replace( strtok( S3_UPLOADS_BUCKET, '/' ) . '/', '', S3_UPLOADS_BUCKET );
		}


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
    },
    {
      "Sid": "AllowRootAndHomeListingOfBucket",
      "Action": ["s3:ListBucket"],
      "Effect": "Allow",
      "Resource": ["arn:aws:s3:::' . $bucket . '"],
      "Condition":{"StringLike":{"s3:prefix":["' . ( $path ? $path . '/' : '' ) . '*"]}}
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

	/**
	 * List files in the S3 bukcet
	 *
	 * @synopsis [<path>]
	 */
	public function ls( $args ) {

		$s3 = S3_Uploads::get_instance()->s3();

		$prefix = '';

		if ( strpos( S3_UPLOADS_BUCKET, '/' ) ) {
			$prefix = trailingslashit( str_replace( strtok( S3_UPLOADS_BUCKET, '/' ) . '/', '', S3_UPLOADS_BUCKET ) );
		}

		if ( isset( $args[0] ) ) {
			$prefix .= trailingslashit( ltrim( $args[0], '/' ) );
		}

		try {
			$objects = $s3->getIterator('ListObjects', array(
				'Bucket' => strtok( S3_UPLOADS_BUCKET, '/' ),
				'Prefix' => $prefix
			));
			foreach ($objects as $object) {
				WP_CLI::line( str_replace( $prefix, '', $object['Key'] ) );
			}
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

	}

	/**
	 * Copy files to / from the uploads directory. Use s3://bucket/location for S3
	 *
	 * @synopsis <from> <to>
	 */
	public function cp( $args ) {

		$from = $args[0];
		$to = $args[1];

		if ( is_dir( $from ) ) {
			$this->recurse_copy( $from, $to );
		} else {
			copy( $from, $to );
		}

		WP_CLI::success( sprintf( 'Completed copy from %s to %s', $from, $to ) );
	}

	/**
	 * Upload a directory to S3
	 * 
	 * @subcommand upload-directory
	 * @synopsis <from> [<to>] [--sync]
	 */
	public function upload_directory( $args, $args_assoc ) {

		$from = $args[0];
		$to = '';
		if ( isset( $args[1] ) ) {
			$to = $args[1];
		}

		$s3 = S3_Uploads::get_instance()->s3();
		$bucket = strtok( S3_UPLOADS_BUCKET, '/' );
		$prefix = '';

		if ( strpos( S3_UPLOADS_BUCKET, '/' ) ) {
			$prefix = trailingslashit( str_replace( strtok( S3_UPLOADS_BUCKET, '/' ) . '/', '', S3_UPLOADS_BUCKET ) );
		}

		require_once dirname( __FILE__ ) . '/class-s3-uploads-uploadsyncbuilder.php';
		require_once dirname( __FILE__ ) . '/class-s3-uploads-changed-files-iterator.php';

		try {
			$s3->uploadDirectory( 
				$from, 
				$bucket, 
				$prefix . $to, 
				array(
					'debug' => true, 
					'params' => array( 'ACL' => 'public-read' ),
					'builder' => new S3_Uploads_UploadSyncBuilder(),
					'force' => empty( $args_assoc['sync'] )
					) 
				); 
		} catch( Exception $e ) { 
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Delete files from S3
	 *
	 * @synopsis <path> [--regex=<regex>]
	 */
	public function rm( $args, $args_assoc ) {

		$s3 = S3_Uploads::get_instance()->s3();

		$prefix = '';
		$regex = isset( $args_assoc['regex'] ) ? $args_assoc['regex'] : '';

		if ( strpos( S3_UPLOADS_BUCKET, '/' ) ) {
			$prefix = trailingslashit( str_replace( strtok( S3_UPLOADS_BUCKET, '/' ) . '/', '', S3_UPLOADS_BUCKET ) );
		}

		if ( isset( $args[0] ) ) {
			$prefix .= ltrim( $args[0], '/' );


			if ( strpos( $args[0], '.' ) === false ) {
				$prefix = trailingslashit( $prefix );
			}
		}

		try {
			$objects = $s3->deleteMatchingObjects(
				strtok( S3_UPLOADS_BUCKET, '/' ),
				$prefix,
				$regex,
				array( 'before_delete', function() {
					WP_CLI::line( sprintf( 'Deleting file' ) );
				})
			);

		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI::success( sprintf( 'Successfully deleted %s', $prefix ) );
	}

	private function recurse_copy($src,$dst) {
		$dir = opendir($src);
		@mkdir($dst);
		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($src . '/' . $file) ) {
					$this->recurse_copy($src . '/' . $file,$dst . '/' . $file);
				}
				else {
					WP_CLI::line( sprintf( 'Copying from %s to %s', $src . '/' . $file, $dst . '/' . $file ) );
					copy($src . '/' . $file,$dst . '/' . $file);
				}
			}
		}
		closedir($dir);
	}
}

WP_CLI::add_command( 's3-uploads', 'S3_Uploads_WP_CLI_Command' );