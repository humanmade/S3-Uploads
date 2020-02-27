<?php

namespace WebPConvert\Options\Exceptions;

use WebPConvert\Exceptions\WebPConvertException;

class InvalidOptionTypeException extends WebPConvertException
{
    public $description = 'Invalid option type';
}
