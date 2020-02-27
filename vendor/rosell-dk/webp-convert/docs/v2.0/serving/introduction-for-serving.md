# Introduction to serving converted WebP files with WebPConvert

**NOTE: This document only applies to the upcoming 2.0 version**

The classes for serving first and foremost helps you handle the cached files intelligently (not serving them if they are larger or older than the original). It also provides a convenient way to deal with conversion failures and setting headers.


In the following example, all available *serve* options are explicitly set to their default values.

```php
use WebPConvert\WebPConvert;

WebPConvert::serveConverted($source, $destination, [

    // failure handling
    'fail'                 => 'original',   // ('original' | 404' | 'throw' | 'report')
    'fail-when-fail-fails' => 'throw',      // ('original' | 404' | 'throw' | 'report')

    // options influencing the decision process of what to be served
    'reconvert' => false,         // if true, existing (cached) image will be discarded
    'serve-original' => false,    // if true, the original image will be served rather than the converted
    'show-report' => false,       // if true, a report will be output rather than the raw image

    // warning handling
    'suppress-warnings' => true,            // if you set to false, make sure that warnings are not echoed out!

    // options when serving an image (be it the webp or the original, if the original is smaller than the webp)
    'serve-image' => [
        'headers' => [
            'cache-control' => true,
            'content-length' => true,
            'content-type' => true,
            'expires' => false,
            'last-modified' => true,
            'vary-accept' => false
        ],
        'cache-control-header' => 'public, max-age=31536000',
    ],

    // redirect tweak
    'redirect-to-self-instead-of-serving' => false,  // if true, a redirect will be issues rather than serving

    'convert' => [
        // options for converting goes here
        'quality' => 'auto',
    ]
]);
```

## Failure handling
The `fail` option gives you an easy way to handle errors. Setting it to 'original' tells it to handle errors by serving the original file instead (*$source*). This could be a good choice on production servers. On development servers, 'throw' might be a good option. It simply rethrows the exception that was thrown by *WebPConvert::convert()*. '404' could also be an option, but it has the weakness that it will probably only be discovered by real persons seeing a missing image.

The fail action might fail too. For example, if it is set to 'original' and the failure is that the original file doesn't exist. Or, more delicately, it may have a wrong mime type - our serve method will not let itself be tricked into serving *exe* files as the 'original'. Anyway, you can control what to do when fail fails using the *fail-when-fail-fails* option. If that fails too, the original exception is thrown. The fun stops there, there is no "fail-when-fail-when-fail-fails" option to customize this.

The failure handling is implemented as an extra layer. You can bypass it by calling `WebPConvert\Serve\ServeConvertedWebP::serve()` directly. Doing that will give the same result as if you set `fail` to 'throw'.

## Options influencing the decision process
The default process is like this:

1. Is there a file at the destination? If not, trigger conversion
2. Is the destination older than the source? If yes, delete destination and trigger conversion
3. Serve the smallest file (destination or source)

You can influence the process with the following options:

*reconvert*
If you set *reconvert* to true, the destination and conversion is triggered (between step 1 and 2)

*serve-original*
If you set *serve-original* to true, process will take its cause from (1) to (2) and then end with source being served.

*show-report*
If you set `show-report`, the process is skipped entirely, and instead a report is generated of how a fresh conversion using the supplied options goes.

## Headers
Leaving errors and reports out of account for a moment, the *WebPConvert::serveConverted()* ultimately has two possible outcomes: Either a converted image is served or - if smaller - the source image. If the source is to be served, its mime type will be detected in order to make sure it is an image and to be able to set the content type header. Either way, the actual serving is passed to `Serve\ServeFile::serve`. The main purpose of this class is to add/set headers.

#### *Cache-Control* and *Expires* headers
Default behavior is to neither set the *Cache-Control* nor the *Expires* header. Once you are on production, you will probably want to turn these on. The default is btw one year (31536000 seconds). I recommend the following for production:

```
'serve-image' => [
    'headers' => [
        'cache-control' => true,        
        'expires' => false,
    ],
    'cache-control-header' => 'public, max-age=31536000',
],
```

The value for the *Expires* header is calculated from "max-age" found in the *cache-control-header* option and the time of the request. The result is an absolute time, ie "Expires: Thu, 07 May 2020 07:02:37 GMT". As most browsers now supports the *Cache-Control* header, *from a performance perspective*, there is no need to also add the expires header. However, some tools complains if you don't (gtmetrix allegedly), and there is no harm in adding both headers. More on this discussion [[here]](https://github.com/rosell-dk/webp-convert/issues/126).

#### *Vary: Accept* header
This library can be used as part of a solution that serves webp files to browsers that supports it, while serving the original file to browsers that does not *on the same URL*. Such a solution typically inspects the *Accept* request header in order to determine if the client supports webp or not. Thus, the response will *vary* along with the "Accept" header and the world (and proxies) should be informed about this, so they don't end up serving cached webps to browsers that does not support it. To add the "Vary: Accept" header, simply set the *serve-image >  headers > vary-accept* option to true.

#### *Last-Modified* header
The Last-Modified header is also used for caching purposes. You should leave that setting on, unless you set it by other means. You control it with the *serve-image >  headers > last-modified* option.

#### *Content-Type* header
The *Content-Type* header tells browsers what they are receiving. This is important information and you should leave the *serve-image >  headers > content-type* option at its default (true), unless you set it by other means.

When the outcome is to serve a webp, the header will be set to: "Content-Type: image/webp". When the original is to be served, the library will try to detect the mime type of the file and set the content type accordingly. The [image-mime-type-guesser](https://github.com/rosell-dk/image-mime-type-guesser) library is used for that.

#### *Content-Length* header
The *Content-Length* header tells browsers the length of the content. According to [the specs](https://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.13), it should be set unless it is prohibited by rules in [section 4.4](https://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.4). In that section we learn that it should not be set when the *Transfer-Encoding* header is set (which it often is, to "chunked"). However, no harm done, because it also says that clients should ignore the header in case *Transfer-Encoding* is set. From this I concluded that it makes sense to default the *serve-image >  headers > content-length* to true. I might however change this in case I should learn that the header could be problematic in some way. So if you decided you want it, do not rely on the default, but set it to *true*. See discussion on this subject [here](https://stackoverflow.com/questions/3854842/content-length-header-with-head-requests/3854983#3854983).

#### *X-WebP-Convert-Log* headers
The serve method adds *X-WebP-Convert-Log* headers in order to let you know what went on.
For example, if there is no converted image and conversion was successful, the following headers will be sent:

```
X-WebP-Convert-Log: Converting (there were no file at destination)
X-WebP-Convert-Log: Serving converted file
```

On the next call (presuming the webp has not been deleted), no conversion is needed and you should simply see:
```
X-WebP-Convert-Log: Serving converted file
```

But say that the first conversion actually failed. In case you have permission problems, the output could be:
```
X-WebP-Convert-Log: Converting (there were no file at destination)
X-WebP-Convert-Log: Failed creating folder. Check the permissions!
X-WebP-Convert-Log: Performing fail action: original
```

In case the problem is that the conversion failed, you could see the following:
```
X-WebP-Convert-Log: Converting (there were no file at destination)
X-WebP-Convert-Log: None of the converters in the stack are operational
X-WebP-Convert-Log: Performing fail action: original
```

If you need more info about the conversion process in order to learn why the converters aren't working, enable the *show-report* option.

As a last example, say you have supplied a non-existing file as source and `fail` is set to "original" (which will also fail). Result:
```
X-WebP-Convert-Log: Source file was not found
X-WebP-Convert-Log: Performing fail action: original
X-WebP-Convert-Log: Performing fail action: throw
```

## The redirect tweak (will be available in 2.3.0)
There are cases where serving the image directly with PHP isn't optimal.

One case is WP Engine. Even though webp-convert adds a Vary:Accept header, the header is not present in the response on WP Engine. It is somehow overwritten by the caching machinery and set to Vary:Accept-Encoding, Cookie.

If however rules have been set up to redirect images directly to existing webps, one can overcome the problem by redirecting the image request back to itself rather than serving the webp directly.

You can achieve this by setting the *redirect-to-self-instead-of-serving* option to true.

Beware of risk of an endless redirect loop. Such loop will happen if the redirection to existing webp rules aren't set up correctly. To prevent this, it is recommended that you only set the option to true after checking that the destination file does not exist. But note that this check does not completely prevent such loops occurring when redirection to existing rules are missing - as the 302 redirect could get cached (it does that on WP Engine). So bottom line: Only use this feature when you have server rules set up for redirecting images to their corresponding webp images (for client that supports webp) - *and you are certain that these rules works*.

## More info

- The complete api is available [here](https://www.bitwise-it.dk/webp-convert/api/2.0/html/index.xhtml)
