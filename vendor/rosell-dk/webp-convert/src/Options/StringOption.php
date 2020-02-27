<?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Abstract option class
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class StringOption extends Option
{

    public $allowedValues;

    public function __construct($id, $defaultValue, $allowedValues = null)
    {
        $this->allowedValues = $allowedValues;
        parent::__construct($id, $defaultValue);
    }

    public function check()
    {
        $this->checkType('string');

        if (!is_null($this->allowedValues) && (!in_array($this->getValue(), $this->allowedValues))) {
            throw new InvalidOptionValueException(
                '"' . $this->id . '" option must be on of these values: ' .
                '[' . implode(', ', $this->allowedValues) . ']. ' .
                'It was however set to: "' . $this->getValue() . '"'
            );
        }
    }

    public function getValueForPrint()
    {
        return '"' . $this->getValue() . '"';
    }
}
