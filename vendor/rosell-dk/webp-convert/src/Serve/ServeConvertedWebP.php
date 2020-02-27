<?php
namespace WebPConvert\Serve;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Helpers\InputValidator;
use WebPConvert\Helpers\MimeType;
use WebPConvert\Serve\Exceptions\ServeFailedException;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Report;
use WebPConvert\Serve\ServeFile;
use WebPConvert\Options\ArrayOption;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\Options;
use WebPConvert\Options\SensitiveArrayOption;
use WebPConvert\Options\Exceptions\InvalidOptionTypeException;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;
use WebPConvert\WebPConvert;

/**
 * Serve a converted webp image.
 *
 * The webp that is served might end up being one of these:
 * - a fresh convertion
 * - the destionation
 * - the original
 *
 * Exactly which is a decision based upon options, file sizes and file modification dates
 * (see the serve method of this class for details)
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ServeConvertedWebP
{

    /**
     * Process options.
     *
     * @throws \WebPConvert\Options\Exceptions\InvalidOptionTypeException   If the type of an option is invalid
     * @throws \WebPConvert\Options\Exceptions\InvalidOptionValueException  If the value of an option is invalid
     * @param array $options
     */
    private static function processOptions($options)
    {
        $options2 = new Options();
        $options2->addOptions(
            new BooleanOption('reconvert', false),
            new BooleanOption('serve-original', false),
            new BooleanOption('show-report', false),
            new BooleanOption('suppress-warnings', true),
            new BooleanOption('redirect-to-self-instead-of-serving', false),
            new ArrayOption('serve-image', []),
            new SensitiveArrayOption('convert', [])
        );
        foreach ($options as $optionId => $optionValue) {
            $options2->setOrCreateOption($optionId, $optionValue);
        }
        $options2->check();
        return $options2->getOptions();
    }

    /**
     * Serve original file (source).
     *
     * @param   string  $source                        path to source file
     * @param   array   $serveImageOptions (optional)  options for serving an image
     *                  Supported options:
     *                  - All options supported by ServeFile::serve()
     * @throws  ServeFailedException  if source is not an image or mime type cannot be determined
     * @return  void
     */
    public static function serveOriginal($source, $serveImageOptions = [])
    {
        InputValidator::checkSource($source);
        $contentType = MimeType::getMimeTypeDetectionResult($source);
        if (is_null($contentType)) {
            throw new ServeFailedException('Rejecting to serve original (mime type cannot be determined)');
        } elseif ($contentType === false) {
            throw new ServeFailedException('Rejecting to serve original (it is not an image)');
        } else {
            ServeFile::serve($source, $contentType, $serveImageOptions);
        }
    }

    /**
     * Serve destination file.
     *
     * TODO: SHould this really be public?
     *
     * @param   string  $destination                   path to destination file
     * @param   array   $serveImageOptions (optional)  options for serving (such as which headers to add)
     *       Supported options:
     *       - All options supported by ServeFile::serve()
     * @return  void
     */
    public static function serveDestination($destination, $serveImageOptions = [])
    {
        InputValidator::checkDestination($destination);
        ServeFile::serve($destination, 'image/webp', $serveImageOptions);
    }


    public static function warningHandler()
    {
        // do nothing! - as we do not return anything, the warning is suppressed
    }

    /**
     * Serve converted webp.
     *
     * Serve a converted webp. If a file already exists at the destination, that is served (unless it is
     * older than the source - in that case a fresh conversion will be made, or the file at the destination
     * is larger than the source - in that case the source is served). Some options may alter this logic.
     * In case no file exists at the destination, a fresh conversion is made and served.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for serving/converting
     *       Supported options:
     *       'show-report'     => (boolean)   If true, the decision will always be 'report'
     *       'serve-original'  => (boolean)   If true, the decision will be 'source' (unless above option is set)
     *       'reconvert     '  => (boolean)   If true, the decision will be 'fresh-conversion' (unless one of the
     *                                        above options is set)
     *       - All options supported by WebPConvert::convert()
     *       - All options supported by ServeFile::serve()
     * @param  \WebPConvert\Loggers\BaseLogger $serveLogger (optional)
     * @param  \WebPConvert\Loggers\BaseLogger $convertLogger (optional)
     *
     * @throws  \WebPConvert\Exceptions\WebPConvertException  If something went wrong.
     * @return  void
     */
    public static function serve($source, $destination, $options = [], $serveLogger = null, $convertLogger = null)
    {
        InputValidator::checkSourceAndDestination($source, $destination);

        $options = self::processOptions($options);

        if ($options['suppress-warnings']) {
            set_error_handler(
                array('\\WebPConvert\\Serve\\ServeConvertedWebP', "warningHandler"),
                E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE
            );
        }


        //$options = array_merge(self::$defaultOptions, $options);

        // Step 1: Is there a file at the destination? If not, trigger conversion
        // However 1: if "show-report" option is set, serve the report instead
        // However 2: "reconvert" option should also trigger conversion
        if ($options['show-report']) {
            Header::addLogHeader('Showing report', $serveLogger);
            Report::convertAndReport($source, $destination, $options);
            return;
        }

        if (!@file_exists($destination)) {
            Header::addLogHeader('Converting (there were no file at destination)', $serveLogger);
            WebPConvert::convert($source, $destination, $options['convert'], $convertLogger);
        } elseif ($options['reconvert']) {
            Header::addLogHeader('Converting (told to reconvert)', $serveLogger);
            WebPConvert::convert($source, $destination, $options['convert'], $convertLogger);
        } else {
            // Step 2: Is the destination older than the source?
            //         If yes, trigger conversion (deleting destination is implicit)
            $timestampSource = @filemtime($source);
            $timestampDestination = @filemtime($destination);
            if (($timestampSource !== false) &&
                ($timestampDestination !== false) &&
                ($timestampSource > $timestampDestination)) {
                    Header::addLogHeader('Converting (destination was older than the source)', $serveLogger);
                    WebPConvert::convert($source, $destination, $options['convert'], $convertLogger);
            }
        }

        // Step 3: Serve the smallest file (destination or source)
        // However, first check if 'serve-original' is set
        if ($options['serve-original']) {
            Header::addLogHeader('Serving original (told to)', $serveLogger);
            self::serveOriginal($source, $options['serve-image']);
            return;
        }

        if ($options['redirect-to-self-instead-of-serving']) {
            Header::addLogHeader(
                'Redirecting to self! ' .
                    '(hope you got redirection to existing webps set up, otherwise you will get a loop!)',
                $serveLogger
            );
            header('Location: ?fresh', 302);
            return;
        }

        $filesizeDestination = @filesize($destination);
        $filesizeSource = @filesize($source);
        if (($filesizeSource !== false) &&
            ($filesizeDestination !== false) &&
            ($filesizeDestination > $filesizeSource)) {
                Header::addLogHeader('Serving original (it is smaller)', $serveLogger);
                self::serveOriginal($source, $options['serve-image']);
                return;
        }

        Header::addLogHeader('Serving converted file', $serveLogger);
        self::serveDestination($destination, $options['serve-image']);
    }
}
