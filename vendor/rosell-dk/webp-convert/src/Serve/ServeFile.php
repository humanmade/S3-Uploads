<?php
namespace WebPConvert\Serve;

//use WebPConvert\Serve\Report;
use WebPConvert\Helpers\InputValidator;
use WebPConvert\Options\ArrayOption;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\Options;
use WebPConvert\Options\StringOption;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Exceptions\ServeFailedException;

/**
 * Serve a file (send to standard output)
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ServeFile
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
            new ArrayOption('headers', []),
            new StringOption('cache-control-header', 'public, max-age=31536000')
        );
        foreach ($options as $optionId => $optionValue) {
            $options2->setOrCreateOption($optionId, $optionValue);
        }
        $options2->check();
        $options = $options2->getOptions();

        // headers option
        // --------------

        $headerOptions = new Options();
        $headerOptions->addOptions(
            new BooleanOption('cache-control', false),
            new BooleanOption('content-length', true),
            new BooleanOption('content-type', true),
            new BooleanOption('expires', false),
            new BooleanOption('last-modified', true),
            new BooleanOption('vary-accept', false)
        );
        foreach ($options['headers'] as $optionId => $optionValue) {
            $headerOptions->setOrCreateOption($optionId, $optionValue);
        }
        $options['headers'] = $headerOptions->getOptions();
        return $options;
    }

    /**
     * Serve existing file.
     *
     * @param  string  $filename     File to serve (absolute path)
     * @param  string  $contentType  Content-type (used to set header).
     *                                    Only used when the "set-content-type-header" option is set.
     *                                    Set to ie "image/jpeg" for serving jpeg file.
     * @param  array   $options      Array of named options (optional).
     *       Supported options:
     *       'add-vary-accept-header'  => (boolean)   Whether to add *Vary: Accept* header or not. Default: true.
     *       'set-content-type-header' => (boolean)   Whether to set *Content-Type* header or not. Default: true.
     *       'set-last-modified-header' => (boolean)  Whether to set *Last-Modified* header or not. Default: true.
     *       'set-cache-control-header' => (boolean)  Whether to set *Cache-Control* header or not. Default: true.
     *       'cache-control-header' => string         Cache control header. Default: "public, max-age=86400"
     *
     * @throws ServeFailedException  if serving failed
     * @return  void
     */
    public static function serve($filename, $contentType, $options = [])
    {
        // Check mimetype - this also checks that path is secure and file exists
        InputValidator::checkMimeType($filename, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif'
        ]);

        /*
        if (!file_exists($filename)) {
            Header::addHeader('X-WebP-Convert-Error: Could not read file');
            throw new ServeFailedException('Could not read file');
        }*/

        $options = self::processOptions($options);

        if ($options['headers']['last-modified']) {
            Header::setHeader("Last-Modified: " . gmdate("D, d M Y H:i:s", @filemtime($filename)) ." GMT");
        }

        if ($options['headers']['content-type']) {
            Header::setHeader('Content-Type: ' . $contentType);
        }

        if ($options['headers']['vary-accept']) {
            Header::addHeader('Vary: Accept');
        }

        if (!empty($options['cache-control-header'])) {
            if ($options['headers']['cache-control']) {
                Header::setHeader('Cache-Control: ' . $options['cache-control-header']);
            }
            if ($options['headers']['expires']) {
                // Add exprires header too (#126)
                // Check string for something like this: max-age:86400
                if (preg_match('#max-age\\s*=\\s*(\\d*)#', $options['cache-control-header'], $matches)) {
                    $seconds = $matches[1];
                    Header::setHeader('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + intval($seconds)));
                }
            }
        }

        if ($options['headers']['content-length']) {
            Header::setHeader('Content-Length: ' . filesize($filename));
        }

        if (@readfile($filename) === false) {
            Header::addHeader('X-WebP-Convert-Error: Could not read file');
            throw new ServeFailedException('Could not read file');
        }
    }
}
