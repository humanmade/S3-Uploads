# The webp converters

## The converters at a glance
When it comes to webp conversion, there is actually only one library in town: *libwebp* from Google. All conversion methods below ultimately uses that very same library for conversion. This means that it does not matter much, which conversion method you use. Whatever works. There is however one thing to take note of, if you set *quality* to *auto*, and your system cannot determine the quality of the source (this requires imagick or gmagick), and you do not have access to install those, then the only way to get quality-detection is to connect to a *wpc* cloud converter. However, with *cwebp*, you can specify the desired reduction (the *size-in-percentage* option) - at the cost of doubling the conversion time. Read more about those considerations in the API.

Speed-wise, there is too little difference for it to matter, considering that images usually needs to be converted just once. Anyway, here are the results: *cweb* is the fastest (with method=3). *gd* is right behind, merely 3% slower than *cwebp*. *gmagick* are third place, ~8% slower than *cwebp*. *imagick* comes in ~22% slower than *cwebp*. *ewww* depends on connection speed. On my *digital ocean* account, it takes ~2 seconds to upload, convert, and download a tiny image (10 times longer than the local *cwebp*). A 1MB image however only takes ~4.5 seconds to upload, convert and download (1.5 seconds longer). A 2 MB image takes ~5 seconds to convert (only 16% longer than my *cwebp*). The *ewww* thus converts at a very decent speeds. Probably faster than your average shared host. If multiple big images needs to be converted at the same time, *ewww* will probably perform much better than the local converters.

[`cwebp`](#cwebp) works by executing the *cwebp* binary from Google, which is build upon the *libwebp* (also from Google). That library is actually the only library in town for generating webp images, which means that the other conversion methods ultimately uses that very same library. Which again means that the results using the different methods are very similar. However, with *cwebp*, we have more parameters to tweak than with the rest. We for example have the *method* option, which controls the trade off between encoding speed and the compressed file size and quality. Setting this to max, we can squeeze the images a few percent extra - without loosing quality (the converter is still pretty fast, so in most cases it is probably worth it).

Of course, as we here have to call a binary directly, *cwebp* requires the *exec* function to be enabled, and that the webserver user is allowed to execute the `cwebp` binary (either at known system locations, or one of the precompiled binaries, that comes with this library).

[`vips`](#vips) (**new in 2.0**) works by using the vips extension, if available. Vips is great! It offers many webp options, it is fast and installation is easier than imagick and gd, as it does not need to be configured for webp support.

[`imagick`](#imagick) does not support any special webp options, but is at least able to strip all metadata, if metadata is set to none. Imagick has a very nice feature - that it is able to detect the quality of a jpeg file. This enables it to automatically use same quality for destination as for source, which eliminates the risk of setting quality higher for the destination than for source (the result of that is that the file size gets higher, but the quality remains the same). As the other converters lends this capability from Imagick, this is however no reason for using Imagick rather than the other converters. Requirements: Imagick PHP extension compiled with WebP support

[`gmagick`](#gmagick) uses the *gmagick* extension. It is very similar to *imagick*. Requirements:  Gmagick PHP extension compiled with WebP support.

[`gd`](#gd) uses the *Gd* extension to do the conversion. The *Gd* extension is pretty common, so the main feature of this converter is that it may work out of the box. It does not support any webp options, and does not support stripping metadata. Requirements: GD PHP extension compiled with WebP support.

[`wpc`](#wpc) is an open source cloud service for converting images to webp. To use it, you must either install [webp-convert-cloud-service](https://github.com/rosell-dk/webp-convert-cloud-service) directly on a remote server, or install the Wordpress plugin, [WebP Express](https://github.com/rosell-dk/webp-express) in Wordpress. Btw: Beware that upload limits will prevent conversion of big images. The converter checks your *php.ini* settings and abandons upload right away, if an image is larger than your *upload_max_filesize* or your *post_max_size* setting. Requirements: Access to a running service. The service can be installed  [directly](https://github.com/rosell-dk/webp-convert-cloud-service) or by using [this Wordpress plugin](https://wordpress.org/plugins/webp-express/)

[`ewww`](#ewww) is also a cloud service. Not free, but cheap enough to be considered *practically* free. It supports lossless encoding, but this cannot be controlled. *Ewww* always uses lossy encoding for jpeg and lossless for png. For jpegs this is usually a good choice, however, many pngs are compressed better using lossy encoding. As lossless cannot be controlled, the "lossless:auto" option cannot be used for automatically trying both lossy and lossless and picking the smallest file. Also, unfortunately, *ewww* does not support quality=auto, like *wpc*, and it does not support *size-in-percentage* like *cwebp*, either. I have requested such features, and he is considering... As with *wpc*, beware of upload limits. Requirements: A key to the *EWWW Image Optimizer* cloud service. Can be purchaced [here](https://ewww.io/plans/)

[`stack`](#stack) takes a stack of converters and tries it from the top, until success. The main convert method actually calls this converter. Stacks within stacks are supported (not really needed, though).


**Summary:**

|                                            | cwebp     | vips   | imagick / gmagick | imagickbinary | gd        | ewww   |
| ------------------------------------------ | --------- | ------ | ----------------- | ------------- | --------- | ------ |
| supports lossless encoding ?               | yes       | yes    | no                | no            | no        | yes    |
| supports lossless auto ?                   | yes       | yes    | no                | no            | no        | no     |
| supports near-lossless ?                   | yes       | yes    | no                | no            | no        | ?      |
| supports metadata stripping / preserving   | yes       | yes    | yes               | no            | no        | ?      |
| supports setting alpha quality             | no        | yes    | no                | no            | no        | no     |
| supports fixed quality (for lossy)         | yes       | yes    | yes               | yes           | yes       | yes    |
| supports auto quality without help         | no        | no     | yes               | yes           | no        | no     |



*WebPConvert* currently supports the following converters:

| Converter                            | Method                                           | Requirements                                       |
| ------------------------------------ | ------------------------------------------------ | -------------------------------------------------- |
| [`cwebp`](#cwebp)                    | Calls `cwebp` binary directly                    | `exec()` function *and* that the webserver user has permission to run `cwebp` binary |
| [`vips`](#vips) (new in 2.0)         | Vips extension                                   | Vips extension                                     |
| [`imagick`](#imagick)                | Imagick extension (`ImageMagick` wrapper)        | Imagick PHP extension compiled with WebP support   |
| [`gmagick`](#gmagick)                | Gmagick extension (`ImageMagick` wrapper)        | Gmagick PHP extension compiled with WebP support   |
| [`gd`](#gd)                          | GD Graphics (Draw) extension (`LibGD` wrapper)   | GD PHP extension compiled with WebP support        |
| [`imagickbinary`](#imagickbinary)    | Calls imagick binary directly                    | exec() and imagick installed and compiled with WebP support   |
| [`wpc`](#wpc)                        | Connects to an open source cloud service                 | Access to a running service. The service can be installed  [directly](https://github.com/rosell-dk/webp-convert-cloud-service) or by using [this Wordpress plugin](https://wordpress.org/plugins/webp-express/).
| [`ewww`](#ewww)                      | Connects to *EWWW Image Optimizer* cloud service | Purchasing a key                                   |

## Installation
Instructions regarding getting the individual converters to work are [on the wiki](https://github.com/rosell-dk/webp-convert/wiki)

## cwebp
<table>
  <tr><th>Requirements</th><td><code>exec()</code> function and that the webserver has permission to run `cwebp` binary (either found in system path, or a precompiled version supplied with this library)</td></tr>
  <tr><th>Performance</th><td>~40-120ms to convert a 40kb image (depending on *method* option)</td></tr>
  <tr><th>Reliability</th><td>No problems detected so far!</td></tr>
  <tr><th>Availability</th><td>According to ewww docs, requirements are met on surprisingly many webhosts. Look <a href="https://docs.ewww.io/article/43-supported-web-hosts">here</a> for a list</td></tr>
  <tr><th>General options supported</th><td>All (`quality`, `metadata`, `lossless`)</td></tr>
  <tr><th>Extra options</th><td>`method` (0-6)<br>`use-nice` (boolean)<br>`try-common-system-paths` (boolean)<br> `try-supplied-binary-for-os` (boolean)<br>`autofilter` (boolean)<br>`size-in-percentage` (number / null)<br>`command-line-options` (string)<br>`low-memory` (boolean)</td></tr>
</table>

[cwebp](https://developers.google.com/speed/webp/docs/cwebp) is a WebP conversion command line converter released by Google. Our implementation ships with precompiled binaries for Linux, FreeBSD, WinNT, Darwin and SunOS. If however a cwebp binary is found in a usual location, that binary will be preferred. It is executed with [exec()](http://php.net/manual/en/function.exec.php).

In more detail, the implementation does this:
- It is tested whether cwebp is available in a common system path (eg `/usr/bin/cwebp`, ..)
- If not, then supplied binary is selected from `Converters/Binaries` (according to OS) - after validating checksum
- Command-line options are generated from the options
- If [`nice`]( https://en.wikipedia.org/wiki/Nice_(Unix)) command is found on host, binary is executed with low priority in order to save system resources
- Permissions of the generated file are set to be the same as parent folder

### Cwebp options

The following options are supported, besides the general options (such as quality, lossless etc):

| Option                     | Type                      | Default                    |
| -------------------------- | ------------------------- | -------------------------- |
| autofilter                 | boolean                   | false                      |
| command-line-options       | string                    | ''                         |
| low-memory                 | boolean                   | false                      |
| method                     | integer (0-6)             | 6                          |
| near-lossless              | integer (0-100)           | 60                         |
| size-in-percentage         | integer (0-100) (or null) | null                       |
| rel-path-to-precompiled-binaries | string              | './Binaries'               |
| size-in-percentage         | number (or null)          | is_null                    |
| try-common-system-paths    | boolean                   | true                       |
| try-supplied-binary-for-os | boolean                   | true                       |
| use-nice                   | boolean                   | false                      |

Descriptions (only of some of the options):

#### the `autofilter` option
Turns auto-filter on. This algorithm will spend additional time optimizing the filtering strength to reach a well-balanced quality. Unfortunately, it is extremely expensive in terms of computation. It takes about 5-10 times longer to do a conversion. A 1MB picture which perhaps typically takes about 2 seconds to convert, will takes about 15 seconds to convert with auto-filter. So in most cases, you will want to leave this at its default, which is off.

#### the `command-line-options` option
This allows you to set any parameter available for cwebp in the same way as you would do when executing *cwebp*. You could ie set it to "-sharpness 5 -mt -crop 10 10 40 40". Read more about all the available parameters in [the docs](https://developers.google.com/speed/webp/docs/cwebp)

#### the `low-memory` option
Reduce memory usage of lossy encoding at the cost of ~30% longer encoding time and marginally larger output size. Default: `false`. Read more in [the docs](https://developers.google.com/speed/webp/docs/cwebp). Default: *false*

#### The `method` option
This parameter controls the trade off between encoding speed and the compressed file size and quality. Possible values range from 0 to 6. 0 is fastest. 6 results in best quality.

#### the `near-lossless` option
Specify the level of near-lossless image preprocessing. This option adjusts pixel values to help compressibility, but has minimal impact on the visual quality. It triggers lossless compression mode automatically. The range is 0 (maximum preprocessing) to 100 (no preprocessing). The typical value is around 60. Read more [here](https://groups.google.com/a/webmproject.org/forum/#!topic/webp-discuss/0GmxDmlexek). Default: 60

#### The `size-in-percentage` option
This option sets the file size, *cwebp* should aim for, in percentage of the original. If you for example set it to *45*, and the source file is 100 kb, *cwebp* will try to create a file with size 45 kb (we use the `-size` option). This is an excellent alternative to the "quality:auto" option. If the quality detection isn't working on your system (and you do not have the rights to install imagick or gmagick), you should consider using this options instead. *Cwebp* is generally able to create webp files with the same quality at about 45% the size. So *45* would be a good choice. The option overrides the quality option. And note that it slows down the conversion - it takes about 2.5 times longer to do a conversion this way, than when quality is specified. Default is *off* (null)


#### final words on cwebp
The implementation is based on the work of Shane Bishop for his plugin, [EWWW Image Optimizer](https://ewww.io). Thanks for letting us do that!

See [the wiki](https://github.com/rosell-dk/webp-convert/wiki/Installing-cwebp---using-official-precompilations) for instructions regarding installing cwebp or using official precompilations.

## vips
<table>
  <tr><th>Requirements</th><td>Vips extension</td></tr>
  <tr><th>Performance</th><td>Great</td></tr>
  <tr><th>Reliability</th><td>No problems detected so far!</td></tr>
  <tr><th>Availability</th><td>Not that widespread yet, but gaining popularity</td></tr>
  <tr><th>General options supported</th><td>All (`quality`, `metadata`, `lossless`)</td></tr>
  <tr><th>Extra options</th><td>`smart-subsample`(boolean)<br>`alpha-quality`(0-100)<br>`near-lossless` (0-100)<br> `preset` (0-6)</td></tr>
</table>

For installation instructions, go [here](https://github.com/libvips/php-vips-ext).

The options are described [here](https://jcupitt.github.io/libvips/API/current/VipsForeignSave.html#vips-webpsave)

*near-lossless* is however an integer (0-100), in order to have the option behave like in cwebp.



## wpc
*WebPConvert Cloud Service*

<table>
  <tr><th>Requirements</th><td>Access to a server with [webp-convert-cloud-service](https://github.com/rosell-dk/webp-convert-cloud-service) installed, <code>cURL</code> and PHP >= 5.5.0</td></tr>
  <tr><th>Performance</th><td>Depends on the server where [webp-convert-cloud-service](https://github.com/rosell-dk/webp-convert-cloud-service) is set up, and the speed of internet connections. But perhaps ~1000ms to convert a 40kb image</td></tr>
  <tr><th>Reliability</th><td>Great (depends on the reliability on the server where it is set up)</td></tr>
  <tr><th>Availability</th><td>Should work on <em>almost</em> any webhost</td></tr>
  <tr><th>General options supported</th><td>All (`quality`, `metadata`, `lossless`)</td></tr>
  <tr><th>Extra options (old api)</th><td>`url`, `secret`</td></tr>
  <tr><th>Extra options (new api)</th><td>`url`, `api-version`, `api-key`, `crypt-api-key-in-transfer`</td></tr>
</table>

[wpc](https://github.com/rosell-dk/webp-convert-cloud-service) is an open source cloud service. You do not buy a key, you set it up on a server, or you set up [the Wordpress plugin](https://wordpress.org/plugins/webp-express/). As WebPConvert Cloud Service itself is based on WebPConvert, all options are supported.

To use it, you need to set the `converter-options` (to add url etc).

#### Example, where api-key is not crypted, on new API:

```php
WebPConvert::convert($source, $destination, [
    'max-quality' => 80,
    'converters' => ['cwebp', 'wpc'],
    'converter-options' => [
        'wpc' => [
            'api-version' => 1,     /* from wpc release 1.0.0 */
            'url' => 'http://example.com/wpc.php',
            'api-key' => 'my dog is white',
            'crypt-api-key-in-transfer' => false
        ]
    ]    
));
```

#### Example, where api-key is crypted:

```php

WebPConvert::convert($source, $destination, [
    'max-quality' => 80,
    'converters' => ['cwebp', 'wpc'],
    'converter-options' => [
        'wpc' => [
            'api-version' => 1,
            'url' => 'https://example.com/wpc.php',
            'api-key' => 'my dog is white',
            'crypt-api-key-in-transfer' => true
        ],
    ]
));
```

In 2.0, you can alternatively set the api key and urls through through the *WPC_API_KEY* and *WPC_API_URL* environment variables. This is a safer place to store it.

To set an environment variable in Apache, you can use the `SetEnv` directory. Ie, place something like the following in your virtual host / or .htaccess file (replace the key with the one you purchased!)

```
SetEnv WPC_API_KEY my-dog-is-dashed
SetEnv WPC_API_URL https://wpc.example.com/wpc.php
```


#### Example, old API:

```php
WebPConvert::convert($source, $destination, [
    'max-quality' => 80,
    'converters' => ['cwebp', 'wpc'],
    'converter-options' => [
        'wpc' => [
            'url' => 'https://example.com/wpc.php',
            'secret' => 'my dog is white',
        ],
    ]
));
```


## ewww

<table>
  <tr><th>Requirements</th><td>Valid EWWW Image Optimizer <a href="https://ewww.io/plans/">API key</a>, <code>cURL</code> and PHP >= 5.5.0</td></tr>
  <tr><th>Performance</th><td>~1300ms to convert a 40kb image</td></tr>
  <tr><th>Reliability</th><td>Great (but, as with any cloud service, there is a risk of downtime)</td></tr>
  <tr><th>Availability</th><td>Should work on <em>almost</em> any webhost</td></tr>
  <tr><th>General options supported</th><td>`quality`, `metadata` (partly)</td></tr>
  <tr><th>Extra options</th><td>`key`</td></tr>
</table>

EWWW Image Optimizer is a very cheap cloud service for optimizing images. After purchasing an API key, add the converter in the `extra-converters` option, with `key` set to the key. Be aware that the `key` should be stored safely to avoid exploitation - preferably in the environment, ie with  [dotenv](https://github.com/vlucas/phpdotenv).

The EWWW api doesn't support the `lossless` option, but it does automatically convert PNG's losslessly. Metadata is either all or none. If you have set it to something else than one of these, all metadata will be preserved.

In more detail, the implementation does this:
- Validates that there is a key, and that `curl` extension is working
- Validates the key, using the [/verify/ endpoint](https://ewww.io/api/) (in order to [protect the EWWW service from unnecessary file uploads, when key has expired](https://github.com/rosell-dk/webp-convert/issues/38))
- Converts, using the [/ endpoint](https://ewww.io/api/).

<details>
<summary><strong>Roadmap</strong> 👁</summary>

The converter could be improved by using `fsockopen` when `cURL` is not available - which is extremely rare. PHP >= 5.5.0 is also widely available (PHP 5.4.0 reached end of life [more than two years ago!](http://php.net/supported-versions.php)).
</details>

#### Example:

```php
WebPConvert::convert($source, $destination, [
    'max-quality' => 80,
    'converters' => ['gd', 'ewww'],
    'converter-options' => [
        'ewww' => [
            'key' => 'your-api-key-here'
        ],
    ]
));
```
In 2.0, you can alternatively set the api key by through the *EWWW_API_KEY* environment variable. This is a safer place to store it.

To set an environment variable in Apache, you can use the `SetEnv` directory. Ie, place something like the following in your virtual host / or .htaccess file (replace the key with the one you purchased!)

```
	SetEnv EWWW_API_KEY sP3LyPpsKWZy8CVBTYegzEGN6VsKKKKA
```

## gd

<table>
  <tr><th>Requirements</th><td>GD PHP extension and PHP >= 5.5.0 (compiled with WebP support)</td></tr>
  <tr><th>Performance</th><td>~30ms to convert a 40kb image</td></tr>
  <tr><th>Reliability</th><td>Not sure - I have experienced corrupted images, but cannot reproduce</td></tr>
  <tr><th>Availability</th><td>Unfortunately, according to <a href="https://stackoverflow.com/questions/25248382/how-to-create-a-webp-image-in-php">this link</a>, WebP support on shared hosts is rare.</td></tr>
  <tr><th>General options supported</th><td>`quality`</td></tr>
  <tr><th>Extra options</th><td>`skip-pngs`</td></tr>
</table>

[imagewebp](http://php.net/manual/en/function.imagewebp.php) is a function that comes with PHP (>5.5.0), *provided* that PHP has been compiled with WebP support.

`gd` neither supports copying metadata nor exposes any WebP options. Lacking the option to set lossless encoding results in poor encoding of PNGs - the filesize is generally much larger than the original. For this reason, PNG conversion is *disabled* by default, but it can be enabled my setting `skip-pngs` option to `false`.

Installaition instructions are [available in the wiki](https://github.com/rosell-dk/webp-convert/wiki/Installing-Gd-extension).

<details>
<summary><strong>Known bugs</strong> 👁</summary>
Due to a [bug](https://bugs.php.net/bug.php?id=66590), some versions sometimes created corrupted images. That bug can however easily be fixed in PHP (fix was released [here](https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files)). However, I have experienced corrupted images *anyway* (but cannot reproduce that bug). So use this converter with caution. The corrupted images look completely transparent in Google Chrome, but have the correct size.
</details>

## imagick

<table>
  <tr><th>Requirements</th><td>Imagick PHP extension (compiled with WebP support)</td></tr>
  <tr><th>Quality</th><td>Poor. [See this issue]( https://github.com/rosell-dk/webp-convert/issues/43)</td></tr>
  <tr><th>General options supported</th><td>`quality`</td></tr>
  <tr><th>Extra options</th><td>None</td></tr>
  <tr><th>Performance</th><td>~20-320ms to convert a 40kb image</td></tr>
  <tr><th>Reliability</th><td>No problems detected so far</td></tr>
  <tr><th>Availability</th><td>Probably only available on few shared hosts (if any)</td></tr>
</table>

WebP conversion with `imagick` is fast and [exposes many WebP options](http://www.imagemagick.org/script/webp.php). Unfortunately, WebP support for the `imagick` extension is pretty uncommon. At least not on the systems I have tried (Ubuntu 16.04 and Ubuntu 17.04). But if installed, it works great and has several WebP options.

See [this page](https://github.com/rosell-dk/webp-convert/wiki/Installing-Imagick-extension) in the Wiki for instructions on installing the extension.

## imagickbinary
<table>
  <tr><th>Requirements</th><td><code>exec()</code> function and that imagick is installed on webserver, compiled with webp support</td></tr>
  <tr><th>Performance</th><td>just fine</td></tr>
  <tr><th>Reliability</th><td>No problems detected so far!</td></tr>
  <tr><th>Availability</th><td>Not sure</td></tr>
  <tr><th>General options supported</th><td>`quality`</td></tr>
  <tr><th>Extra options</th><td>`use-nice` (boolean)</td></tr>
</table>

This converter tryes to execute `convert source.jpg webp:destination.jpg.webp`.

## stack

<table>
  <tr><th>General options supported</th><td>all (passed to the converters in the stack )</td></tr>
  <tr><th>Extra options</th><td>`converters` (array) and `converter-options` (array)</td></tr>
</table>

Stack implements the functionality you know from `WebPConvert::convert`. In fact, all `WebPConvert::convert` does is to call `Stack::convert($source, $destination, $options, $logger);`

It has two special options: `converters` and `converter-options`. You can read about those in `docs/api/convert.md`
