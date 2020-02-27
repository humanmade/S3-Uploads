<?php

// TODO:
// Read this: https://sourcemaking.com/design_patterns/strategy

namespace WebPConvert\Convert\Converters;

use WebPConvert\Helpers\InputValidator;
use WebPConvert\Helpers\MimeType;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Converters\BaseTraits\AutoQualityTrait;
use WebPConvert\Convert\Converters\BaseTraits\DestinationPreparationTrait;
use WebPConvert\Convert\Converters\BaseTraits\LoggerTrait;
use WebPConvert\Convert\Converters\BaseTraits\OptionsTrait;
use WebPConvert\Convert\Converters\BaseTraits\WarningLoggerTrait;
use WebPConvert\Exceptions\WebPConvertException;
use WebPConvert\Loggers\BaseLogger;

/**
 * Base for all converter classes.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
abstract class AbstractConverter
{
    use AutoQualityTrait;
    use OptionsTrait;
    use WarningLoggerTrait;
    use DestinationPreparationTrait;
    use LoggerTrait;

    /**
     * The actual conversion is be done by a concrete converter extending this class.
     *
     * At the stage this method is called, the abstract converter has taken preparational steps.
     * - It has created the destination folder (if neccesary)
     * - It has checked the input (valid mime type)
     * - It has set up an error handler, mostly in order to catch and log warnings during the doConvert fase
     *
     * Note: This method is not meant to be called from the outside. Use the static *convert* method for converting
     *       or, if you wish, create an instance with ::createInstance() and then call ::doConvert()
     *
     * @throws ConversionFailedException in case conversion failed in an antipiciated way (or subclass)
     * @throws \Exception in case conversion failed in an unantipiciated way
     */
    abstract protected function doActualConvert();

    /**
     * Whether or not the converter supports lossless encoding (even for jpegs)
     *
     * PS: Converters that supports lossless encoding all use the EncodingAutoTrait, which
     * overrides this function.
     *
     * @return  boolean  Whether the converter supports lossless encoding (even for jpegs).
     */
    public function supportsLossless()
    {
        return false;
    }

    /** @var string  The filename of the image to convert (complete path) */
    protected $source;

    /** @var string  Where to save the webp (complete path) */
    protected $destination;

    /**
     * Check basis operationality
     *
     * Converters may override this method for the purpose of performing basic operationaly checks. It is for
     * running general operation checks for a conversion method.
     * If some requirement is not met, it should throw a ConverterNotOperationalException (or subtype)
     *
     * The method is called internally right before calling doActualConvert() method.
     * - It SHOULD take options into account when relevant. For example, a missing api key for a
     *   cloud converter should be detected here
     * - It should NOT take the actual filename into consideration, as the purpose is *general*
     *   For that pupose, converters should override checkConvertability
     *   Also note that doConvert method is allowed to throw ConverterNotOperationalException too.
     *
     * @return  void
     */
    public function checkOperationality()
    {
    }

    /**
     * Converters may override this for the purpose of performing checks on the concrete file.
     *
     * This can for example be used for rejecting big uploads in cloud converters or rejecting unsupported
     * image types.
     *
     * @return  void
     */
    public function checkConvertability()
    {
    }

    /**
     * Constructor.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for conversion
     * @param   BaseLogger $logger (optional)
     */
    final public function __construct($source, $destination, $options = [], $logger = null)
    {
        InputValidator::checkSourceAndDestination($source, $destination);

        $this->source = $source;
        $this->destination = $destination;

        $this->setLogger($logger);
        $this->setProvidedOptions($options);

        if (!isset($this->options['_skip_input_check'])) {
            $this->log('WebP Convert 2.3.0', 'italic');
            $this->logLn(' ignited.');
            $this->logLn('- PHP version: ' . phpversion());
            if (isset($_SERVER['SERVER_SOFTWARE'])) {
                $this->logLn('- Server software: ' . $_SERVER['SERVER_SOFTWARE']);
            }
            $this->logLn('');
            $this->logLn(self::getConverterDisplayName() . ' converter ignited');
        }
    }

    /**
     * Get source.
     *
     * @return string  The source.
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get destination.
     *
     * @return string  The destination.
     */
    public function getDestination()
    {
        return $this->destination;
    }

    /**
     * Set destination.
     *
     * @param   string  $destination         path to destination
     * @return  void
     */
    public function setDestination($destination)
    {
        $this->destination = $destination;
    }


    /**
     *  Get converter name for display (defaults to the class name (short)).
     *
     *  Converters can override this.
     *
     * @return string  A display name, ie "Gd"
     */
    protected static function getConverterDisplayName()
    {
        // https://stackoverflow.com/questions/19901850/how-do-i-get-an-objects-unqualified-short-class-name/25308464
        return substr(strrchr('\\' . static::class, '\\'), 1);
    }


    /**
     *  Get converter id (defaults to the class name lowercased)
     *
     *  Converters can override this.
     *
     * @return string  A display name, ie "Gd"
     */
    protected static function getConverterId()
    {
        return strtolower(self::getConverterDisplayName());
    }


    /**
     * Create an instance of this class
     *
     * @param  string  $source       The path to the file to convert
     * @param  string  $destination  The path to save the converted file to
     * @param  array   $options      (optional)
     * @param  \WebPConvert\Loggers\BaseLogger   $logger       (optional)
     *
     * @return static
     */
    public static function createInstance($source, $destination, $options = [], $logger = null)
    {
        return new static($source, $destination, $options, $logger);
    }

    protected function logReduction($source, $destination)
    {
        $sourceSize = filesize($source);
        $destSize = filesize($destination);
        $this->log(round(($sourceSize - $destSize)/$sourceSize * 100) . '% ');
        if ($sourceSize < 10000) {
            $this->logLn('(went from ' . strval($sourceSize) . ' bytes to '. strval($destSize) . ' bytes)');
        } else {
            $this->logLn('(went from ' . round($sourceSize/1024) . ' kb to ' . round($destSize/1024) . ' kb)');
        }
    }

    /**
     * Run conversion.
     *
     * @return void
     */
    private function doConvertImplementation()
    {
        $beginTime = microtime(true);

        $this->activateWarningLogger();

        $this->checkOptions();

        // Prepare destination folder
        $this->createWritableDestinationFolder();
        $this->removeExistingDestinationIfExists();

        if (!isset($this->options['_skip_input_check'])) {
            // Check that a file can be written to destination
            $this->checkDestinationWritable();
        }

        $this->checkOperationality();
        $this->checkConvertability();

        if ($this->options['log-call-arguments']) {
            $this->logOptions();
            $this->logLn('');
        }

        $this->runActualConvert();

        $source = $this->source;
        $destination = $this->destination;

        if (!@file_exists($destination)) {
            throw new ConversionFailedException('Destination file is not there: ' . $destination);
        } elseif (@filesize($destination) === 0) {
            unlink($destination);
            throw new ConversionFailedException('Destination file was completely empty');
        } else {
            if (!isset($this->options['_suppress_success_message'])) {
                $this->ln();
                $this->log('Converted image in ' . round((microtime(true) - $beginTime) * 1000) . ' ms');

                $sourceSize = @filesize($source);
                if ($sourceSize !== false) {
                    $this->log(', reducing file size with ');
                    $this->logReduction($source, $destination);
                }
            }
        }

        $this->deactivateWarningLogger();
    }

    //private function logEx
    /**
     * Start conversion.
     *
     * Usually you would rather call the static convert method, but alternatively you can call
     * call ::createInstance to get an instance and then ::doConvert().
     *
     * @return void
     */
    public function doConvert()
    {
        try {
            //trigger_error('hello', E_USER_ERROR);
            $this->doConvertImplementation();
        } catch (WebPConvertException $e) {
            $this->logLn('');
            /*
            if (isset($e->description) && ($e->description != '')) {
                $this->log('Error: ' . $e->description . '. ', 'bold');
            } else {
                $this->log('Error: ', 'bold');
            }
            */
            $this->log('Error: ', 'bold');
            $this->logLn($e->getMessage(), 'bold');
            throw $e;
        } catch (\Exception $e) {
            $className = get_class($e);

            $classNameParts = explode("\\", $className);
            $shortClassName = array_pop($classNameParts);

            $this->logLn('');
            $this->logLn($shortClassName . ' thrown in ' . $e->getFile() . ':' . $e->getLine(), 'bold');
            $this->logLn('Message: "' . $e->getMessage() . '"', 'bold');
            //$this->logLn('Exception class: ' . $className);

            $this->logLn('Trace:');
            foreach ($e->getTrace() as $trace) {
                //$this->logLn(print_r($trace, true));
                if (isset($trace['file']) && isset($trace['line'])) {
                    $this->logLn(
                        $trace['file'] . ':' . $trace['line']
                    );
                }
            }
            throw $e;
        } /*catch (\Error $e) {
            $this->logLn('ERROR');
        }*/
    }

    /**
     * Runs the actual conversion (after setup and checks)
     * Simply calls the doActualConvert() of the actual converter.
     * However, in the EncodingAutoTrait, this method is overridden to make two conversions
     * and select the smallest.
     *
     * @return void
     */
    protected function runActualConvert()
    {
        $this->doActualConvert();
    }

    /**
     * Convert an image to webp.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for conversion
     * @param   BaseLogger $logger (optional)
     *
     * @throws  ConversionFailedException   in case conversion fails in an antipiciated way
     * @throws  \Exception   in case conversion fails in an unantipiciated way
     * @return  void
     */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        $c = self::createInstance($source, $destination, $options, $logger);
        $c->doConvert();
        //echo $instance->id;
    }

    /**
     * Get mime type for image (best guess).
     *
     * It falls back to using file extension. If that fails too, false is returned
     *
     * PS: Is it a security risk to fall back on file extension?
     * - By setting file extension to "jpg", one can lure our library into trying to convert a file, which isn't a jpg.
     * hmm, seems very unlikely, though not unthinkable that one of the converters could be exploited
     *
     * @return  string|false|null mimetype (if it is an image, and type could be determined / guessed),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
     */
    public function getMimeTypeOfSource()
    {
        return MimeType::getMimeTypeDetectionResult($this->source);
    }
}
