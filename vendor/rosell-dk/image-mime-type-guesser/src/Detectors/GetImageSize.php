<?php

namespace ImageMimeTypeGuesser\Detectors;

class GetImageSize extends AbstractDetector
{

    /**
     * Try to detect mime type of image using *getimagesize()*.
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
        // getimagesize is slower than exif_imagetype
        // It may not return "mime". In that case we can rely on that the file is not an image (and return false)
        if (function_exists('getimagesize')) {
            try {
                $imageSize = getimagesize($filePath);
                return (isset($imageSize['mime']) ? $imageSize['mime'] : false);
            } catch (\Exception $e) {
                // well well, don't let this stop us either                
                return null;
            }
        }
        return null;
    }
}
