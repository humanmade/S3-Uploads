<?php

namespace WebPConvert;

//use WebPConvert\Convert\Converters\ConverterHelper;
use WebPConvert\Convert\Converters\Stack;
//use WebPConvert\Serve\ServeExistingOrHandOver;
use WebPConvert\Serve\ServeConvertedWebP;
use WebPConvert\Serve\ServeConvertedWebPWithErrorHandling;

/**
 * Convert images to webp and/or serve them.
 *
 * This class is just a couple of convenience methods for doing conversion and/or
 * serving.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class WebPConvert
{

    /**
     * Convert jpeg or png into webp
     *
     * Convenience method for calling Stack::convert.
     *
     * @param  string  $source       The image to convert (absolute,no backslashes)
     *                               Image must be jpeg or png.
     * @param  string  $destination  Where to store the converted file (absolute path, no backslashes).
     * @param  array   $options      (optional) Array of named options
     *                               The options are documented here:
     *                            https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md
     * @param  \WebPConvert\Loggers\BaseLogger $logger (optional)
     *
     * @throws  \WebPConvert\Convert\Exceptions\ConversionFailedException   in case conversion fails
     * @return  void
     */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        Stack::convert($source, $destination, $options, $logger);
    }

    /**
     * Serve webp image, converting first if neccessary.
     *
     * If an image already exists, it will be served, unless it is older or larger than the source. (If it is larger,
     * the original is served, if it is older, the existing webp will be deleted and a fresh conversion will be made
     * and served). In case of error, the action indicated in the 'fail' option will be triggered (default is to serve
     * the original). Look up the ServeConvertedWebP:serve() and the ServeConvertedWebPWithErrorHandling::serve()
     * methods to learn more.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for serving/converting. The options are documented in the
     *                                       ServeConvertedWebPWithErrorHandling::serve() method
     * @param  \WebPConvert\Loggers\BaseLogger $serveLogger (optional)
     * @param  \WebPConvert\Loggers\BaseLogger $convertLogger (optional)
     * @return void
     */
    public static function serveConverted(
        $source,
        $destination,
        $options = [],
        $serveLogger = null,
        $convertLogger = null
    ) {
        //return ServeExistingOrHandOver::serveConverted($source, $destination, $options);
        //if (isset($options['handle-errors']) && $options['handle-errors'] === true) {
        if (isset($options['fail']) && ($options['fail'] != 'throw')) {
            ServeConvertedWebPWithErrorHandling::serve($source, $destination, $options, $serveLogger, $convertLogger);
        } else {
            ServeConvertedWebP::serve($source, $destination, $options, $serveLogger, $convertLogger);
        }
    }
}
