<?php

namespace ImageMimeTypeGuesser\Detectors;

use \ImageMimeTypeGuesser\Detectors\AbstractDetector;

class ExifImageType extends AbstractDetector
{

    /**
     * Try to detect mime type of image using *exif_imagetype*.
     *
     * Returns:
     * - mime type (string) (if it is in fact an image, and type could be determined)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
     */
    protected function doDetect($filePath)
    {
        // exif_imagetype is fast, however not available on all systems,
        // It may return false. In that case we can rely on that the file is not an image (and return false)
        if (function_exists('exif_imagetype')) {
            try {
                $imageType = exif_imagetype($filePath);
                return ($imageType ? image_type_to_mime_type($imageType) : false);
            } catch (\Exception $e) {
                // Might for example get "Read error!"
                // well well, don't let this stop us
                //echo $e->getMessage();
//                throw($e);
            }
        }
        return null;
    }
}
