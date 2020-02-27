<?php

namespace WebPConvert\Convert\Helpers;

/**
 * Try to detect quality of a jpeg image using various tools.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class JpegQualityDetector
{

    /**
     * Try to detect quality of jpeg using imagick extension.
     *
     * Note that the detection might fail for two different reasons:
     * 1) Imagick is not installed
     * 2) Imagick for some reason fails to detect quality for some images
     *
     * In both cases, null is returned.
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    private static function detectQualityOfJpgUsingImagick($filename)
    {
        if (extension_loaded('imagick') && class_exists('\\Imagick')) {
            try {
                $img = new \Imagick($filename);

                // The required function is available as from PECL imagick v2.2.2
                if (method_exists($img, 'getImageCompressionQuality')) {
                    $quality = $img->getImageCompressionQuality();
                    if ($quality === 0) {
                        // We have experienced that this Imagick method returns 0 for some images,
                        // (even though the imagemagick binary is able to detect the quality)
                        // ie "/test/images/quality-undetectable-with-imagick.jpg". See #208
                        $quality = null;
                    }
                    return $quality;
                }
            } catch (\Exception $e) {
                // Well well, it just didn't work out.
                // - But perhaps next method will work...
            }
        }
        return null;
    }


    /**
     * Try to detect quality of jpeg using imagick binary.
     *
     * Note that the detection might fail for three different reasons:
     * 1) exec function is not available
     * 2) the 'identify' command is not available on the system
     * 3) imagemagick for some reason fails to detect quality for some images
     *
     * In the first two cases, null is returned.
     * In the third case, 92 is returned. This is what imagemagick returns when it cannot detect the quality.
     *    and unfortunately we cannot distinguish between the situation where the quality is undetectable
     *    and the situation where the quality is actually 92 (at least, I have not found a way to do so)
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    private static function detectQualityOfJpgUsingImageMagick($filename)
    {
        if (function_exists('exec')) {
            // Try Imagick using exec, and routing stderr to stdout (the "2>$1" magic)
            exec("identify -format '%Q' " . escapeshellarg($filename) . " 2>&1", $output, $returnCode);
            //echo 'out:' . print_r($output, true);
            if ((intval($returnCode) == 0) && (is_array($output)) && (count($output) == 1)) {
                return intval($output[0]);
            }
        }
        return null;
    }


    /**
     * Try to detect quality of jpeg using graphicsmagick binary.
     *
     * It seems that graphicsmagick is never able to detect the quality! - and always returns
     * the default quality, which is 75.
     * However, as this might be solved in future versions, the method might be useful one day.
     * But we treat "75" as a failure to detect and shall return null in that case.
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    private static function detectQualityOfJpgUsingGraphicsMagick($filename)
    {
        if (function_exists('exec')) {
            // Try GraphicsMagick
            exec("gm identify -format '%Q' " . escapeshellarg($filename) . " 2>&1", $output, $returnCode);
            if ((intval($returnCode) == 0) && (is_array($output)) && (count($output) == 1)) {
                $quality = intval($output[0]);

                // It seems that graphicsmagick is (currently) never able to detect the quality!
                // - and always returns 75 as a fallback
                // We shall therefore treat 75 as a failure to detect. (#209)
                if ($quality == 75) {
                    return null;
                }
                return $quality;
            }
        }
        return null;
    }


    /**
     * Try to detect quality of jpeg.
     *
     * Note: This method does not throw errors, but might dispatch warnings.
     * You can use the WarningsIntoExceptions class if it is critical to you that nothing gets "printed"
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    public static function detectQualityOfJpg($filename)
    {

        //trigger_error('warning test', E_USER_WARNING);

        // Test that file exists in order not to break things.
        if (!file_exists($filename)) {
            // One could argue that it would be better to throw an Exception...?
            return null;
        }

        // Try Imagick extension, if available
        $quality = self::detectQualityOfJpgUsingImagick($filename);

        if (is_null($quality)) {
            $quality = self::detectQualityOfJpgUsingImageMagick($filename);
        }

        if (is_null($quality)) {
            $quality = self::detectQualityOfJpgUsingGraphicsMagick($filename);
        }

        return $quality;
    }
}
