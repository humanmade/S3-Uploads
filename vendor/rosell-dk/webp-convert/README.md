# WebP Convert

[![Latest Stable Version](https://img.shields.io/packagist/v/rosell-dk/webp-convert.svg?style=flat-square)](https://packagist.org/packages/rosell-dk/webp-convert)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg?style=flat-square)](https://php.net)
[![Build Status](https://travis-ci.org/rosell-dk/webp-convert.png?branch=master)](https://travis-ci.org/rosell-dk/webp-convert)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/rosell-dk/webp-convert.svg?style=flat-square)](https://scrutinizer-ci.com/g/rosell-dk/webp-convert/code-structure/master)
[![Quality Score](https://img.shields.io/scrutinizer/g/rosell-dk/webp-convert.svg?style=flat-square)](https://scrutinizer-ci.com/g/rosell-dk/webp-convert/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://github.com/rosell-dk/webp-convert/blob/master/LICENSE)

*Convert JPEG & PNG to WebP with PHP*

This library enables you to do webp conversion with PHP. It supports an abundance of methods for converting and automatically selects the most capable of these that is available on the system.

The library can convert using the following methods:
- *cwebp* (executing [cwebp](https://developers.google.com/speed/webp/docs/cwebp) binary using an `exec` call)
- *vips* (using [Vips PHP extension](https://github.com/libvips/php-vips-ext))
- *imagick* (using [Imagick PHP extension](https://github.com/Imagick/imagick))
- *gmagick* (using [Gmagick PHP extension](https://www.php.net/manual/en/book.gmagick.php))
- *imagemagick* (executing [imagemagick](https://imagemagick.org/index.php) binary using an `exec` call)
- *graphicsmagick* (executing [graphicsmagick](http://www.graphicsmagick.org/) binary using an `exec` call)
- *wpc* (using [WebPConvert Cloud Service](https://github.com/rosell-dk/webp-convert-cloud-service/) - an open source webp converter for PHP - based on this library)
- *ewwww* (using the [ewww](https://ewww.io/plans/) cloud converter (1 USD startup and then free webp conversion))
- *gd* (using the [Gd PHP extension](https://www.php.net/manual/en/book.image.php))

In addition to converting, the library also has a method for *serving* converted images, and we have instructions here on how to set up a solution for automatically serving webp images to browsers that supports webp.

## Installation
Require the library with *Composer*, like this:

```text
composer require rosell-dk/webp-convert
```

## Converting images
Here is a minimal example of converting using the *WebPConvert::convert* method:

```php
<?php

// Initialise your autoloader (this example is using Composer)
require 'vendor/autoload.php';

use WebPConvert\WebPConvert;

$source = __DIR__ . '/logo.jpg';
$destination = $source . '.webp';
$options = [];
WebPConvert::convert($source, $destination, $options);
```

The *WebPConvert::convert* method comes with a bunch of options. The following introduction is a *must-read*:
[docs/v2.0/converting/introduction-for-converting.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md).

If you are migrating from 1.3.9, [read this](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/migrating-to-2.0.md)

## Serving converted images
The *WebPConvert::serveConverted* method tries to serve a converted image. If there already is an image at the destination, it will take that, unless the original is newer or smaller. If the method cannot serve a converted image, it will serve original image, a 404, or whatever the 'fail' option is set to. It also adds *X-WebP-Convert-Log* headers, which provides insight into what happened.

Example (version 2.0):
```php
<?php
require 'vendor/autoload.php';
use WebPConvert\WebPConvert;

$source = __DIR__ . '/logo.jpg';
$destination = $source . '.webp';

WebPConvert::serveConverted($source, $destination, [
    'fail' => 'original',     // If failure, serve the original image (source). Other options include 'throw', '404' and 'report'
    //'show-report' => true,  // Generates a report instead of serving an image

    'serve-image' => [
        'headers' => [
            'cache-control' => true,
            'vary-accept' => true,
            // other headers can be toggled...
        ],
        'cache-control-header' => 'max-age=2',
    ],

    'convert' => [
        // all convert option can be entered here (ie "quality")
    ],
]);
```

The following introduction is a *must-read* (for 2.0):
[docs/v2.0/serving/introduction-for-serving.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/serving/introduction-for-serving.md).

The old introduction (for 1.3.9) is available here: [docs/v1.3/serving/convert-and-serve.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/v1.3/serving/convert-and-serve.md)


## WebP on demand
The library can be used to create a *WebP On Demand* solution, which automatically serves WebP images instead of jpeg/pngs for browsers that supports WebP. To set this up, follow what's described  [in this tutorial (not updated for 2.0 yet)](https://github.com/rosell-dk/webp-convert/blob/master/docs/v1.3/webp-on-demand/webp-on-demand.md).


## Projects using WebP Convert

### CMS plugins using WebP Convert
This library is used as the engine to provide webp conversions to a handful of platforms. Hopefully this list will be growing over time. Currently there are plugins / extensions / modules / whatever the term is for the following CMS'es (ordered by [market share](https://w3techs.com/technologies/overview/content_management/all)):

- [Wordpress](https://github.com/rosell-dk/webp-express)
- [Contao](https://github.com/postyou/contao-webp-bundle)
- [Kirby](https://github.com/S1SYPHOS/kirby-webp)

### Other projects using WebP Convert

- [webp-convert-cloud-service](https://github.com/rosell-dk/webp-convert-cloud-service)
A cloud service based on WebPConvert

- [webp-convert-concat](https://github.com/rosell-dk/webp-convert-concat)
The webp-convert library and its dependents as a single PHP file (or two)

## Supporting WebP Convert
Bread on the table don't come for free, even though this library does, and always will. I enjoy developing this, and supporting you guys, but I kind of need the bread too. Please make it possible for me to have both:

[Become a backer or sponsor on Patreon](https://www.patreon.com/rosell).
