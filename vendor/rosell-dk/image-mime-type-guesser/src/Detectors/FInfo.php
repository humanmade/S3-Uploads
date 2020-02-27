<?php

namespace ImageMimeTypeGuesser\Detectors;

class FInfo extends AbstractDetector
{

    /**
     * Try to detect mime type of image using *finfo* class.
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

        if (class_exists('finfo')) {
            // phpcs:ignore PHPCompatibility.PHP.NewClasses.finfoFound
            $finfo = new \finfo(FILEINFO_MIME);
            $mime = explode('; ', $finfo->file($filePath));
            $result = $mime[0];

            if (strpos($result, 'image/') === 0) {
                return $result;
            } else {
                return false;
            }
        }
        return null;
    }
}
