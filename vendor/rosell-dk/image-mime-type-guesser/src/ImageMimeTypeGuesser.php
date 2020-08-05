<?php

/**
 * ImageMimeTypeGuesser - Detect / guess mime type of an image
 *
 * The library is born out of a discussion here:
 * https://github.com/rosell-dk/webp-convert/issues/98
 *
 * @link https://github.com/rosell-dk/image-mime-type-guesser
 * @license MIT
 */

namespace ImageMimeTypeGuesser;

use \ImageMimeTypeGuesser\Detectors\Stack;

class ImageMimeTypeGuesser
{


    /**
     * Try to detect mime type of image using all available detectors (the "stack" detector).
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
    public static function detect($filePath)
    {
        return Stack::detect($filePath);
    }

    /**
     * Try to detect mime type of image. If that fails, make a guess based on the file extension.
     *
     * Try to detect mime type of image using "stack" detector (all available methods, until one succeeds)
     * If that fails (null), fall back to wild west guessing based solely on file extension.
     *
     * Returns:
     * - mime type (string) (if it is an image, and type could be determined / mapped from file extension))
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
     */
    public static function guess($filePath)
    {
        $detectionResult = self::detect($filePath);
        if (!is_null($detectionResult)) {
            return $detectionResult;
        }

        // fall back to the wild west method
        return GuessFromExtension::guess($filePath);
    }

    /**
     * Try to detect mime type of image. If that fails, make a guess based on the file extension.
     *
     * Try to detect mime type of image using "stack" detector (all available methods, until one succeeds)
     * If that fails (false or null), fall back to wild west guessing based solely on file extension.
     *
     * Returns:
     * - mime type (string) (if it is an image, and type could be determined / mapped from file extension)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined / guessed),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
     */
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


    /**
     * Check if the *detected* mime type is in a list of accepted mime types.
     *
     * @param  string  $filePath  The path to the file
     * @param  string[]  $mimeTypes  Mime types to accept
     * @return bool  Whether the detected mime type is in the $mimeTypes array or not
     */
    public static function detectIsIn($filePath, $mimeTypes)
    {
        return in_array(self::detect($filePath), $mimeTypes);
    }

    /**
     * Check if the *guessed* mime type is in a list of accepted mime types.
     *
     * @param  string  $filePath  The path to the file
     * @param  string[]  $mimeTypes  Mime types to accept
     * @return bool  Whether the detected / guessed mime type is in the $mimeTypes array or not
     */
    public static function guessIsIn($filePath, $mimeTypes)
    {
        return in_array(self::guess($filePath), $mimeTypes);
    }

    /**
     * Check if the *leniently guessed* mime type is in a list of accepted mime types.
     *
     * @param  string  $filePath  The path to the file
     * @param  string[]  $mimeTypes  Mime types to accept
     * @return bool  Whether the detected / leniently guessed mime type is in the $mimeTypes array or not
     */
    public static function lenientGuessIsIn($filePath, $mimeTypes)
    {
        return in_array(self::lenientGuess($filePath), $mimeTypes);
    }
}
