convert# Migrating to 2.0

## Converting

### Changes in conversion api
While the code have been refactored quite extensively, if you have stuck to `WebPConvert::convert()` and/or `WebPConvert::convertAndServe()`, there is only a few things you need to know.

First and foremost: *`WebPConvert::convert` no longer returns a boolean indicating the result*. So, if conversion fails, an exception is thrown, no matter what the reason is. When migrating, you will probably need to remove some lines of code where you test the result.

Also, a few options has been renamed and a few option defaults has been changed.

#### The options that has been renamed are the following:

- Two converters have changed IDs and class names: The ids that are changed are: *imagickbinary* => *imagemagick* and *gmagickbinary* => *graphicsmagick*
- In *ewww*, the `key` option has been renamed to `api-key` (or [`ewww-api-key`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#ewww-api-key))
- In *wpc*, the `url` option has been renamed to `api-url` (or [`wpc-api-url`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#wpc-api-url))
* In *cwebp*, the [`lossless`] option is now replaced with the new `encoding` option (which is not boolean, but "lossy", "lossless" or ["auto"](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md#auto-selecting-between-losslesslossy-encoding))
* In *cwebp*, the [`autofilter`] option has been renamed to "auto-filter"
- In *gd*, the `skip-pngs` option has been removed and replaced with the general `skip` option and prefixing. So `gd-skip` amounts to the same thing, but notice that Gd no longer skips per default.

#### The option defaults that has been changed are the following:
- the `converters` default now includes the cloud converters (*ewww* and *wpc*) and also two new converters, *vips* and *graphicsmagick*. So it is not necessary to add *ewww* or *wpc* explicitly. Also, when you set options with `converter-options` and point to a converter that isn't in the stack, in 1.3.9, this resulted in the converter automatically being added. This behavior has been removed.
- *gd* no longer skips pngs per default. To make it skip pngs, set `gd-skip` to *true*
- Default quality is now 75 for jpegs and 85 for pngs (it was 75 for both)
- For *cwebp*, the `lossless` has been removed. Use the new `encoding` option instead.
- For *wpc*, default `secret` and `api-key` are now "" (they were "my dog is white")

### New convert options
You might also be interested in the new options available in 2.0:

- Added a syntax for conveniently targeting specific converters. If you for example prefix the "quality" option with "gd-", it will override the "quality" option, but only for gd.
- Certain options can now be set with environment variables too ("EWWW_API_KEY", "WPC_API_KEY" and "WPC_API_URL")
- Added new *vips* converter.
- Added new *graphicsmagick* converter.
- Added new *stack* converter (the stack functionality has been moved into a converter)
- Added [`jpeg`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#jpeg) and [`png`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#png) options
- Added [`alpha-quality`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#alpha-quality) option for *cwebp*, *vips*, *imagick*, *imagemagick* and *graphicsmagick*.
- Added [`auto-filter`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#autofilter) option for *cwebp*, *imagick*, *imagemagick* and the new *vips* converter.
- Added [`encoding`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#encoding) option (lossy | lossless | auto). lossless and auto is supported for *cwebp*, *imagick*, *imagemagick*, *graphicsmagick* and the new *vips* converter.
- Added [`near-lossless`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#near-lossless) option for *cwebp* and *imagemagick*.
- Added [`preset`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#preset) option for *cwebp* and the new *vips* converter.
- Added [`skip`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#skip) option (its general and works for all converters)
- Besides the ones mentioned above, *imagemagick* now also supports [`low-memory`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#low-memory), [`metadata`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#metadata) ("all" or "none") and [`method`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#method). *imagemagick* has become very potent!

## Serving
The classes for serving has also been refactored quite extensively, but again, if you have stuck to `WebPConvert::convertAndServe`, there is only a few things you need to know.

First and foremost, *`WebPConvert::convertAndServe` has been renamed to `WebPConvert::serveConverted()`*. The reason for this change is that it more accurately describes what is happening: A converted file is served. The old name implied that a conversion was always going on, which is not the case (if the file at destination already exists, which is not bigger or older than the source, that file is served directly).

Besides this, there is the following changes in options:

- A new option `convert` has been created for supplying the conversion options. So the conversion options are no longer "mingled" with the serving options, but has its own option.
- Options regarding serving the image are now organized into its own `serve-image` setting, which again has been reorganized.
- A new option `serve-image > headers > cache-control` controls whether to set cache control header (default: false).
- The `fail` option no longer support the "report-as-image" value. It however supports a new value: "throw".
- The `fail-when-original-unavailable` option has been renamed to `fail-when-fail-fails`. In 2.0, the original not being available is no longer the only thing that can cause the fail action to fail &ndash; the library now checks the mime type of the source file and only serves it if it is either png or jpeg.
- The `error-reporting` option has been removed. The reason for it being removed is that it is considered bad practice for a library to mess with error handling. However, *this pushes the responsibility to you*. You should make sure that no warnings ends up in the output, as this will corrupt the image being served. You can for example ensure that by calling `ini_set('display_errors', '0');` or `error_reporting(0);` (or both), or by creating your own error handler.
- The `aboutToServeImageCallBack` option has been removed. You can instead extend the `ServeConvertedWebP` class and override `serveOriginal` and `serveDestination`. You can call the serve method of your extended class, but then you will not have the error handling (the `fail` and `fail-if-fail-fails` options). Too add this, you can call  `ServeConvertedWebPWithErrorHandling::serve` and make sure to override the default of the last argument.
- The `aboutToPerformFailAction` option has been removed. You can instead set `fail` to `throw` and handle the exception in a *catch* clause. Or you can extend the `ServeConvertedWebPWithErrorHandling` class and override the `performFailAction` method.
- The `add-x-header-status` and `add-x-header-options` options have been removed.
- The `require-for-conversion` option has been removed. You must either use with composer or create a simple autoloader (see next section)

## WebP On demand
If you are using the "non-composer" version of webp demand (the one where you only upload two files - `webp-on-demand-1.inc` and `webp-on-demand-2.inc`), you were probably using the `require-for-conversion` option. This option is no longer supported. But you never really needed it in the first place, because the you create and register an autoloader instead:

```php
function autoloader($class) {
    if (strpos($class, 'WebPConvert\\') === 0) {
        require_once __DIR__ . '/webp-on-demand-2.inc';
    }
}
spl_autoload_register('autoloader', true, true);
```
