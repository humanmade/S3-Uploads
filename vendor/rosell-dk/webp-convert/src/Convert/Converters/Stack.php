<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\ConverterFactory;
use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\ArrayOption;
use WebPConvert\Options\GhostOption;
use WebPConvert\Options\SensitiveArrayOption;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp by trying a stack of converters until success.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Stack extends AbstractConverter
{

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
            'use-nice',
            'skip',
            'default-quality',
            'quality',
            'max-quality',
        ];
    }

    protected function createOptions()
    {
        parent::createOptions();

        $this->options2->addOptions(
            new SensitiveArrayOption('converters', self::getAvailableConverters()),
            new SensitiveArrayOption('converter-options', []),
            new BooleanOption('shuffle', false),
            new ArrayOption('preferred-converters', []),
            new SensitiveArrayOption('extra-converters', [])
        );
    }

    /**
     * Get available converters (ids) - ordered by awesomeness.
     *
     * @return  array  An array of ids of converters that comes with this library
     */
    public static function getAvailableConverters()
    {
        return [
            'cwebp', 'vips', 'imagick', 'gmagick', 'imagemagick', 'graphicsmagick', 'wpc', 'ewww', 'gd'
        ];
    }

    /**
     * Check (general) operationality of imagack converter executable
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        if (count($this->options['converters']) == 0) {
            throw new ConverterNotOperationalException(
                'Converter stack is empty! - no converters to try, no conversion can be made!'
            );
        }

        // TODO: We should test if all converters are found in order to detect problems early

        //$this->logLn('Stack converter ignited');
    }

    protected function doActualConvert()
    {
        $options = $this->options;

        $beginTimeStack = microtime(true);

        $anyRuntimeErrors = false;

        $converters = $options['converters'];
        if (count($options['extra-converters']) > 0) {
            $converters = array_merge($converters, $options['extra-converters']);
            /*foreach ($options['extra-converters'] as $extra) {
                $converters[] = $extra;
            }*/
        }

        // preferred-converters
        if (count($options['preferred-converters']) > 0) {
            foreach (array_reverse($options['preferred-converters']) as $prioritizedConverter) {
                foreach ($converters as $i => $converter) {
                    if (is_array($converter)) {
                        $converterId = $converter['converter'];
                    } else {
                        $converterId = $converter;
                    }
                    if ($converterId == $prioritizedConverter) {
                        unset($converters[$i]);
                        array_unshift($converters, $converter);
                        break;
                    }
                }
            }
            // perhaps write the order to the log? (without options) - but this requires some effort
        }

        // shuffle
        if ($options['shuffle']) {
            shuffle($converters);
        }

        //$this->logLn(print_r($converters));
        //$options['converters'] = $converters;
        //$defaultConverterOptions = $options;
        $defaultConverterOptions = [];

        foreach ($this->options2->getOptionsMap() as $id => $option) {
            if ($option->isValueExplicitlySet() && !($option instanceof GhostOption)) {
                //$this->logLn('hi' . $id);
                $defaultConverterOptions[$id] = $option->getValue();
            }
        }

        //unset($defaultConverterOptions['converters']);
        //unset($defaultConverterOptions['converter-options']);
        $defaultConverterOptions['_skip_input_check'] = true;
        $defaultConverterOptions['_suppress_success_message'] = true;
        unset($defaultConverterOptions['converters']);
        unset($defaultConverterOptions['extra-converters']);
        unset($defaultConverterOptions['converter-options']);
        unset($defaultConverterOptions['preferred-converters']);
        unset($defaultConverterOptions['shuffle']);

//        $this->logLn('converters: ' . print_r($converters, true));

        //return;
        foreach ($converters as $converter) {
            if (is_array($converter)) {
                $converterId = $converter['converter'];
                $converterOptions = isset($converter['options']) ? $converter['options'] : [];
            } else {
                $converterId = $converter;
                $converterOptions = [];
                if (isset($options['converter-options'][$converterId])) {
                    // Note: right now, converter-options are not meant to be used,
                    //       when you have several converters of the same type
                    $converterOptions = $options['converter-options'][$converterId];
                }
            }
            $converterOptions = array_merge($defaultConverterOptions, $converterOptions);
            /*
            if ($converterId != 'stack') {
                //unset($converterOptions['converters']);
                //unset($converterOptions['converter-options']);
            } else {
                //$converterOptions['converter-options'] =
                $this->logLn('STACK');
                $this->logLn('converterOptions: ' . print_r($converterOptions, true));
            }*/

            $beginTime = microtime(true);

            $this->ln();
            $this->logLn('Trying: ' . $converterId, 'italic');

            $converter = ConverterFactory::makeConverter(
                $converterId,
                $this->source,
                $this->destination,
                $converterOptions,
                $this->logger
            );

            try {
                $converter->doConvert();

                //self::runConverterWithTiming($converterId, $source, $destination, $converterOptions, false, $logger);

                $this->logLn($converterId . ' succeeded :)');
                //throw new ConverterNotOperationalException('...');
                return;
            } catch (ConverterNotOperationalException $e) {
                $this->logLn($e->getMessage());
            } catch (ConversionSkippedException $e) {
                $this->logLn($e->getMessage());
            } catch (ConversionFailedException $e) {
                $this->logLn($e->getMessage(), 'italic');
                $prev = $e->getPrevious();
                if (!is_null($prev)) {
                    $this->logLn($prev->getMessage(), 'italic');
                    $this->logLn(' in ' . $prev->getFile() . ', line ' . $prev->getLine(), 'italic');
                    $this->ln();
                }
                //$this->logLn($e->getTraceAsString());
                $anyRuntimeErrors = true;
            }
            $this->logLn($converterId . ' failed in ' . round((microtime(true) - $beginTime) * 1000) . ' ms');
        }

        $this->ln();
        $this->logLn('Stack failed in ' . round((microtime(true) - $beginTimeStack) * 1000) . ' ms');

        // Hm, Scrutinizer complains that $anyRuntimeErrors is always false. But that is not true!
        if ($anyRuntimeErrors) {
            // At least one converter failed
            throw new ConversionFailedException(
                'None of the converters in the stack could convert the image.'
            );
        } else {
            // All converters threw a SystemRequirementsNotMetException
            throw new ConverterNotOperationalException('None of the converters in the stack are operational');
        }
    }
}
