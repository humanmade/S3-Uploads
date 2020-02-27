# API: The WebPConvert::convertAndServe() method

*NOTE:* In 2.0, the method is renamed to *serveConverted* ("convertAndServe" was implying that a conversion was always made, but the method simply serves destination if it exists and is smaller and newer than source)

The method tries to serve a converted image. If destination already exists, the already converted image will be served. Unless the original is newer or smaller. If the method fails, it will serve original image, a 404, or whatever the 'fail' option is set to.

**WebPConvert::convertAndServe($source, $destination, $options)**

| Parameter        | Type    | Description                                                         |
| ---------------- | ------- | ------------------------------------------------------------------- |
| `$source`        | String  | Absolute path to source image (only forward slashes allowed)        |
| `$destination`   | String  | Absolute path to converted image (only forward slashes allowed)     |
| `$options`       | Array   | Array of options (see below)                                        |

## The *$options* argument
The options argument is a named array. Besides the options described below, you can also use any options that the *convert* method takes (if a fresh convertion needs to be created, this method will call the *convert* method and hand over the options argument)

### *convert*
Conversion options, handed over to the convert method, in case a conversion needs to be made. The convert options are documented [here](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md).

### *fail*
Indicate what to do, in case of normal conversion failure.
Default value: *"original"*

| Possible values   | Meaning                                         |
| ----------------- | ----------------------------------------------- |
| "serve-original"  | Serve the original image.                       |
| "404"             | Serve 404 status (not found)                    |
| "report-as-image" | Serve an image with text explaining the problem |
| "report"          | Serve a textual report explaining the problem   |

### *fail-when-original-unavailable*
Possible values: Same as above, except that "original" is not an option.
Default value: *"404"*

### *show-report*
Produce a report rather than serve an image.  
Default value: *false*

### *reconvert*
Force a conversion, discarding existing converted image (if any).
Default value: *false*

### *serve-original*
Forces serving original image. This will skip conversion.
Default value: *false*

### *add-x-header-status*
When set to *true*, a *X-WebP-Convert-Status* header will be added describing how things went.  
Default value: *true*

Depending on how things goes, the header will be set to one of the following:
- "Failed (missing source argument)"
- "Failed (source not found)""
- "Failed (missing destination argument)"
- "Reporting..."
- "Serving original image (was explicitly told to)"
- "Serving original image - because it is smaller than the converted!"
- "Serving freshly converted image (the original had changed)"
- "Serving existing converted image"
- "Converting image (handed over to WebPConvertAndServe)"
- "Serving freshly converted image"
- "Failed (could not convert image)"

### *add-vary-header*
Add a "Vary: Accept" header when an image is served. Experimental.  
Default value: *true*

### *add-content-type-header*
Add a "Content-Type" header
Default value: *true*
If set, a *Content-Type* header will be added. It will be set to "image/webp" if a converted image is served, "image/jpeg" or "image/png", if the original is served or "image/gif", if an error message is served (as image). You can set it to false when debugging (to check if any errors are being outputted)

### *add-last-modified-header*
Add a "Last-Modified" header
Default value: *true*
If set, a *Last-Modified* header will be added. When a cached image is served, it will be set to the modified time of the converted file. When a fresh image is served, it is set to current time.

### *cache-control-header*
Specify a cache control header, which will be served when caching is appropriate.
Default value: "public, max-age=86400" (1 day)
Caching is "deemed appropriate", when destination is served, source is served, because it is lighter or a fresh conversion is made, due to there not being any converted image at the destination yet. Caching is not deemed appropriate when something fails, a report is requested, or the *reconvert* option have been set. Note: in version 1.3.2 and below, the *serve-original* option also prevented caching, but it no longer does. previous In those cases, standard headers will be used for preventing caching.
For your convenience, here is a little table:

| duration | max-age          |
| -------- | ---------------- |
| 1 second | max-age=1        |
| 1 minute | max-age=60       |
| 1 hour   | max-age=3600     |
| 1 day    | max-age=86400    |
| 1 week   | max-age=604800   |
| 1 month  | max-age=2592000  |
| 1 year   | max-age=31536000 |

To learn about the options for the Cache-Control header, go [here](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control)

### *error-reporting*
Set error reporting
Allowed values: *"auto"*, *"dont-mess"*, *true*, *false*
Default value: *"auto"*

If set to true, error reporting will be turned on, like this:
```
    error_reporting(E_ALL);
    ini_set('display_errors', 'On');
```

If set to false, error reporting will be turned off, like this:
```
    error_reporting(0);
    ini_set('display_errors', 'Off');
```
If set to "auto", errors will be turned off, unless the `show-report` option is set, in which case errors will be turned off.
If set to "dont-mess", error reporting will not be touched.

### *aboutToServeImageCallBack*
This callback is called right before response headers and image is served. This is a great chance to adding headers. You can stop the image and the headers from being served by returning *false*.

**Arguments:**
The first argument to the callback contains a string that tells what is about to be served. It can be 'fresh-conversion', 'destination' or 'source'.

The second argument tells you why that is served. It can be one of the following:
for 'source':
- "explicitly-told-to"     (when the "serve-original" option is set)
- "source-lighter"         (when original image is actually smaller than the converted)

for 'fresh-conversion':
- "explicitly-told-to"     (when the "reconvert" option is set)
- "source-modified"        (when source is newer than existing)
- "no-existing"            (when there is no existing at the destination)

for 'destination':
- "no-reason-not-to"       (it is lighter than source, its not older, and we were not told to do otherwise)

Example of callback:
```
function aboutToServeImageCallBack($servingWhat, $whyServingThis, $obj)
{
    echo 'about to serve: ' . $servingWhat . '<br>';
    echo 'Why? - because: ' . $whyServingThis;
    return false;   // Do not serve! (this also prevents any response headers from being added)
}
```

### *aboutToPerformFailActionCallback*
This callback is called right before doing the action specified in the `fail` option, or the  `fail-when-original-unavailable` option. You can stop the fail action from being executod by returning *false*.

Documentation by example:
```
function aboutToPerformFailActionCallback($errorTitle, $errorDescription, $actionAboutToBeTaken, $serveConvertedObj)
{
    echo '<h1>' . $errorTitle . '</h1>';
    echo $errorDescription;
    if (actionAboutToBeTaken == '404') {
        // handle 404 differently than webp-convert would
        $protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'HTTP/1.0';
        $serveConvertedObj->header($protocol . " 404 Not Found. We take this very seriously. Heads will roll.");

        return false;   // stop webp-convert from doing what it would do
    }

}
```

### *require-for-conversion*
If set, makes the library 'require in' a file just before doing an actual conversion with `ConvertAndServe::convertAndServe()`. This is not needed for composer projects, as composer takes care of autoloading classes when needed.
Default value: *null*
