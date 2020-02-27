<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\ExecTrait;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp by calling imagemagick binary.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ImageMagick extends AbstractConverter
{
    use ExecTrait;
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'near-lossless',
            'preset',
            'size-in-percentage',
        ];
    }

    // To futher improve this converter, I could check out:
    // https://github.com/Orbitale/ImageMagickPHP

    private function getPath()
    {
        if (defined('WEBPCONVERT_IMAGEMAGICK_PATH')) {
            return constant('WEBPCONVERT_IMAGEMAGICK_PATH');
        }
        if (!empty(getenv('WEBPCONVERT_IMAGEMAGICK_PATH'))) {
            return getenv('WEBPCONVERT_IMAGEMAGICK_PATH');
        }
        return 'convert';
    }

    private function getVersion()
    {
        exec($this->getPath() . ' -version', $output, $returnCode);
        if (($returnCode == 0) && isset($output[0])) {
            return $output[0];
        } else {
            return 'unknown';
        }
    }

    public function isInstalled()
    {
        exec($this->getPath() . ' -version', $output, $returnCode);
        return ($returnCode == 0);
    }

    // Check if webp delegate is installed
    public function isWebPDelegateInstalled()
    {

        exec('convert -list delegate', $output, $returnCode);
        foreach ($output as $line) {
            if (preg_match('#webp\\s*=#i', $line)) {
                return true;
            }
        }

        // try other command
        exec('convert -list configure', $output, $returnCode);
        foreach ($output as $line) {
            if (preg_match('#DELEGATE.*webp#i', $line)) {
                return true;
            }
        }

        return false;

        // PS, convert -version does not output delegates on travis, so it is not reliable
    }

    /**
     * Check (general) operationality of imagack converter executable
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        $this->checkOperationalityExecTrait();

        if (!$this->isInstalled()) {
            throw new SystemRequirementsNotMetException(
                'imagemagick is not installed (cannot execute: "' . $this->getPath() . '")'
            );
        }
        if (!$this->isWebPDelegateInstalled()) {
            throw new SystemRequirementsNotMetException('webp delegate missing');
        }
    }

    /**
     * Build command line options
     *
     * @return string
     */
    private function createCommandLineOptions()
    {
        // PS: Available webp options for imagemagick are documented here:
        // https://imagemagick.org/script/webp.php

        $commandArguments = [];
        if ($this->isQualityDetectionRequiredButFailing()) {
            // quality:auto was specified, but could not be determined.
            // we cannot apply the max-quality logic, but we can provide auto quality
            // simply by not specifying the quality option.
        } else {
            $commandArguments[] = '-quality ' . escapeshellarg($this->getCalculatedQuality());
        }
        if ($this->options['encoding'] == 'lossless') {
            $commandArguments[] = '-define webp:lossless=true';
        }
        if ($this->options['low-memory']) {
            $commandArguments[] = '-define webp:low-memory=true';
        }
        if ($this->options['auto-filter'] === true) {
            $commandArguments[] = '-define webp:auto-filter=true';
        }
        if ($this->options['metadata'] == 'none') {
            $commandArguments[] = '-strip';
        }
        if ($this->options['alpha-quality'] !== 100) {
            $commandArguments[] = '-define webp:alpha-quality=' . strval($this->options['alpha-quality']);
        }

        // Unfortunately, near-lossless does not seem to be supported.
        // it does have a "preprocessing" option, which may be doing something similar

        $commandArguments[] = '-define webp:method=' . $this->options['method'];

        $commandArguments[] = escapeshellarg($this->source);
        $commandArguments[] = escapeshellarg('webp:' . $this->destination);

        return implode(' ', $commandArguments);
    }

    protected function doActualConvert()
    {
        $this->logLn($this->getVersion());

        $command = $this->getPath() . ' ' . $this->createCommandLineOptions();

        $useNice = (($this->options['use-nice']) && self::hasNiceSupport()) ? true : false;
        if ($useNice) {
            $this->logLn('using nice');
            $command = 'nice ' . $command;
        }
        $this->logLn('Executing command: ' . $command);
        exec($command, $output, $returnCode);

        $this->logExecOutput($output);
        if ($returnCode == 0) {
            $this->logLn('success');
        } else {
            $this->logLn('return code: ' . $returnCode);
        }

        if ($returnCode == 127) {
            throw new SystemRequirementsNotMetException('imagemagick is not installed');
        }
        if ($returnCode != 0) {
            throw new SystemRequirementsNotMetException('The exec call failed');
        }
    }
}
