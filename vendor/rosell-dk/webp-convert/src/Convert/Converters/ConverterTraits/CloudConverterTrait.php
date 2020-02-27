<?php

namespace WebPConvert\Convert\Converters\ConverterTraits;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Helpers\PhpIniSizes;

/**
 * Trait for converters that works by uploading to a cloud service.
 *
 * The trait adds a method for checking against upload limits.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait CloudConverterTrait
{

    /**
     * Test that filesize is below "upload_max_filesize" and "post_max_size" values in php.ini.
     *
     * @param  string  $iniSettingId  Id of ini setting (ie "upload_max_filesize")
     *
     * @throws  ConversionFailedException  if filesize is larger than the ini setting
     * @return  void
     */
    private function checkFileSizeVsIniSetting($iniSettingId)
    {
        $fileSize = @filesize($this->source);
        if ($fileSize === false) {
            return;
        }
        $sizeInIni = PhpIniSizes::getIniBytes($iniSettingId);
        if ($sizeInIni === false) {
            // Not sure if we should throw an exception here, or not...
            return;
        }
        if ($sizeInIni < $fileSize) {
            throw new ConversionFailedException(
                'File is larger than your ' . $iniSettingId . ' (set in your php.ini). File size:' .
                    round($fileSize/1024) . ' kb. ' .
                    $iniSettingId . ' in php.ini: ' . ini_get($iniSettingId) .
                    ' (parsed as ' . round($sizeInIni/1024) . ' kb)'
            );
        }
    }

    /**
     * Check convertability of cloud converters (that file is not bigger than limits set in php.ini).
     *
     * Performs the same as ::Convertability(). It is here so converters that overrides the
     * ::Convertability() still has a chance to do the checks.
     *
     * @throws  ConversionFailedException  if filesize is larger than "upload_max_filesize" or "post_max_size"
     * @return  void
     */
    public function checkConvertabilityCloudConverterTrait()
    {
        $this->checkFileSizeVsIniSetting('upload_max_filesize');
        $this->checkFileSizeVsIniSetting('post_max_size');
    }

    /**
     * Check convertability of cloud converters (file upload limits).
     */
    public function checkConvertability()
    {
        $this->checkConvertabilityCloudConverterTrait();
    }
}
