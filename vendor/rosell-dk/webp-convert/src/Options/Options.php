<?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\OptionNotFoundException;

/**
 * Handles a collection of options.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Options
{

    /** @var  array  A map of options, keyed by their id */
    private $options = [];

    /**
     * Add option.
     *
     * @param  Option  $option  The option object to add to collection.
     * @return void
     */
    public function addOption($option)
    {
        $this->options[$option->getId()] = $option;
    }

    /**
     * Add options.
     *
     * Conveniently add several options in one call.
     *
     * @return void
     */
    public function addOptions()
    {
        $options = func_get_args();
        foreach ($options as $option) {
            $this->addOption($option);
        }
    }
     /*
     In some years, we can use the splat instead (requires PHP 5.6):
     @param  Option[]  ...$options  Array of options objects to add
    public function addOptions(...$options)
    {
        foreach ($options as $option) {
            $this->addOption($option);
        }
    }*/

    /**
     * Set the value of an option.
     *
     * @param  string  $id      Id of the option
     * @param  mixed   $value   Value of the option
     * @return void
     */
    public function setOption($id, $value)
    {
        if (!isset($this->options[$id])) {
            throw new OptionNotFoundException(
                'Could not set option. There is no option called "' . $id . '" in the collection.'
            );
        }
        $option = $this->options[$id];
        $option->setValue($value);
    }

    /**
     * Set option, or create a new, if no such option exists.
     *
     * @param  string  $id  Id of option to set/create
     * @param  mixed  $value  Value of option
     * @return void
     */
    public function setOrCreateOption($id, $value)
    {
        if (!isset($this->options[$id])) {
            $newOption = new GhostOption($id, null);
            $newOption->setValue($value);
            //$newOption = new Option($id, $value);
            $this->addOption($newOption);
        } else {
            $this->setOption($id, $value);
        }
    }

    /**
     * Get the value of an option in the collection - by id.
     *
     * @param  string  $id      Id of the option to get
     * @throws  OptionNotFoundException  if the option is not in the collection
     * @return mixed  The value of the option
     */
    public function getOption($id)
    {
        if (!isset($this->options[$id])) {
            throw new OptionNotFoundException(
                'There is no option called "' . $id . '" in the collection.'
            );
        }
        $option = $this->options[$id];
        return $option->getValue();
    }

    /**
     * Return map of option objects.
     *
     * @return array  map of option objects
     */
    public function getOptionsMap()
    {
        return $this->options;
    }

    /**
     * Return flat associative array of options.
     *
     * @return array  associative array of options
     */
    public function getOptions()
    {
        $values = [];
        foreach ($this->options as $id => $option) {
            $values[$id] = $option->getValue();
        }
        return $values;
    }

    /**
     * Check all options in the collection.
     */
    public function check()
    {
        foreach ($this->options as $id => $option) {
            $option->check();
        }
    }
}
