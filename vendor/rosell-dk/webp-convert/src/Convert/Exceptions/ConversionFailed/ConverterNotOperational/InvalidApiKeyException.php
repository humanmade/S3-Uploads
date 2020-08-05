<?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;

class InvalidApiKeyException extends ConverterNotOperationalException
{
    public $description = 'The converter is not operational (access denied)';
}
