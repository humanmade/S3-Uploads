<?php

namespace WebPConvert\Exceptions;

/**
 *  WebPConvertException is the base exception for all exceptions in this library.
 *
 *  Note that the parameters for the constructor differs from that of the Exception class.
 *  We do not use exception code here, but are instead allowing two version of the error message:
 *  a short version and a long version.
 *  The short version may not contain special characters or dynamic content.
 *  The detailed version may.
 *  If the detailed version isn't provided, getDetailedMessage will return the short version.
 *
 */
class WebPConvertException extends \Exception
{
    public $description = '';
    protected $detailedMessage;
    protected $shortMessage;

    public function getDetailedMessage()
    {
        return $this->detailedMessage;
    }

    public function getShortMessage()
    {
        return $this->shortMessage;
    }

    public function __construct($shortMessage = "", $detailedMessage = "", $previous = null)
    {
        $detailedMessage = ($detailedMessage != '') ? $detailedMessage : $shortMessage;
        $this->detailedMessage = $detailedMessage;
        $this->shortMessage = $shortMessage;

        parent::__construct(
            $detailedMessage,
            0,
            $previous
        );
    }
}
