# WebP On Demand without composer

For your convenience, the library has been cooked down to two files: *webp-on-demand-1.inc* and *webp-on-demand-2.inc*. The second one is loaded when the first one decides it needs to do a conversion (and not simply serve existing image).

## Installing

### 1. Copy the latest build files into your website
The build files are distributed [here](https://github.com/rosell-dk/webp-convert-concat/tree/master/build). Open the "latest" folder and copy *webp-on-demand-1.inc* and *webp-on-demand-2.inc* into your website. They can be located wherever you like.

### 2. Create a *webp-on-demand.php*

Create a file *webp-on-demand.php*, and place it in webroot, or where-ever you like in you web-application.

Here is a minimal example to get started with:

```php
<?php
// To start with, lets display any errors.
// - this will reveal if you entered wrong paths
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Once you got it working, make sure that PHP warnings are not send to the output
// - this will corrupt the image
// For example, you can do it by commenting out the lines below:
// error_reporting(0);
// ini_set("display_errors", 0);

use WebPConvert\WebPConvert;

require 'webp-on-demand-1.inc';

function webpconvert_autoloader($class) {
    if (strpos($class, 'WebPConvert\\') === 0) {
        require_once __DIR__ . '/webp-on-demand-2.inc';
    }
}
spl_autoload_register('webpconvert_autoloader', true, true);

$source = $_GET['source'];            // Absolute file path to source file. Comes from the .htaccess
$destination = $source . '.webp';     // Store the converted images besides the original images (other options are available!)

$options = [

    // UNCOMMENT NEXT LINE, WHEN YOU ARE UP AND RUNNING!
    'show-report' => true             // Show a conversion report instead of serving the converted image.

    // More options available!
    // https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md
    // https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/serving/introduction-for-serving.md
];
WebPConvert::serveConverted($source, $destination, $options);
```

Note that the procedure has changed in 2.0. In 1.x, the library supported a `require-for-conversion` option, but this option has been removed in 2.0. It was not really needed, as the example above illustrates.

### 3. Continue the regular install instructions from step 3
[Click here to continue...](https://github.com/rosell-dk/webp-on-demand#3-add-redirect-rules)
