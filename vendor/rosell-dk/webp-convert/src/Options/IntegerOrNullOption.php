<?php

namespace WebPConvert\Options;

use WebPConvert\Options\IntegerOption;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Abstract option class
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class IntegerOrNullOption extends IntegerOption
{

    public function __construct($id, $defaultValue, $minValue = null, $maxValue = null)
    {
        parent::__construct($id, $defaultValue, $minValue, $maxValue);
    }

    public function check()
    {
        $this->checkMinMax();

        $valueType = gettype($this->getValue());
        if (!in_array($valueType, ['integer', 'NULL'])) {
            throw new InvalidOptionValueException(
                'The "' . $this->id . '" option must be either integer or NULL. ' .
                    'You however provided a value of type: ' . $valueType
            );
        }
    }

    public function getValueForPrint()
    {
        if (gettype($this->getValue() == 'NULL')) {
            return 'null (not set)';
        }
        return parent::getValueForPrint();
    }
}
