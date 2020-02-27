<?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class ConverterNotOperationalException extends ConversionFailedException
{
    public $description = 'The converter is not operational';
}
