S3-Uploads
==========

WordPress plugin to store uploads on S3. S3-Uploads aims to be a lightweight "drop-in" for storing uploads on Amazon S3 instead of the local filesystem.

It's focused on providing a highly robust S3 interface with no "bells and whistles", WP-Admin UI or much otherwise. It comes with some helpful WP-CLI commands for generating IAM users, listing files on S3 and Migrating your existing library to S3.


Getting Set Up
==========

Once you have `git clone`d the repo, or added it as a Git Submodule, add the following constants to your `wp-config.php`:

```PHP
define( 'S3_UPLOADS_BUCKET', 'my-bucket' );
define( 'S3_UPLOADS_KEY', '' );
define( 'S3_UPLOADS_SECRET', '' );
```

You'll want to create a new IAM user for the S3-Uploads plugin, so you are not using admin level access keys on your site. S3-Uploads can create the IAM user for you and asign the correct permissions.

```
wp s3-uploads create-iam-user --admin-key=<key> --admin-secret=<secret>
```

This will provide you with a new Access Key and Secret Key which you can configure S3-Uploads with. Paste the values in the `wp-config.php`. That's it! You're good to go.


Migrating your Media to S3
==========

S3-Uploads can migrate your existing media library to S3. Once you have S3-Uploads up and running, use the following WP-CLI command:

```
wp s3-uploads migrate-attachments [--delete-local]
```

By default, S3-Uploads will keep your files locally just incase something goes wrong, but you can delete with the `--delete-local` flag.


Listing files on S3
==========

S3-Uploads comes with a WP-CLI command for listing files in the S3 bucket for debugging etc.

```
wp s3-uploads [<path>]
```