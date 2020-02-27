<?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;

class SystemRequirementsNotMetException extends ConverterNotOperationalException
{
    public $description = 'The converter is not operational (system requirements not met)';
}
