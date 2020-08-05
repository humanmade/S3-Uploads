# Introduction to converting with WebPConvert

**NOTE: This document only applies to the upcoming 2.0 version**

The library is able to convert images to webp using a variety of methods (*gd*, *imagick*, *vips* etc.), which we call "converters". A converter is called like this:

```php
use WebPConvert\Convert\Converters\Gd;

Gd::convert($source, $destination, $options=[], $logger=null);
```

All converters comes with requirements. For example, the *Gd* converter requires that Gd is installed and compiled with webp support. The cloud converters requires an api key. In case the conversion fails, an exception is thrown.

## Insights to the process
If *$logger* is supplied, the converter will log the details of how the conversion process went to that logger. You can for example use the supplied *EchoLogger* to print directly to screen or the *BufferLogger* to collect the log entries. Here is a simple example which prints the process to screen:

```php
use WebPConvert\Convert\Converters\Gd;
use WebPConvert\Loggers\EchoLogger;

Gd::convert($source, $destination, $options=[], new EchoLogger());
```

It will output something like this:

```text
GD Version: 2.2.5
image is true color
Quality set to same as source: 61

Converted image in 20 ms, reducing file size with 34% (went from 12 kb to 8 kb)
```

## The stack converter
When your software is going to be installed on a variety of systems which you do not control, you can try the converters one at the time until success. The converters has been designed to exit quickly when system requirements are not met. To make this task easy, a *Stack* converter has been created.

The stack converter has two special options:

| option                    | description |
| ------------------------- | ----------- |
| converters (array)        | Converters to try (ids or class names, in case you have your own custom converter) |
| converter-options (array) | Extra options for specific converters. |

Alternatively to the converter-options array, you can simply prefix options with the converter id.

I recommend leave the converters array at the default unless you have strong reasons not to. Otherwise you might miss out when new converters are added.

### Example:

```php
<?php
use WebPConvert\Convert\Converters\Stack;

Stack::convert($source, $destination, $options = [    

    // PS: only set converters if you have strong reasons to do so
    'converters' => [
        'cwebp', 'vips', 'imagick', 'gmagick', 'imagemagick', 'graphicsmagick', 'wpc', 'ewww', 'gd'
    ],

    // Any available options can be set here, they dribble down to all converters.
    'metadata' => 'all',

    // To override for specific converter, you can prefix with converter id:
    'cwebp-metadata' => 'exif',

    // This can for example be used for setting ewww api key:
    'ewww-api-key' => 'your-ewww-api-key-here',

    // As an alternative to prefixing, you can use "converter-options" to set a whole bunch of overrides in one go:
    'converter-options' => [        
        'wpc' => [
            'crypt-api-key-in-transfer' => true
            'api-key' => 'my dog is white',
            'api-url' => 'https://example.com/wpc.php',
            'api-version' => 1,
        ],
    ],
], $logger=null);
```

Note: As an alternative to setting the third party credentials in the options, you can set them through constants or environment variables ("WEBPCONVERT_EWWW_API_KEY", "WEBPCONVERT_WPC_API_KEY", "WEBPCONVERT_WPC_API_URL"). Paths to binaries can also be set like that (it is rarely needed to do this): "WEBPCONVERT_CWEBP_PATH", "WEBPCONVERT_GRAPHICSMAGICK_PATH" and WEBPCONVERT_IMAGEMAGICK_PATH"

To set an environment variable in Apache, you can add a line like this in your `.htaccess` or vhost configuration:
```
# Set ewww api key for WebP Convert
SetEnv WEBPCONVERT_EWWW_API_KEY yourVerySecretApiKeyGoesHere

# Set custom path to imagick for WebP Convert
SetEnv WEBPCONVERT_IMAGEMAGICK_PATH /usr/local/bin/magick
```
To set a constant:
```php
define('WEBPCONVERT_IMAGEMAGICK_PATH', '/usr/local/bin/magick');
```


## Configuring the options

### Auto quality
**Q:** What do you get if you convert a low quality jpeg (ie q=50) into a high quality webp (ie q=90) ?\
**A:** You maintain the low quality, but you get a large file`

What should we have done instead? We should have converted with a quality around 50. Of course, quality is still low - we cannot fix that - but it will not be less, *and the converted file will be much smaller*.

As unnecessary large conversions are rarely desirable, this library per default converts jpeg files with the same quality level as the source. This functionality requires that either *imagemagick*, *graphicsmagick* or *imagick* is installed (not necessarily compiled with webp support). When they are, all converters will have the "auto" quality functionality. The *wpc* cloud converter supports auto quality if these are installed on the server that *wpc* is installed on.

How much can be gained? A lot!
The following low quality (q=50) jpeg weighs 54 kb. If this is converted to webp with quality=80, the size of the converted file is 52kb - almost no reduction! With auto, the quality of the webp will be set to 50, and the size will be 34kb. Visually, the results are indistinguable.

![A low quality jpeg](https://raw.githubusercontent.com/rosell-dk/webp-convert/master/docs/v2.0/converting/architecture-q50-w600.jpg)

**Q:** What do you get if you convert an excessively high quality jpeg into an excessively high quality webp?\
**A:** An excessively big file

The size of a webp file grows enormously with the quality setting. For the web however, a quality above 80 is rarely needed. For this reason the library has a per default limits the quality to the value of the *max-quality* option (default: 85).

In case quality detection is unavailable, the quality gets the value of the *default-quality* option (default is 70 for JPEGs and 85 for PNGs).

So, how much can be gained? A lot!
The following excessively high quality jpeg (q=100) weighs 146 kb. Converting it to webp with q=100 results in a 99kb image (this would happen if we had the auto feature, but not the max-quality feature). Converting it to q=85 results in a 40kb image.

![A (too) high quality jpeg](https://raw.githubusercontent.com/rosell-dk/webp-convert/master/docs/v2.0/converting/mouse-q100.jpg)


### Auto selecting between lossless/lossy encoding
WebP files can be encoded using either *lossless* or *lossy* encoding. The JPEG format is lossy and the PNG is lossless. However, this does not mean that you necessarily get the best conversion by always encoding JPEG to lossy and PNG to lossless. With JPEGs it is often the case, as they are usually pictures and pictures usually best encoded as lossy. With PNG it is however a different story, as you often can get a better compression using lossy encoding, also when using high quality level of say 85, which should be enough for the web.

As unnecessary large conversions are rarely desirable, this library per default tries to convert images using both lossy and lossless encoding and automatically selects the smallest. This is controlled using the *encoding* option, which per default is "auto", but can also be set to "lossy" or "lossless".

As an example, the following PNG (231 kb) will be compressed to 156 kb when converting to *lossless* webp. But when converting to *lossy* (quality: 85), it is compressed to merely 68 kb - less than half. (in case you are confused about the combination of lossy and transparency: Yes, you can have both at the same time with webp).

![Dice](https://raw.githubusercontent.com/rosell-dk/webp-convert/master/docs/v2.0/converting/dice.png)

Unless you changed the `near-lossless` option described below, the choice is actually between lossy and *near-lossless*.

Note that *gd* and *ewww* doesn't support this feature. *gd* can only produce lossy, and will simply do that. *ewww* can not be configured to use a certain encoding, but automatically chooses *lossless* encoding for PNGs and lossy for JPEGs.

### Near-lossless
*cwebp* and *vips* supports "near-lossless" mode. Near lossless produces a webp with lossless encoding but adjusts pixel values to help compressibility. The result is a smaller file. The price is described as a minimal impact on the visual quality.

As unnecessary large conversions are rarely desirable, this library per default sets *near-lossless* to 60. To disable near-lossless, set it to 100.

When compressing the image above (231 kb) to lossless, it compressed to 156 kb when near-lossless is set to 100. Setting near-lossless to 60 gets the size down to 110 kb while still looking great.

You can read more about the near-lossless mode [here](https://groups.google.com/a/webmproject.org/forum/#!topic/webp-discuss/0GmxDmlexek)

### Alpha-quality
All converters, except *gd* and *ewww* supports "alpha-quality" option. This allows lossy compressing of the alpha channel.

As unnecessary large conversions are rarely desirable, this library per default sets *alpha-quality* to 85. Set it to 100 to achieve lossless compression of alhpa.

Btw, the image above gets compressed to 68 kb with alpha quality set to 100. Surprisingly, it gets slightly larger (70 kb) with alpha quality set to 85. Setting alpha quality to 50 gets it down to merely 35 kb - about half - while still looking great.

You can read more about the alpha-quality option [here](https://developers.google.com/speed/webp/docs/cwebp)


### PNG og JPEG-specific options.

To have options depending on the image type of the source, you can use the `png` and `jpeg` keys.

The following options mimics the default behaviour:

```php
$options = [
    'png' => [
        'encoding' => 'auto',    /* Try both lossy and lossless and pick smallest */
        'near-lossless' => 60,   /* The level of near-lossless image preprocessing (when trying lossless) */
        'quality' => 85,         /* Quality when trying lossy. It is set high because pngs is often selected to ensure high quality */
    ],
    'jpeg' => [
        'encoding' => 'auto',     /* If you are worried about the longer conversion time, you could set it to "lossy" instead (lossy will often be smaller than lossless for jpegs) */
        'quality' => 'auto',      /* Set to same as jpeg (requires imagick or gmagick extension, not necessarily compiled with webp) */
        'max-quality' => 80,      /* Only relevant if quality is set to "auto" */
        'default-quality' => 75,  /* Fallback quality if quality detection isnt working */
    ]
];
```

You can use it for any option, also the converter specific options.
A use case could for example be to use different converters for png and jpeg:

```php
$options = [
    'png' => [
        'converters' => ['ewww'],
    ],
    'jpeg' => [
        'converters' => ['gd'],
    ]
];
```

## Available options

**All** available options are documented [here](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md).

Here is a quick overview of the few ones discussed here.

| Option            | Default (jpeg)     | Default (png)       | Description                                                                        |
| ----------------- | ------------------ | ------------------- | ---------------------------------------------------------------------------------- |
| quality           | "auto"             | 85                  | See the "Auto quality" section above. |
| max-quality       | 85                 | 85                  | Only relevant for jpegs and when quality is set to "auto".                         |
| default-quality   | 75                 | 85                  |                                                                                    |
| metadata          | "none"             | "none"              | Valid values: "all", "none", "exif", "icc", "xmp".<br><br>Note: Currently only *cwebp* supports all values. *gd* will always remove all metadata. *ewww*, *imagick* and *gmagick* can either strip all, or keep all (they will keep all, unless metadata is set to *none*)       |
| encoding          | "auto"             | "auto"              | See the "Auto selecting between lossless/lossy encoding" section above   |
| jpeg              | -                  | -                   | Array of options which will be merged into the other options when source is a JPEG |
| png               | -                  | -                   | Array of options which will be merged into the other options when source is a PNG  |
| skip              | false              | false               | If true, conversion will be skipped (ie for skipping png conversion for some converters) |


## More info

- The complete api is available [here](https://www.bitwise-it.dk/webp-convert/api/2.0/html/index.xhtml)
- The converters are described in more detail here (for 1.3.9): [docs/v1.3/converting/converters.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/v1.3/converting/converters.md).
- On the github wiki you can find installation instructions for imagick with webp, gd with webp, etc.
- This document is a newly written introduction to the convert api, which has been created as part of the 2.0 release. The old introduction, which was made for 1.3 is available here: [docs/converting/v1.3/convert.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/v1.3/converting/convert.md).
