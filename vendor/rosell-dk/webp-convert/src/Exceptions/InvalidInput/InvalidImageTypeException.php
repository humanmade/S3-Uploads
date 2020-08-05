<?php

namespace WebPConvert\Exceptions\InvalidInput;

use WebPConvert\Exceptions\InvalidInputException;

class InvalidImageTypeException extends InvalidInputException
{
    public $description = 'The converter does not handle the supplied image type';
}
