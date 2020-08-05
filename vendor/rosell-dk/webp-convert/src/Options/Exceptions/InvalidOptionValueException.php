<?php

namespace WebPConvert\Options\Exceptions;

use WebPConvert\Exceptions\WebPConvertException;

class InvalidOptionValueException extends WebPConvertException
{
    public $description = 'Invalid option value';
}
