<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInputException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

/**
 * Convert images to webp using gd extension.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Gd extends AbstractConverter
{
    public function supportsLossless()
    {
        return false;
    }

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'alpha-quality',
            'auto-filter',
            'encoding',
            'low-memory',
            'metadata',
            'method',
            'near-lossless',
            'preset',
            'size-in-percentage',
            'use-nice'
        ];
    }

    private $errorMessageWhileCreating = '';
    private $errorNumberWhileCreating;

    /**
     * Check (general) operationality of Gd converter.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        if (!extension_loaded('gd')) {
            throw new SystemRequirementsNotMetException('Required Gd extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            throw new SystemRequirementsNotMetException(
                'Gd has been compiled without webp support.'
            );
        }
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Gd has been compiled without support for image type
     */
    public function checkConvertability()
    {
        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                if (!function_exists('imagecreatefrompng')) {
                    throw new SystemRequirementsNotMetException(
                        'Gd has been compiled without PNG support and can therefore not convert this PNG image.'
                    );
                }
                break;

            case 'image/jpeg':
                if (!function_exists('imagecreatefromjpeg')) {
                    throw new SystemRequirementsNotMetException(
                        'Gd has been compiled without Jpeg support and can therefore not convert this jpeg image.'
                    );
                }
        }
    }

    /**
     * Find out if all functions exists.
     *
     * @return boolean
     */
    private static function functionsExist($functionNamesArr)
    {
        foreach ($functionNamesArr as $functionName) {
            if (!function_exists($functionName)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Try to convert image pallette to true color on older systems that does not have imagepalettetotruecolor().
     *
     * The aim is to function as imagepalettetotruecolor, but for older systems.
     * So, if the image is already rgb, nothing will be done, and true will be returned
     * PS: Got the workaround here: https://secure.php.net/manual/en/function.imagepalettetotruecolor.php
     *
     * @param  resource  $image
     * @return boolean  TRUE if the convertion was complete, or if the source image already is a true color image,
     *          otherwise FALSE is returned.
     */
    private function makeTrueColorUsingWorkaround(&$image)
    {
        //return $this->makeTrueColor($image);
        /*
        if (function_exists('imageistruecolor') && imageistruecolor($image)) {
            return true;
        }*/
        if (self::functionsExist(['imagecreatetruecolor', 'imagealphablending', 'imagecolorallocatealpha',
                'imagefilledrectangle', 'imagecopy', 'imagedestroy', 'imagesx', 'imagesy'])) {
            $dst = imagecreatetruecolor(imagesx($image), imagesy($image));

            if ($dst === false) {
                return false;
            }

            //prevent blending with default black
            if (imagealphablending($dst, false) === false) {
                return false;
            }

            //change the RGB values if you need, but leave alpha at 127
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);

            if ($transparent === false) {
                return false;
            }

            //simpler than flood fill
            if (imagefilledrectangle($dst, 0, 0, imagesx($image), imagesy($image), $transparent) === false) {
                return false;
            }

            //restore default blending
            if (imagealphablending($dst, true) === false) {
                return false;
            };

            if (imagecopy($dst, $image, 0, 0, 0, 0, imagesx($image), imagesy($image)) === false) {
                return false;
            }
            imagedestroy($image);

            $image = $dst;
            return true;
        } else {
            // The necessary methods for converting color palette are not avalaible
            return false;
        }
    }

    /**
     * Try to convert image pallette to true color.
     *
     * Try to convert image pallette to true color. If imagepalettetotruecolor() exists, that is used (available from
     * PHP >= 5.5.0). Otherwise using workaround found on the net.
     *
     * @param  resource  $image
     * @return boolean  TRUE if the convertion was complete, or if the source image already is a true color image,
     *          otherwise FALSE is returned.
     */
    private function makeTrueColor(&$image)
    {
        if (function_exists('imagepalettetotruecolor')) {
            return imagepalettetotruecolor($image);
        } else {
            // imagepalettetotruecolor() is not available on this system. Using custom implementation instead
            return $this->makeTrueColorUsingWorkaround($image);
        }
    }

    /**
     * Create Gd image resource from source
     *
     * @throws  InvalidInputException  if mime type is unsupported or could not be detected
     * @throws  ConversionFailedException  if imagecreatefrompng or imagecreatefromjpeg fails
     * @return  resource  $image  The created image
     */
    private function createImageResource()
    {
        $mimeType = $this->getMimeTypeOfSource();

        switch ($mimeType) {
            case 'image/png':
                $image = imagecreatefrompng($this->source);
                if ($image === false) {
                    throw new ConversionFailedException(
                        'Gd failed when trying to load/create image (imagecreatefrompng() failed)'
                    );
                }
                return $image;

            case 'image/jpeg':
                $image = imagecreatefromjpeg($this->source);
                if ($image === false) {
                    throw new ConversionFailedException(
                        'Gd failed when trying to load/create image (imagecreatefromjpeg() failed)'
                    );
                }
                return $image;
        }

        throw new InvalidInputException('Unsupported mime type');
    }

    /**
     * Try to make image resource true color if it is not already.
     *
     * @param  resource  $image  The image to work on
     * @return void
     */
    protected function tryToMakeTrueColorIfNot(&$image)
    {
        $mustMakeTrueColor = false;
        if (function_exists('imageistruecolor')) {
            if (imageistruecolor($image)) {
                $this->logLn('image is true color');
            } else {
                $this->logLn('image is not true color');
                $mustMakeTrueColor = true;
            }
        } else {
            $this->logLn('It can not be determined if image is true color');
            $mustMakeTrueColor = true;
        }

        if ($mustMakeTrueColor) {
            $this->logLn('converting color palette to true color');
            $success = $this->makeTrueColor($image);
            if (!$success) {
                $this->logLn(
                    'Warning: FAILED converting color palette to true color. ' .
                    'Continuing, but this does NOT look good.'
                );
            }
        }
    }

    /**
     *
     * @param  resource  $image
     * @return boolean  true if alpha blending was set successfully, false otherwise
     */
    protected function trySettingAlphaBlending($image)
    {
        if (function_exists('imagealphablending')) {
            // TODO: Should we set second parameter to false instead?
            // As here: https://www.texelate.co.uk/blog/retaining-png-transparency-with-php-gd
            // (PS: I have backed up some local changes - to Gd.php, which includes changing that param
            // to false. But I didn't finish up back then and now I forgot, so need to retest before
            // changing anything...
            if (!imagealphablending($image, true)) {
                $this->logLn('Warning: imagealphablending() failed');
                return false;
            }
        } else {
            $this->logLn(
                'Warning: imagealphablending() is not available on your system.' .
                ' Converting PNGs with transparency might fail on some systems'
            );
            return false;
        }

        if (function_exists('imagesavealpha')) {
            if (!imagesavealpha($image, true)) {
                $this->logLn('Warning: imagesavealpha() failed');
                return false;
            }
        } else {
            $this->logLn(
                'Warning: imagesavealpha() is not available on your system. ' .
                'Converting PNGs with transparency might fail on some systems'
            );
            return false;
        }
        return true;
    }

    protected function errorHandlerWhileCreatingWebP($errno, $errstr, $errfile, $errline)
    {
        $this->errorNumberWhileCreating = $errno;
        $this->errorMessageWhileCreating = $errstr . ' in ' . $errfile . ', line ' . $errline .
            ', PHP ' . PHP_VERSION . ' (' . PHP_OS . ')';
        //return false;
    }

    /**
     *
     * @param  resource  $image
     * @return void
     */
    protected function destroyAndRemove($image)
    {
        imagedestroy($image);
        if (file_exists($this->destination)) {
            unlink($this->destination);
        }
    }

    /**
     *
     * @param  resource  $image
     * @return void
     */
    protected function tryConverting($image)
    {

        // Danger zone!
        //    Using output buffering to generate image.
        //    In this zone, Do NOT do anything that might produce unwanted output
        //    Do NOT call $this->logLn
        // --------------------------------- (start of danger zone)

        $addedZeroPadding = false;
        set_error_handler(array($this, "errorHandlerWhileCreatingWebP"));

        // This line may trigger log, so we need to do it BEFORE ob_start() !
        $q = $this->getCalculatedQuality();

        ob_start();

        //$success = imagewebp($image, $this->destination, $q);
        $success = imagewebp($image, null, $q);

        if (!$success) {
            $this->destroyAndRemove($image);
            ob_end_clean();
            restore_error_handler();
            throw new ConversionFailedException(
                'Failed creating image. Call to imagewebp() failed.',
                $this->errorMessageWhileCreating
            );
        }


        // The following hack solves an `imagewebp` bug
        // See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
        if (ob_get_length() % 2 == 1) {
            echo "\0";
            $addedZeroPadding = true;
        }
        $output = ob_get_clean();
        restore_error_handler();

        if ($output == '') {
            $this->destroyAndRemove($image);
            throw new ConversionFailedException(
                'Gd failed: imagewebp() returned empty string'
            );
        }

        // --------------------------------- (end of danger zone).


        if ($this->errorMessageWhileCreating != '') {
            switch ($this->errorNumberWhileCreating) {
                case E_WARNING:
                    $this->logLn('An warning was produced during conversion: ' . $this->errorMessageWhileCreating);
                    break;
                case E_NOTICE:
                    $this->logLn('An notice was produced during conversion: ' . $this->errorMessageWhileCreating);
                    break;
                default:
                    $this->destroyAndRemove($image);
                    throw new ConversionFailedException(
                        'An error was produced during conversion',
                        $this->errorMessageWhileCreating
                    );
                    //break;
            }
        }

        if ($addedZeroPadding) {
            $this->logLn(
                'Fixing corrupt webp by adding a zero byte ' .
                '(older versions of Gd had a bug, but this hack fixes it)'
            );
        }

        $success = file_put_contents($this->destination, $output);

        if (!$success) {
            $this->destroyAndRemove($image);
            throw new ConversionFailedException(
                'Gd failed when trying to save the image. Check file permissions!'
            );
        }

        /*
        Previous code was much simpler, but on a system, the hack was not activated (a file with uneven number of bytes
        was created). This is puzzeling. And the old code did not provide any insights.
        Also, perhaps having two subsequent writes to the same file could perhaps cause a problem.
        In the new code, there is only one write.
        However, a bad thing about the new code is that the entire webp file is read into memory. This might cause
        memory overflow with big files.
        Perhaps we should check the filesize of the original and only use the new code when it is smaller than
        memory limit set in PHP by a certain factor.
        Or perhaps only use the new code on older versions of Gd
        https://wordpress.org/support/topic/images-not-seen-on-chrome/#post-11390284

        Here is the old code:

        $success = imagewebp($image, $this->destination, $this->getCalculatedQuality());

        if (!$success) {
            throw new ConversionFailedException(
                'Gd failed when trying to save the image as webp (call to imagewebp() failed). ' .
                'It probably failed writing file. Check file permissions!'
            );
        }


        // This hack solves an `imagewebp` bug
        // See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
        if (filesize($this->destination) % 2 == 1) {
            file_put_contents($this->destination, "\0", FILE_APPEND);
        }
        */
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doActualConvert()
    {

        $this->logLn('GD Version: ' . gd_info()["GD Version"]);

        // Btw: Check out processWebp here:
        // https://github.com/Intervention/image/blob/master/src/Intervention/Image/Gd/Encoder.php

        // Create image resource
        $image = $this->createImageResource();

        // Try to convert color palette if it is not true color
        $this->tryToMakeTrueColorIfNot($image);


        if ($this->getMimeTypeOfSource() == 'image/png') {
            // Try to set alpha blending
            $this->trySettingAlphaBlending($image);
        }

        // Try to convert it to webp
        $this->tryConverting($image);

        // End of story
        imagedestroy($image);
    }
}
