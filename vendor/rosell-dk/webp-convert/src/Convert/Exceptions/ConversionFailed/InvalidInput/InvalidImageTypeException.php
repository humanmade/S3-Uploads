<?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput;

use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInputException;

class InvalidImageTypeException extends InvalidInputException
{
    public $description = 'The converter does not handle the supplied image type';
}
