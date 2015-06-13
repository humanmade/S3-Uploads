S3-Uploads
==========

[![Build Status](https://travis-ci.org/humanmade/S3-Uploads.svg?branch=master)](https://travis-ci.org/humanmade/S3-Uploads)
[![codecov.io](http://codecov.io/github/humanmade/S3-Uploads/coverage.svg?branch=master)](http://codecov.io/github/humanmade/S3-Uploads?branch=master)

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

The next thing that you should do is to verify your setup. You can do this using the `verify` command
like so:

```
wp s3-uploads verify
```

You'll want to create a new IAM user for the S3-Uploads plugin, so you are not using admin level access keys on your site. S3-Uploads can create the IAM user for you and asign the correct permissions.

```
wp s3-uploads create-iam-user --admin-key=<key> --admin-secret=<secret>
```

This will provide you with a new Access Key and Secret Key which you can configure S3-Uploads with. Paste the values in the `wp-config.php`. Once you have migrated your media to S3 with any of the below methods, you'll want to enable S3 Uploads: `wp s3-uploads enable`.

If you want to create your IAM user yourself, or attach the neccessary permissions to an existing user, you can output the policy via `wp s3-uploads generate-iam-policy`

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
wp s3-uploads ls [<path>]
```

Uploading files to S3
==========

Sometimes the `wp s3-uploads migrate-attachments` command may not be enough to migrate your uploads to S3, as that will only move attachment files to S3. If you are using any plugins that store data in uploads, you'll want to upload the whole `uploads` directory.

```
wp s3-uploads upload-directory <from> <to> [--sync] [--dry-run]
```

Passing `--sync` will only upload files that are newer in `<from>` or that don't exist on S3 already. Use `--dry-run` to test.

There is also an all purpose `cp` command for arbitrary copying to and from S3.

```
wp s3-uploads cp <from> <to>
```

Note: as either `<from>` or `<to>` can be S3 or local locations, you must speficy the full S3 location via `s3://mybucket/mydirectory` for example `cp ./test.txt s3://mybucket/test.txt`.

Cache Control
==========

You can define the default HTTP `Cache-Control` header for uploaded media using the
following constant:

```PHP
define( 'S3_UPLOADS_CACHE_CONTROL', 30 * 24 * 60 * 60 );
	// will expire in 30 days time
```

You can also configure the `Expires` header using the `S3_UPLOADS_EXPIRES` constant
For instance if you wanted to set an asset to effectively not expire, you could
set the Expires header way off in the future.  For example:

```PHP
define( 'S3_UPLOADS_EXPIRES', gmdate( 'D, d M Y H:i:s', time() + (10 * 365 * 24 * 60 * 60) ) .' GMT' );
	// will expire in 10 years time
```
