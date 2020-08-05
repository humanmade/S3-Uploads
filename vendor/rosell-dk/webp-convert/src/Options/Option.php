<?php

namespace WebPConvert\Options;

use WebPConvert\Options\Exceptions\InvalidOptionTypeException;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * (base) option class.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Option
{
    /** @var string  The id of the option */
    protected $id;

    /** @var mixed  The default value of the option */
    protected $defaultValue;

    /** @var mixed  The value of the option */
    protected $value;

    /** @var boolean  Whether the value has been explicitly set */
    protected $isExplicitlySet = false;

    /**
     * Constructor.
     *
     * @param   string  $id              id of the option
     * @param   mixed   $defaultValue    default value for the option
     * @throws  InvalidOptionValueException  if the default value cannot pass the check
     * @throws  InvalidOptionTypeException   if the default value is wrong type
     * @return  void
     */
    public function __construct($id, $defaultValue)
    {
        $this->id = $id;
        $this->defaultValue = $defaultValue;

        // Check that default value is ok
        $this->check();
    }

    /**
     * Get Id.
     *
     * @return  string  The id of the option
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get default value.
     *
     * @return  mixed  The default value for the option
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }


    /**
     * Get value, or default value if value has not been explicitly set.
     *
     * @return  mixed  The value/default value
     */
    public function getValue()
    {
        if (!$this->isExplicitlySet) {
            return $this->defaultValue;
        } else {
            return $this->value;
        }
    }

    /**
     * Get to know if value has been explicitly set.
     *
     * @return  boolean  Whether or not the value has been set explicitly
     */
    public function isValueExplicitlySet()
    {
        return $this->isExplicitlySet;
    }

    /**
     * Set value
     *
     * @param  mixed  $value  The value
     * @return  void
     */
    public function setValue($value)
    {
        $this->isExplicitlySet = true;
        $this->value = $value;
    }

    /**
     * Check if the value is valid.
     *
     * This base class does no checking, but this method is overridden by most other options.
     * @return  void
     */
    public function check()
    {
    }

    /**
     * Helpful function for checking type - used by subclasses.
     *
     * @param  string  $expectedType  The expected type, ie 'string'
     * @throws  InvalidOptionTypeException  If the type is invalid
     * @return  void
     */
    protected function checkType($expectedType)
    {
        if (gettype($this->getValue()) != $expectedType) {
            throw new InvalidOptionTypeException(
                'The "' . $this->id . '" option must be a ' . $expectedType .
                ' (you provided a ' . gettype($this->getValue()) . ')'
            );
        }
    }

    public function getValueForPrint()
    {
        return print_r($this->getValue(), true);
    }
}
