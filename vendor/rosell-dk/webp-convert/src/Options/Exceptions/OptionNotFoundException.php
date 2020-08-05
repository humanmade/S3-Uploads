<?php

namespace WebPConvert\Options\Exceptions;

use WebPConvert\Exceptions\WebPConvertException;

class OptionNotFoundException extends WebPConvertException
{
    public $description = '';
}
