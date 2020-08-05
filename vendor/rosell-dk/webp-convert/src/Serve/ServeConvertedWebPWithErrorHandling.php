<?php
namespace WebPConvert\Serve;

use WebPConvert\Helpers\InputValidator;
use WebPConvert\Options\Options;
use WebPConvert\Options\StringOption;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Report;
use WebPConvert\Serve\ServeConvertedWeb;
use WebPConvert\Serve\Exceptions\ServeFailedException;
use WebPConvert\Exceptions\WebPConvertException;

/**
 * Serve a converted webp image and handle errors.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ServeConvertedWebPWithErrorHandling
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
            new StringOption('fail', 'original', ['original', '404', 'throw', 'report']),
            new StringOption('fail-when-fail-fails', 'throw', ['original', '404', 'throw', 'report'])
        );
        foreach ($options as $optionId => $optionValue) {
            $options2->setOrCreateOption($optionId, $optionValue);
        }
        $options2->check();
        return $options2->getOptions();
    }

    /**
     *  Add headers for preventing caching.
     *
     *  @return  void
     */
    private static function addHeadersPreventingCaching()
    {
        Header::setHeader("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        Header::addHeader("Cache-Control: post-check=0, pre-check=0");
        Header::setHeader("Pragma: no-cache");
    }

    /**
     * Perform fail action.
     *
     * @param  string  $fail                Action to perform (original | 404 | report)
     * @param  string  $failIfFailFails     Action to perform if $fail action fails
     * @param  string  $source              path to source file
     * @param  string  $destination         path to destination
     * @param  array   $options (optional)  options for serving/converting
     * @param  \Exception  $e               exception that was thrown when trying to serve
     * @param   string  $serveClass         (optional) Full class name to a class that has a serveOriginal() method
     * @return void
     */
    public static function performFailAction($fail, $failIfFailFails, $source, $destination, $options, $e, $serveClass)
    {
        self::addHeadersPreventingCaching();

        Header::addLogHeader('Performing fail action: ' . $fail);

        switch ($fail) {
            case 'original':
                try {
                    //ServeConvertedWebP::serveOriginal($source, $options);
                    call_user_func($serveClass . '::serveOriginal', $source, $options);
                } catch (\Exception $e) {
                    self::performFailAction($failIfFailFails, '404', $source, $destination, $options, $e, $serveClass);
                }
                break;

            case '404':
                $protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'HTTP/1.0';
                Header::setHeader($protocol . " 404 Not Found");
                break;

            case 'report':
                $options['show-report'] = true;
                Report::convertAndReport($source, $destination, $options);
                break;

            case 'throw':
                throw $e;
                //break;  commented out as phpstan complains. But do something else complain now?

            case 'report-as-image':
                // TODO: Implement or discard ?
                break;
        }
    }

    /**
     * Serve webp image and handle errors as specified in the 'fail' option.
     *
     * This method basically wraps ServeConvertedWebP:serve in order to provide exception handling.
     * The error handling is set with the 'fail' option and can be either '404', 'original' or 'report'.
     * If set to '404', errors results in 404 Not Found headers being issued. If set to 'original', an
     * error results in the original being served.
     * Look up the ServeConvertedWebP:serve method to learn more.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for serving/converting
     *       Supported options:
     *       - 'fail' => (string)    Action to take on failure (404 | original | report | throw).
     *               "404" or "throw" is recommended for development and "original" is recommended for production.
     *               Default: 'original'.
     *       - 'fail-when-fail-fails'  => (string) Action to take if fail action also fails. Default: '404'.
     *       - All options supported by WebPConvert::convert()
     *       - All options supported by ServeFile::serve()
     *       - All options supported by DecideWhatToServe::decide)
     * @param  \WebPConvert\Loggers\BaseLogger $serveLogger (optional)
     * @param  \WebPConvert\Loggers\BaseLogger $convertLogger (optional)
     * @param   string  $serveClass     (optional) Full class name to a class that has a serve() method and a
     *                                  serveOriginal() method
     * @return  void
     */
    public static function serve(
        $source,
        $destination,
        $options = [],
        $serveLogger = null,
        $convertLogger = null,
        $serveClass = '\\WebPConvert\\Serve\\ServeConvertedWebP'
    ) {

        $options = self::processOptions($options);
        try {
            InputValidator::checkSourceAndDestination($source, $destination);
            //ServeConvertedWebP::serve($source, $destination, $options, $serveLogger);
            call_user_func($serveClass . '::serve', $source, $destination, $options, $serveLogger, $convertLogger);
        } catch (\Exception $e) {
            if ($e instanceof \WebPConvert\Exceptions\WebPConvertException) {
                Header::addLogHeader($e->getShortMessage(), $serveLogger);
            }

            self::performFailAction(
                $options['fail'],
                $options['fail-when-fail-fails'],
                $source,
                $destination,
                $options,
                $e,
                $serveClass
            );
        }
    }
}
