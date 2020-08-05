<?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Helpers\JpegQualityDetector;

/**
 * Trait for handling the "quality:auto" option.
 *
 * This trait is only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning auto quality.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait AutoQualityTrait
{

    abstract public function logLn($msg, $style = '');
    abstract public function getMimeTypeOfSource();

    /** @var boolean  Whether the quality option has been processed or not */
    private $processed = false;

    /** @var boolean  Whether the quality of the source could be detected or not (set upon processing) */
    private $qualityCouldNotBeDetected = false;

    /** @var integer  The calculated quality (set upon processing - on successful detection) */
    private $calculatedQuality;


    /**
     *  Determine if quality detection is required but failing.
     *
     *  It is considered "required" when:
     *  - Mime type is "image/jpeg"
     *  - Quality is set to "auto"
     *
     *  If quality option hasn't been proccessed yet, it is triggered.
     *
     *  @return  boolean
     */
    public function isQualityDetectionRequiredButFailing()
    {
        $this->processQualityOptionIfNotAlready();
        return $this->qualityCouldNotBeDetected;
    }

    /**
     * Get calculated quality.
     *
     * If the "quality" option is a number, that number is returned.
     * If mime type of source is something else than "image/jpeg", the "default-quality" option is returned
     * If quality is "auto" and source is a jpeg image, it will be attempted to detect jpeg quality.
     * In case of failure, the value of the "default-quality" option is returned.
     * In case of success, the detected quality is returned, or the value of the "max-quality" if that is lower.
     *
     *  @return  int
     */
    public function getCalculatedQuality()
    {
        $this->processQualityOptionIfNotAlready();
        return $this->calculatedQuality;
    }

    /**
     * Process the quality option if it is not already processed.
     *
     * @return void
     */
    private function processQualityOptionIfNotAlready()
    {
        if (!$this->processed) {
            $this->processed = true;
            $this->processQualityOption();
        }
    }

    /**
     * Process the quality option.
     *
     * Sets the private property "calculatedQuality" according to the description for the getCalculatedQuality
     * function.
     * In case quality detection was attempted and failed, the private property "qualityCouldNotBeDetected" is set
     * to true. This is used by the "isQualityDetectionRequiredButFailing" (and documented there too).
     *
     * @return void
     */
    private function processQualityOption()
    {
        $options = $this->options;
        $source = $this->source;

        $q = $options['quality'];
        if ($q == 'auto') {
            if (($this->/** @scrutinizer ignore-call */getMimeTypeOfSource() == 'image/jpeg')) {
                $q = JpegQualityDetector::detectQualityOfJpg($source);
                if (is_null($q)) {
                    $q = $options['default-quality'];
                    $this->/** @scrutinizer ignore-call */logLn(
                        'Quality of source could not be established (Imagick or GraphicsMagick is required)' .
                        ' - Using default instead (' . $options['default-quality'] . ').'
                    );

                    $this->qualityCouldNotBeDetected = true;
                } else {
                    if ($q > $options['max-quality']) {
                        $this->logLn(
                            'Quality of source is ' . $q . '. ' .
                            'This is higher than max-quality, so using max-quality instead (' .
                                $options['max-quality'] . ')'
                        );
                    } else {
                        $this->logLn('Quality set to same as source: ' . $q);
                    }
                }
                $q = min($q, $options['max-quality']);
            } else {
                //$q = $options['default-quality'];
                $q = min($options['default-quality'], $options['max-quality']);
                $this->logLn('Quality: ' . $q . '. ');
            }
        } else {
            $this->logLn(
                'Quality: ' . $q . '. '
            );
            if (($this->getMimeTypeOfSource() == 'image/jpeg')) {
                $this->logLn(
                    'Consider setting quality to "auto" instead. It is generally a better idea'
                );
            }
        }
        $this->calculatedQuality = $q;
    }
}
