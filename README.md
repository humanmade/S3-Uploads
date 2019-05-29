<table width="100%">
	<tr>
		<td align="left" width="70">
			<strong>S3 Uploads</strong><br />
			Lightweight "drop-in" for storing WordPress uploads on Amazon S3 instead of the local filesystem.
		</td>
		<td align="right" width="20%">
			<a href="https://travis-ci.org/humanmade/S3-Uploads">
				<img src="https://travis-ci.org/humanmade/S3-Uploads.svg?branch=master" alt="Build status">
			</a>
			<a href="http://codecov.io/github/humanmade/S3-Uploads?branch=master">
				<img src="http://codecov.io/github/humanmade/S3-Uploads/coverage.svg?branch=master" alt="Coverage via codecov.io" />
			</a>
		</td>
	</tr>
	<tr>
		<td>
			A <strong><a href="https://hmn.md/">Human Made</a></strong> project. Maintained by @joehoyle.
		</td>
		<td align="center">
			<img src="https://hmn.md/content/themes/hmnmd/assets/images/hm-logo.svg" width="100" />
		</td>
	</tr>
</table>

S3 is a WordPress plugin to store uploads on S3. S3-Uploads aims to be a lightweight "drop-in" for storing uploads on Amazon S3 instead of the local filesystem.

It's focused on providing a highly robust S3 interface with no "bells and whistles", WP-Admin UI or much otherwise. It comes with some helpful WP-CLI commands for generating IAM users, listing files on S3 and Migrating your existing library to S3.


Getting Set Up
==========

**Install Using Composer**

```
composer require humanmade/s3-uploads
```

**Install Manually**

If you do not use Composer to manage plugins or other dependencies, you can install the plugin manually. Download the `manual-install.zip` file from the [Releases page](https://github.com/humanmade/S3-Uploads/releases) and extract the ZIP file to your `plugins` directory.

You can also `git clone` this repository, and run `composer install` in the plugin folder to pull in it's dependencies.

---

Once you've installed the plugin, add the following constants to your `wp-config.php`:

```PHP
define( 'S3_UPLOADS_BUCKET', 'my-bucket' );
define( 'S3_UPLOADS_KEY', '' );
define( 'S3_UPLOADS_SECRET', '' );
define( 'S3_UPLOADS_REGION', '' ); // the s3 bucket region (excluding the rest of the URL)
```
Please refer to this region list http://docs.aws.amazon.com/general/latest/gr/rande.html#s3_region for the S3_UPLOADS_REGION values.

Use of path prefix after the bucket name is allowed and is optional. For example, if you want to upload all files to 'my-folder' inside a bucket called 'my-bucket', you can use:

```PHP
define( 'S3_UPLOADS_BUCKET', 'my-bucket/my-folder' );
```

You must then enable the plugin. To do this via WP-CLI use command:

```
wp plugin activate S3-Uploads
```

The plugin name must match the directory you have cloned S3 Uploads into;
If you're using Composer, use
```
wp plugin activate s3-uploads
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

If you want to create your IAM user yourself, or attach the necessary permissions to an existing user, you can output the policy via `wp s3-uploads generate-iam-policy`


Listing files on S3
==========

S3-Uploads comes with a WP-CLI command for listing files in the S3 bucket for debugging etc.

```
wp s3-uploads ls [<path>]
```

Uploading files to S3
==========

If you have an existing media library with attachment files, use the below command to copy them all to S3 from local disk.

```
wp s3-uploads upload-directory <from> <to> [--verbose]
```

Passing `--sync` will only upload files that are newer in `<from>` or that don't exist on S3 already. Use `--dry-run` to test.

For example, to migrate your whole uploads directory to S3, you'd run:

```
wp s3-uploads upload-directory /path/to/uploads/ uploads
```

There is also an all purpose `cp` command for arbitrary copying to and from S3.

```
wp s3-uploads cp <from> <to>
```

Note: as either `<from>` or `<to>` can be S3 or local locations, you must specify the full S3 location via `s3://mybucket/mydirectory` for example `cp ./test.txt s3://mybucket/test.txt`.

Cache Control
==========

You can define the default HTTP `Cache-Control` header for uploaded media using the
following constant:

```PHP
define( 'S3_UPLOADS_HTTP_CACHE_CONTROL', 30 * 24 * 60 * 60 );
	// will expire in 30 days time
```

You can also configure the `Expires` header using the `S3_UPLOADS_HTTP_EXPIRES` constant
For instance if you wanted to set an asset to effectively not expire, you could
set the Expires header way off in the future.  For example:

```PHP
define( 'S3_UPLOADS_HTTP_EXPIRES', gmdate( 'D, d M Y H:i:s', time() + (10 * 365 * 24 * 60 * 60) ) .' GMT' );
	// will expire in 10 years time
```

Default Behaviour
==========

As S3 Uploads is a plug and play plugin, activating it will start rewriting image URLs to S3, and also put
new uploads on S3. Sometimes this isn't required behaviour as a site owner may want to upload a large
amount of media to S3 using the `wp-cli` commands before enabling S3 Uploads to direct all uploads requests
to S3. In this case one can define the `S3_UPLOADS_AUTOENABLE` to `false`. For example, place the following
in your `wp-config.php`:

```PHP
define( 'S3_UPLOADS_AUTOENABLE', false );
```

To then enable S3 Uploads rewriting, use the wp-cli command: `wp s3-uploads enable` / `wp s3-uploads disable`
to toggle the behaviour.

URL Rewrites
=======
By default, S3 Uploads will use the canonical S3 URIs for referencing the uploads, i.e. `[bucket name].s3.amazonaws.com/uploads/[file path]`. If you want to use another URL to serve the images from (for instance, if you [wish to use S3 as an origin for CloudFlare](https://support.cloudflare.com/hc/en-us/articles/200168926-How-do-I-use-CloudFlare-with-Amazon-s-S3-Service-)), you should define `S3_UPLOADS_BUCKET_URL` in your `wp-config.php`:

```PHP
// Define the base bucket URL (without trailing slash)
define( 'S3_UPLOADS_BUCKET_URL', 'https://your.origin.url.example/path' );
```
S3 Uploads' URL rewriting feature can be disabled if the current website does not require it, nginx proxy to s3 etc. In this case the plugin will only upload files to the S3 bucket.
```PHP
// disable URL rewriting alltogether
define( 'S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL', true );
```

S3 Object Permissions
=======

The object permission of files uploaded to S3 by this plugin can be controlled by setting the `S3_UPLOADS_OBJECT_ACL`
constant. The default setting if not specified is `public-read` to allow objects to be read by anyone. If you don't
want the uploads to be publicly readable then you can define `S3_UPLOADS_OBJECT_ACL` as one of `private` or `authenticated-read`
in you wp-config file:

```PHP
// Set the S3 object permission to private
define('S3_UPLOADS_OBJECT_ACL', 'private');
```

For more information on S3 permissions please see the Amazon S3 permissions documentation.

Custom Endpoints
=======

Depending on your requirements you may wish to use an alternative S3 compatible object storage system such as Minio, Ceph,
Digital Ocean Spaces, Scaleway and others.

You can configure the endpoint by adding the following code to a file in the `wp-content/mu-plugins/` directory, for example `wp-content/mu-plugins/s3-endpoint.php`:

```php
<?php
// Filter S3 Uploads params.
add_filter( 's3_uploads_s3_client_params', function ( $params ) {
	$params['endpoint'] = 'https://your.endpoint.com';
	$params['use_path_style_endpoint'] = true;
	$params['debug'] = false; // Set to true if uploads are failing.
	return $params;
} );
```

Temporary Session Tokens
=======

If your S3 access is configured to require a temporary session token in addition to the access key and secret, you should configure the credentials using the following code:

```php
// Filter S3 Uploads params.
add_filter( 's3_uploads_s3_client_params', function ( $params ) {
	$params['credentials']['token'] = 'your session token here';
	return $params;
} );
```

Offline Development
=======

While it's possible to use S3 Uploads for local development (this is actually a nice way to not have to sync all uploads from production to development),
if you want to develop offline you have a couple of options.

1. Just disable the S3 Uploads plugin in your development environment.
2. Define the `S3_UPLOADS_USE_LOCAL` constant with the plugin active.

Option 2 will allow you to run the S3 Uploads plugin for production parity purposes, it will essentially mock
Amazon S3 with a local stream wrapper and actually store the uploads in your WP Upload Dir `/s3/`.

Credits
=======
Created by Human Made for high volume and large-scale sites. We run S3 Uploads on sites with millions of monthly page views, and thousands of sites.

Written and maintained by [Joe Hoyle](https://github.com/joehoyle). Thanks to all our [contributors](https://github.com/humanmade/S3-Uploads/graphs/contributors).

Interested in joining in on the fun? [Join us, and become human!](https://hmn.md/is/hiring/)
