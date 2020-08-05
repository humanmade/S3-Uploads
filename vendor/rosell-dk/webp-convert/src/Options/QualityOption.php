<?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Quality option.
 *
 * Quality can be a number between 0-100 or "auto"
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class QualityOption extends Option
{

    public function __construct($id, $defaultValue)
    {
        parent::__construct($id, $defaultValue);
    }

    public function check()
    {
        $value = $this->getValue();
        if (gettype($value) == 'string') {
            if ($value != 'auto') {
                throw new InvalidOptionValueException(
                    'The "quality" option must be either "auto" or a number between 0-100. ' .
                    'A string, different from "auto" was given'
                );
            }
        } elseif (gettype($value) == 'integer') {
            if (($value < 0) || ($value > 100)) {
                throw new InvalidOptionValueException(
                    'The "quality" option must be either "auto" or a number between 0-100. ' .
                        'The number you provided (' . strval($value) . ') is out of range.'
                );
            }
        } else {
            throw new InvalidOptionValueException(
                'The "quality" option must be either "auto" or an integer. ' .
                    'You however provided a value of type: ' . gettype($value)
            );
        }
    }

    public function getValueForPrint()
    {
        if (gettype($this->getValue()) == 'string') {
            return '"' . $this->getValue() . '"';
        }
        return $this->getValue();
    }
}
