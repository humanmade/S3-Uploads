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
class IntegerOption extends Option
{

    protected $minValue;
    protected $maxValue;

    /**
     * Constructor.
     *
     * @param   string   $id              id of the option
     * @param   integer  $defaultValue    default value for the option
     * @throws  InvalidOptionValueException  if the default value cannot pass the check
     * @return  void
     */
    public function __construct($id, $defaultValue, $minValue = null, $maxValue = null)
    {
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
        parent::__construct($id, $defaultValue);
    }

    protected function checkMin()
    {
        if (!is_null($this->minValue) && $this->getValue() < $this->minValue) {
            throw new InvalidOptionValueException(
                '"' . $this->id . '" option must be set to minimum ' . $this->minValue . '. ' .
                'It was however set to: ' . $this->getValue()
            );
        }
    }

    protected function checkMax()
    {
        if (!is_null($this->maxValue) && $this->getValue() > $this->maxValue) {
            throw new InvalidOptionValueException(
                '"' . $this->id . '" option must be set to max ' . $this->maxValue . '. ' .
                'It was however set to: ' . $this->getValue()
            );
        }
    }

    protected function checkMinMax()
    {
        $this->checkMin();
        $this->checkMax();
    }

    public function check()
    {
        $this->checkType('integer');
        $this->checkMinMax();
    }
}
