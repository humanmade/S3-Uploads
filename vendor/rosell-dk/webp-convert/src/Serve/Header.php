<?php
namespace WebPConvert\Serve;

/**
 * Add / Set HTTP header.
 *
 * This class does nothing more than adding two convenience functions for calling the "header" function.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Header
{
    /**
     * Convenience function for adding header (append).
     *
     * @param  string  $header  The header to add.
     * @return void
     */
    public static function addHeader($header)
    {
        header($header, false);
    }

    /**
     * Convenience function for replacing header.
     *
     * @param  string  $header  The header to set.
     * @return void
     */
    public static function setHeader($header)
    {
        header($header, true);
    }

    /**
     * Add log header and optionally send it to a logger as well.
     *
     * @param  string  $msg  Message to add to "X-WebP-Convert-Log" header
     * @param  \WebPConvert\Loggers\BaseLogger $logger (optional)
     * @return void
     */
    public static function addLogHeader($msg, $logger = null)
    {
        self::addHeader('X-WebP-Convert-Log: ' . $msg);
        if (!is_null($logger)) {
            $logger->logLn($msg);
        }
    }
}
