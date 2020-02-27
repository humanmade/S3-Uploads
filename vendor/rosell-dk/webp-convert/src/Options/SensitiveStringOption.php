<?php

namespace WebPConvert\Options;

use WebPConvert\Options\StringOption;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Abstract option class
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class SensitiveStringOption extends StringOption
{

    public function __construct($id, $defaultValue, $allowedValues = null)
    {
        parent::__construct($id, $defaultValue, $allowedValues);
    }

    public function check()
    {
        parent::check();
    }

    public function getValueForPrint()
    {
        if (strlen($this->getValue()) == 0) {
            return '""';
        }
        return '*****';
    }
}
