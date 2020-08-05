# image-mime-type-guesser

[![Latest Stable Version](https://img.shields.io/packagist/v/rosell-dk/image-mime-type-guesser.svg?style=flat-square)](https://packagist.org/packages/rosell-dk/image-mime-type-guesser)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg?style=flat-square)](https://php.net)
[![Build Status](https://img.shields.io/travis/rosell-dk/image-mime-type-guesser/master.svg?style=flat-square)](https://travis-ci.org/rosell-dk/image-mime-type-guesser)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/rosell-dk/image-mime-type-guesser.svg?style=flat-square)](https://scrutinizer-ci.com/g/rosell-dk/image-mime-type-guesser/code-structure/master)
[![Quality Score](https://img.shields.io/scrutinizer/g/rosell-dk/image-mime-type-guesser.svg?style=flat-square)](https://scrutinizer-ci.com/g/rosell-dk/image-mime-type-guesser/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://github.com/rosell-dk/image-mime-type-guesser/blob/master/LICENSE)


*Detect / guess mime type of an image*

Do you need to determine if a file is an image?<br>
And perhaps you also want to know the mime type of the image?<br>
Do you basically need [exif_imagetype](https://www.php.net/manual/en/function.exif-imagetype.php), but which also works when PHP is compiled without exif?

&ndash; You come to the right library.

Ok, actually the library cannot offer mime type detection for images which works *on all platforms*, but it can try a whole stack of methods and optionally fall back to guess from the file extension.

The stack of detect methods are currently (and in that order):
-  [`exif_imagetype`](https://www.php.net/manual/en/function.exif-imagetype.php) *(PHP 4 >= 4.3.0, PHP 5, PHP 7) - unless PHP is compiled without exif*
-  [`finfo`](https://www.php.net/manual/en/class.finfo.php) *(PHP 5 >= 5.3.0, PHP 7, PECL fileinfo >= 0.1.0) - requires fileinfo extension to be enabled*
-  Our custom 4 byte sniffer (based on [this code](http://phil.lavin.me.uk/2011/12/php-accurately-detecting-the-type-of-a-file/)) *(PHP 4, PHP 5, PHP 7) - only detects png, gif and jpeg*
-  [`getimagesize`](https://www.php.net/getimagesize) *(PHP 4, PHP 5, PHP 7)*
-  [`mime_content_type`](https://www.php.net/manual/en/function.mime-content-type.php) *(PHP 4 >= 4.3.0, PHP 5, PHP 7)*

Note that these methods all uses the mime type mapping on the server. Not all servers for example detects `image/webp`.


## Installation

Install with composer


## Usage

Use `ImageMimeTypeGuesser::detect` if you do not want the library to make a wild guess based on file extension, but in return are willing to accept the increased probability of the library not returning a mime type as an answer.

Example:
```php
$result = ImageMimeTypeGuesser::detect($filePath);
if (is_null($result)) {
    // the mime type could not be determined
} elseif ($result === false) {
    // it is NOT an image (not a mime type that the server knows about anyway)
} else {
    // it is an image, and we know its mime type!
    $mimeType = $result;
}
```

If you are ok with wild guessing from file extension, use `ImageMimeTypeGuesser::guess` or `ImageMimeTypeGuesser::lenientGuess`. Lets start with the first.

`ImageMimeTypeGuesser::guess` will first try detection. If detection fails (void is returned), it will fall back to guessing from extension using `GuessFromExtension::guess`.

As with the detect method, it also has three possible outcomes: a mime type, false or void.

*Warning*: Beware that guessing from file extension is unsuited when your aim is to protect the server from harmful uploads.

*Notice*: Only a limited set of image extensions is recognized by the extension to mimetype mapper - namely the following: { bmp, gif, ico, jpg, jpeg, png, tif, tiff, webp, svg }. If you need some other specifically, feel free to add a PR, or ask me to do it by creating an issue.


Example:
```php
$result = ImageMimeTypeGuesser::guess($filePath);
if ($result !== false) {
    // it is an image, and we know its mime type (well, we don't really know, because we allowed guessing from extension)
    $mimeType = $result;
} else {
    // not an image
}
```

If you do not want your servers limited knowledge about image types to be decisive, you can use lenientGuess. It tries to detect. If detection fails (void *or false* is returned), it will fall back to guessing based on file extension.

Say for example that your server does not recognize the image/webp format, and you are examining a file "test.webp". In that case, a detection with *detect* will return false (provided that one of the detection methods are operational). The *guess* method will *also* return false, as it never gets to fall back to file extension mapping. However, *lenientGuess* will nail it, and return 'image/webp'.

For those who speaks code, the logic is perhaps best described with the code itself:

```php
public static function lenientGuess($filePath)
{
    $detectResult = self::detect($filePath);
    if ($detectResult === false) {
        // The server does not recognize this image type.
        // - but perhaps it is because it does not know about this image type.
        // - so we turn to mapping the file extension
        return GuessFromExtension::guess($filePath);
    } elseif (is_null($detectResult)) {
        // the mime type could not be determined
        // perhaps we also in this case want to turn to mapping the file extension
        return GuessFromExtension::guess($filePath);
    }
    return $detectResult;
}
```


Finally, for convenience, there are three methods for testing if a detection / guess / lenient guess is in a list of mime types. They are called `ImageMimeTypeGuesser::detectIsIn`, `ImageMimeTypeGuesser::guessIsIn` and `ImageMimeTypeGuesser::lenientGuessIsIn`.

Example:

```php
if (ImageMimeTypeGuesser::guessIsIn($filePath, ['image/jpeg','image/png']) {
    // Image is either a jpeg or a png (probably)
}
```
