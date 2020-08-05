<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp using Imagick extension.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Imagick extends AbstractConverter
{
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'near-lossless',
            'preset',
            'size-in-percentage',
            'use-nice'
        ];
    }

    /**
     * Check operationality of Imagick converter.
     *
     * Note:
     * It may be that Gd has been compiled without jpeg support or png support.
     * We do not check for this here, as the converter could still be used for the other.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     * @return void
     */
    public function checkOperationality()
    {
        if (!extension_loaded('imagick')) {
            throw new SystemRequirementsNotMetException('Required iMagick extension is not available.');
        }

        if (!class_exists('\\Imagick')) {
            throw new SystemRequirementsNotMetException(
                'iMagick is installed, but not correctly. The class Imagick is not available'
            );
        }

        $im = new \Imagick();
        if (!in_array('WEBP', $im->queryFormats('WEBP'))) {
            throw new SystemRequirementsNotMetException('iMagick was compiled without WebP support.');
        }
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Imagick does not support image type
     */
    public function checkConvertability()
    {
        $im = new \Imagick();
        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                if (!in_array('PNG', $im->queryFormats('PNG'))) {
                    throw new SystemRequirementsNotMetException(
                        'Imagick has been compiled without PNG support and can therefore not convert this PNG image.'
                    );
                }
                break;
            case 'image/jpeg':
                if (!in_array('JPEG', $im->queryFormats('JPEG'))) {
                    throw new SystemRequirementsNotMetException(
                        'Imagick has been compiled without Jpeg support and can therefore not convert this Jpeg image.'
                    );
                }
                break;
        }
    }

    /**
     *
     * It may also throw an ImagickException if imagick throws an exception
     * @throws CreateDestinationFileException if imageblob could not be saved to file
     */
    protected function doActualConvert()
    {
        /*
         * More about iMagick's WebP options:
         * - Inspect source code: https://github.com/ImageMagick/ImageMagick/blob/master/coders/webp.c#L559
         *      (search for "webp:")
         * - http://www.imagemagick.org/script/webp.php
         * - https://developers.google.com/speed/webp/docs/cwebp
         * - https://stackoverflow.com/questions/37711492/imagemagick-specific-webp-calls-in-php
         */

        $options = $this->options;

        // This might throw - we let it!
        $im = new \Imagick($this->source);

        //$im = new \Imagick();
        //$im->pingImage($this->source);
        //$im->readImage($this->source);

        $im->setImageFormat('WEBP');

        $im->setOption('webp:method', $options['method']);
        $im->setOption('webp:lossless', $options['encoding'] == 'lossless' ? 'true' : 'false');
        $im->setOption('webp:low-memory', $options['low-memory'] ? 'true' : 'false');
        $im->setOption('webp:alpha-quality', $options['alpha-quality']);

        if ($options['auto-filter'] === true) {
            $im->setOption('webp:auto-filter', 'true');
        }

        if ($options['metadata'] == 'none') {
            // Strip metadata and profiles
            $im->stripImage();
        }

        if ($this->isQualityDetectionRequiredButFailing()) {
            // Luckily imagick is a big boy, and automatically converts with same quality as
            // source, when the quality isn't set.
            // So we simply do not set quality.
            // This actually kills the max-quality functionality. But I deem that this is more important
            // because setting image quality to something higher than source generates bigger files,
            // but gets you no extra quality. When failing to limit quality, you at least get something
            // out of it
            $this->logLn('Converting without setting quality in order to achieve auto quality');
        } else {
            $im->setImageCompressionQuality($this->getCalculatedQuality());
        }

        // https://stackoverflow.com/questions/29171248/php-imagick-jpeg-optimization
        // setImageFormat

        // TODO: Read up on
        // https://www.smashingmagazine.com/2015/06/efficient-image-resizing-with-imagemagick/
        // https://github.com/nwtn/php-respimg

        // TODO:
        // Should we set alpha channel for PNG's like suggested here:
        // https://gauntface.com/blog/2014/09/02/webp-support-with-imagemagick-and-php ??
        // It seems that alpha channel works without... (at least I see completely transparerent pixels)

        // We used to use writeImageFile() method. But we now use getImageBlob(). See issue #43

        // This might throw - we let it!
        $imageBlob = $im->getImageBlob();

        $success = file_put_contents($this->destination, $imageBlob);

        if (!$success) {
            throw new CreateDestinationFileException('Failed writing file');
        }

        // Btw: check out processWebp() method here:
        // https://github.com/Intervention/image/blob/master/src/Intervention/Image/Imagick/Encoder.php
    }
}
