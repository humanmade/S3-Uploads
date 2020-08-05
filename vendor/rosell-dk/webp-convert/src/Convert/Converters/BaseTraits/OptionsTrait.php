<?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Converters\Stack;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;
use WebPConvert\Options\Exceptions\InvalidOptionTypeException;

use WebPConvert\Options\ArrayOption;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\GhostOption;
use WebPConvert\Options\IntegerOption;
use WebPConvert\Options\IntegerOrNullOption;
use WebPConvert\Options\MetadataOption;
use WebPConvert\Options\Options;
use WebPConvert\Options\StringOption;
use WebPConvert\Options\QualityOption;

/**
 * Trait for handling options
 *
 * This trait is currently only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning options.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait OptionsTrait
{

    abstract public function log($msg, $style = '');
    abstract public function logLn($msg, $style = '');
    abstract protected function getMimeTypeOfSource();

    /** @var array  Provided conversion options */
    public $providedOptions;

    /** @var array  Calculated conversion options (merge of default options and provided options)*/
    protected $options;

    /** @var Options  */
    protected $options2;


    /**
     *  Create options.
     *
     *  The options created here will be available to all converters.
     *  Individual converters may add options by overriding this method.
     *
     *  @return void
     */
    protected function createOptions()
    {
        $isPng = ($this->getMimeTypeOfSource() == 'image/png');

        $this->options2 = new Options();
        $this->options2->addOptions(
            new IntegerOption('alpha-quality', 85, 0, 100),
            new BooleanOption('auto-filter', false),
            new IntegerOption('default-quality', ($isPng ? 85 : 75), 0, 100),
            new StringOption('encoding', 'auto', ['lossy', 'lossless', 'auto']),
            new BooleanOption('low-memory', false),
            new BooleanOption('log-call-arguments', false),
            new IntegerOption('max-quality', 85, 0, 100),
            new MetadataOption('metadata', 'none'),
            new IntegerOption('method', 6, 0, 6),
            new IntegerOption('near-lossless', 60, 0, 100),
            new StringOption('preset', 'none', ['none', 'default', 'photo', 'picture', 'drawing', 'icon', 'text']),
            new QualityOption('quality', ($isPng ? 85 : 'auto')),
            new IntegerOrNullOption('size-in-percentage', null, 0, 100),
            new BooleanOption('skip', false),
            new BooleanOption('use-nice', false),
            new ArrayOption('jpeg', []),
            new ArrayOption('png', [])
        );
    }

    /**
     * Set "provided options" (options provided by the user when calling convert().
     *
     * This also calculates the protected options array, by merging in the default options, merging
     * jpeg and png options and merging prefixed options (such as 'vips-quality').
     * The resulting options array are set in the protected property $this->options and can be
     * retrieved using the public ::getOptions() function.
     *
     * @param   array $providedOptions (optional)
     * @return  void
     */
    public function setProvidedOptions($providedOptions = [])
    {
        $this->createOptions();

        $this->providedOptions = $providedOptions;

        if (isset($this->providedOptions['png'])) {
            if ($this->getMimeTypeOfSource() == 'image/png') {
                $this->providedOptions = array_merge($this->providedOptions, $this->providedOptions['png']);
//                $this->logLn(print_r($this->providedOptions, true));
                unset($this->providedOptions['png']);
            }
        }

        if (isset($this->providedOptions['jpeg'])) {
            if ($this->getMimeTypeOfSource() == 'image/jpeg') {
                $this->providedOptions = array_merge($this->providedOptions, $this->providedOptions['jpeg']);
                unset($this->providedOptions['jpeg']);
            }
        }

        // merge down converter-prefixed options
        $converterId = self::getConverterId();
        $strLen = strlen($converterId);
        foreach ($this->providedOptions as $optionKey => $optionValue) {
            if (substr($optionKey, 0, $strLen + 1) == ($converterId . '-')) {
                $this->providedOptions[substr($optionKey, $strLen + 1)] = $optionValue;
            }
        }

        // Create options (Option objects)
        foreach ($this->providedOptions as $optionId => $optionValue) {
            $this->options2->setOrCreateOption($optionId, $optionValue);
        }
        //$this->logLn(print_r($this->options2->getOptions(), true));
//$this->logLn($this->options2->getOption('hello'));

        // Create flat associative array of options
        $this->options = $this->options2->getOptions();

        // -  Merge $defaultOptions into provided options
        //$this->options = array_merge($this->getDefaultOptions(), $this->providedOptions);

        //$this->logOptions();
    }

    /**
     * Get the resulting options after merging provided options with default options.
     *
     * Note that the defaults depends on the mime type of the source. For example, the default value for quality
     * is "auto" for jpegs, and 85 for pngs.
     *
     * @return array  An associative array of options: ['metadata' => 'none', ...]
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Change an option specifically.
     *
     * This method is probably rarely neeeded. We are using it to change the "encoding" option temporarily
     * in the EncodingAutoTrait.
     *
     * @param  string  $id      Id of option (ie "metadata")
     * @param  mixed   $value   The new value.
     * @return void
     */
    protected function setOption($id, $value)
    {
        $this->options[$id] = $value;
        $this->options2->setOrCreateOption($id, $value);
    }

    /**
     *  Check options.
     *
     *  @throws InvalidOptionTypeException   if an option have wrong type
     *  @throws InvalidOptionValueException  if an option value is out of range
     *  @throws ConversionSkippedException   if 'skip' option is set to true
     *  @return void
     */
    protected function checkOptions()
    {
        $this->options2->check();

        if ($this->options['skip']) {
            if (($this->getMimeTypeOfSource() == 'image/png') && isset($this->options['png']['skip'])) {
                throw new ConversionSkippedException(
                    'skipped conversion (configured to do so for PNG)'
                );
            } else {
                throw new ConversionSkippedException(
                    'skipped conversion (configured to do so)'
                );
            }
        }
    }

    public function logOptions()
    {
        $this->logLn('');
        $this->logLn('Options:');
        $this->logLn('------------');
        $this->logLn(
            'The following options have been set explicitly. ' .
            'Note: it is the resulting options after merging down the "jpeg" and "png" options and any ' .
            'converter-prefixed options.'
        );
        $this->logLn('- source: ' . $this->source);
        $this->logLn('- destination: ' . $this->destination);

        $unsupported = $this->getUnsupportedDefaultOptions();
        //$this->logLn('Unsupported:' . print_r($this->getUnsupportedDefaultOptions(), true));
        $ignored = [];
        $implicit = [];
        foreach ($this->options2->getOptionsMap() as $id => $option) {
            if (($id == 'png') || ($id == 'jpeg')) {
                continue;
            }
            if ($option->isValueExplicitlySet()) {
                if (($option instanceof GhostOption) || in_array($id, $unsupported)) {
                    //$this->log(' (note: this option is ignored by this converter)');
                    if (($id != '_skip_input_check') && ($id != '_suppress_success_message')) {
                        $ignored[] = $option;
                    }
                } else {
                    $this->log('- ' . $id . ': ');
                    $this->log($option->getValueForPrint());
                    $this->logLn('');
                }
            } else {
                if (($option instanceof GhostOption) || in_array($id, $unsupported)) {
                } else {
                    $implicit[] = $option;
                }
            }
        }

        if (count($implicit) > 0) {
            $this->logLn('');
            $this->logLn(
                'The following options have not been explicitly set, so using the following defaults:'
            );
            foreach ($implicit as $option) {
                $this->log('- ' . $option->getId() . ': ');
                $this->log($option->getValueForPrint());
                $this->logLn('');
            }
        }
        if (count($ignored) > 0) {
            $this->logLn('');
            if ($this instanceof Stack) {
                $this->logLn(
                    'The following options were supplied and are passed on to the converters in the stack:'
                );
                foreach ($ignored as $option) {
                    $this->log('- ' . $option->getId() . ': ');
                    $this->log($option->getValueForPrint());
                    $this->logLn('');
                }
            } else {
                $this->logLn(
                    'The following options were supplied but are ignored because they are not supported by this ' .
                        'converter:'
                );
                foreach ($ignored as $option) {
                    $this->logLn('- ' . $option->getId());
                }
            }
        }
        $this->logLn('------------');
    }

    // to be overridden by converters
    protected function getUnsupportedDefaultOptions()
    {
        return [];
    }
}
