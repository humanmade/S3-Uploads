# WebP On Demand without composer

For your convenience, the library has been cooked down to two files: *webp-on-demand-1.inc* and *webp-on-demand-2.inc*. The second one is loaded when the first one decides it needs to do a conversion (and not simply serve existing image).

## Installing

### 1. Copy the latest build files into your website
Copy *webp-on-demand-1.inc* and *webp-on-demand-2.inc* from the *build* folder into your website (in 2.0, they are located in "src-build"). They can be located wherever you like.

### 2. Create a *webp-on-demand.php*

Create a file *webp-on-demand.php*, and place it in webroot, or where-ever you like in you web-application.

Here is a minimal example to get started with. Note that this example only works in version 1.x. In 2.0, the `require-for-conversion` option has been removed, so the [procedure is different](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/webp-on-demand/without-composer.md).

```php
<?php
// To start with, lets display any errors.
// You can later comment these out
error_reporting(E_ALL);
ini_set("display_errors", 1);

require 'webp-on-demand-1.inc';

use WebPConvert\WebPConvert;

$source = $_GET['source'];            // Absolute file path to source file. Comes from the .htaccess
$destination = $source . '.webp';     // Store the converted images besides the original images (other options are available!)

$options = [

    // Tell where to find the webp-convert-and-serve library, which will
    // be dynamically loaded, if need be.
    'require-for-conversion' => 'webp-on-demand-2.inc',

    // UNCOMMENT NEXT LINE, WHEN YOU ARE UP AND RUNNING!
    'show-report' => true             // Show a conversion report instead of serving the converted image.

    // More options available!
];
WebPConvert::convertAndServe($source, $destination, $options);
```

### 3. Continue the main install instructions from step 3
[Click here to continue...](https://github.com/rosell-dk/webp-on-demand#3-add-redirect-rules)
