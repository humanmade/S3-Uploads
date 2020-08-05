<?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput;

use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInputException;

class ConverterNotFoundException extends InvalidInputException
{
    public $description = 'The converter does not exist.';
}
