<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Options\BooleanOption;

//require '/home/rosell/.composer/vendor/autoload.php';

/**
 * Convert images to webp using Vips extension.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Vips extends AbstractConverter
{
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'size-in-percentage',
            'use-nice'
        ];
    }

    protected function createOptions()
    {
        parent::createOptions();

        $this->options2->addOptions(
            new BooleanOption('smart-subsample', false)
        );
    }

    /**
     * Check operationality of Vips converter.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        if (!extension_loaded('vips')) {
            throw new SystemRequirementsNotMetException('Required Vips extension is not available.');
        }

        if (!function_exists('vips_image_new_from_file')) {
            throw new SystemRequirementsNotMetException(
                'Vips extension seems to be installed, however something is not right: ' .
                'the function "vips_image_new_from_file" is not available.'
            );
        }

        // TODO: Should we also test if webp is available? (It seems not to be neccessary - it seems
        // that webp be well intergrated part of vips)
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Vips does not support image type
     */
    public function checkConvertability()
    {
        // It seems that png and jpeg are always supported by Vips
        // - so nothing needs to be done here

        if (function_exists('vips_version')) {
            $this->logLn('vipslib version: ' . vips_version());
        }
        $this->logLn('vips extension version: ' . phpversion('vips'));
    }

    /**
     * Create vips image resource from source file
     *
     * @throws  ConversionFailedException  if image resource cannot be created
     * @return  resource  vips image resource
     */
    private function createImageResource()
    {
        // We are currently using vips_image_new_from_file(), but we could consider
        // calling vips_jpegload / vips_pngload instead
        $result = /** @scrutinizer ignore-call */ vips_image_new_from_file($this->source, []);
        if ($result === -1) {
            /*throw new ConversionFailedException(
                'Failed creating new vips image from file: ' . $this->source
            );*/
            $message = /** @scrutinizer ignore-call */ vips_error_buffer();
            throw new ConversionFailedException($message);
        }

        if (!is_array($result)) {
            throw new ConversionFailedException(
                'vips_image_new_from_file did not return an array, which we expected'
            );
        }

        if (count($result) != 1) {
            throw new ConversionFailedException(
                'vips_image_new_from_file did not return an array of length 1 as we expected ' .
                '- length was: ' . count($result)
            );
        }

        $im = array_shift($result);
        return $im;
    }

    /**
     * Create parameters for webpsave
     *
     * @return  array  the parameters as an array
     */
    private function createParamsForVipsWebPSave()
    {
        // webpsave options are described here:
        // v 8.8.0:  https://libvips.github.io/libvips/API/current/VipsForeignSave.html#vips-webpsave
        // v ?.?.?:  https://jcupitt.github.io/libvips/API/current/VipsForeignSave.html#vips-webpsave
        // near_lossless option is described here: https://github.com/libvips/libvips/pull/430

        // Note that "method" is currently not supported (27 may 2019)

        $options = [
            "Q" => $this->getCalculatedQuality(),
            'lossless' => ($this->options['encoding'] == 'lossless'),
            'strip' => $this->options['metadata'] == 'none',
        ];

        // Only set the following options if they differ from the default of vipslib
        // This ensures we do not get warning if that property isn't supported
        if ($this->options['smart-subsample'] !== false) {
            $options['smart_subsample'] = $this->options['smart-subsample'];
        }
        if ($this->options['alpha-quality'] !== 100) {
            $options['alpha_q'] = $this->options['alpha-quality'];
        }

        if (!is_null($this->options['preset']) && ($this->options['preset'] != 'none')) {
            // preset. 0:default, 1:picture, 2:photo, 3:drawing, 4:icon, 5:text, 6:last
            $options['preset'] = array_search(
                $this->options['preset'],
                ['default', 'picture', 'photo', 'drawing', 'icon', 'text']
            );
        }
        if ($this->options['near-lossless'] !== 100) {
            if ($this->options['encoding'] == 'lossless') {
                // We only let near_lossless have effect when encoding is set to lossless
                // otherwise encoding=auto would not work as expected
                // Available in https://github.com/libvips/libvips/pull/430, merged 1 may 2016
                // seems it corresponds to release 8.4.2
                $options['near_lossless'] = true;

                // In Vips, the near-lossless value is controlled by Q.
                // this differs from how it is done in cwebp, where it is an integer.
                // We have chosen same option syntax as cwebp
                $options['Q'] = $this->options['near-lossless'];
            }
        }

        return $options;
    }

    /**
     * Convert with vips extension.
     *
     * Tries to create image resource and save it as webp using the calculated options.
     * Vips fails when a parameter is not supported, but we detect this and unset that parameter and try again
     * (recursively call itself until there is no more of these kind of errors).
     *
     * @param  resource  $im  A vips image resource to save
     * @throws  ConversionFailedException  if conversion fails.
     */
    private function webpsave($im, $options)
    {
        $result = /** @scrutinizer ignore-call */ vips_call('webpsave', $im, $this->destination, $options);

        //trigger_error('test-warning', E_USER_WARNING);
        if ($result === -1) {
            $message = /** @scrutinizer ignore-call */ vips_error_buffer();

            $nameOfPropertyNotFound = '';
            if (preg_match("#no property named .(.*).#", $message, $matches)) {
                $nameOfPropertyNotFound = $matches[1];
            } elseif (preg_match("#(.*)\\sunsupported$#", $message, $matches)) {
                // Actually, I am not quite sure if this ever happens.
                // I got a "near_lossless unsupported" error message in a build, but perhaps it rather a warning
                if (in_array($matches[1], ['lossless', 'alpha_q', 'near_lossless', 'smart_subsample'])) {
                    $nameOfPropertyNotFound = $matches[1];
                }
            }

            if ($nameOfPropertyNotFound != '') {
                $this->logLn(
                    'Your version of vipslib does not support the "' . $nameOfPropertyNotFound . '" property. ' .
                    'The option is ignored.'
                );
                unset($options[$nameOfPropertyNotFound]);
                $this->webpsave($im, $options);
            } else {
                throw new ConversionFailedException($message);
            }
        }
    }

    /**
     * Convert with vips extension.
     *
     * Tries to create image resource and save it as webp using the calculated options.
     * Vips fails when a parameter is not supported, but we detect this and unset that parameter and try again
     * (repeat until success).
     *
     * @throws  ConversionFailedException  if conversion fails.
     */
    protected function doActualConvert()
    {
/*
        $im = \Jcupitt\Vips\Image::newFromFile($this->source);
        //$im->writeToFile(__DIR__ . '/images/small-vips.webp', ["Q" => 10]);

        $im->webpsave($this->destination, [
            "Q" => 80,
            //'near_lossless' => true
        ]);
        return;*/

        $im = $this->createImageResource();
        $options = $this->createParamsForVipsWebPSave();
        $this->webpsave($im, $options);
    }
}
