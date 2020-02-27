<?php

/**
 * ImageMimeTypeGuesser - Detect / guess mime type of an image
 *
 * @link https://github.com/rosell-dk/image-mime-type-guesser
 * @license MIT
 */

namespace ImageMimeTypeGuesser;

class GuessFromExtension
{


    /**
     *  Make a wild guess based on file extension.
     *
     *  - and I mean wild!
     *
     *  Only most popular image types are recognized.
     *  Many are not. See this list: https://www.iana.org/assignments/media-types/media-types.xhtml
     *                - and the constants here: https://secure.php.net/manual/en/function.exif-imagetype.php
     *
     *  If no mapping found, nothing is returned
     *
     *  TODO: jp2, jpx, ...
     * Returns:
     * - mimetype (if file extension could be mapped to an image type),
     * - false (if file extension could be mapped to a type known not to be an image type)
     * - null (if file extension could not be mapped to any mime type, using our little list)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if file extension could be mapped to an image type),
     *    false (if file extension could be mapped to a type known not to be an image type)
     *    or null (if file extension could not be mapped to any mime type, using our little list)
     */
    public static function guess($filePath)
    {
        if (!@file_exists($filePath)) {
            return false;
        }
        /*
        Not using pathinfo, as it is locale aware, and I'm not sure if that could lead to problems

        if (!function_exists('pathinfo')) {
            // This is really a just in case! - We do not expect this to happen.
            // - in fact we have a test case asserting that this does not happen.
            return null;
            //
            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            $fileExtension = strtolower($fileExtension);
        }*/

        $result = preg_match('#\\.([^.]*)$#', $filePath, $matches);
        if ($result !== 1) {
            return null;
        }
        $fileExtension = $matches[1];

        // Trivial image mime types
        if (in_array($fileExtension, ['bmp', 'gif', 'jpeg', 'png', 'tiff', 'webp'])) {
            return 'image/' . $fileExtension;
        }

        // Common extensions that are definitely not images
        if (in_array($fileExtension, ['txt', 'doc', 'zip', 'gz', 'exe'])) {
            return false;
        }

        // Non-trivial image mime types
        switch ($fileExtension) {
            case 'ico':
                return 'image/vnd.microsoft.icon';      // or perhaps 'x-icon' ?

            case 'jpg':
                return 'image/jpeg';

            case 'svg':
                return 'image/svg+xml';

            case 'tif':
                return 'image/tiff';
        }

        // We do not know this extension, return null
        return null;
    }

}
