<?php

namespace WebPConvert\Convert\Converters\BaseTraits;

/**
 * Trait for providing logging capabilities.
 *
 * This trait is currently only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning logging.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait LoggerTrait
{

    /** @var \WebPConvert\Loggers\BaseLogger  The logger (or null if not set) */
    protected $logger;

    /**
     * Set logger
     *
     * @param   \WebPConvert\Loggers\BaseLogger $logger (optional)  $logger
     * @return  void
     */
    public function setLogger($logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Write a line to the logger.
     *
     * @param  string  $msg    The line to write.
     * @param  string  $style  (optional) Ie "italic" or "bold"
     * @return void
     */
    public function logLn($msg, $style = '')
    {
        if (isset($this->logger)) {
            $this->logger->logLn($msg, $style);
        }
    }

    /**
     * New line
     *
     * @return  void
     */
    protected function ln()
    {
        if (isset($this->logger)) {
            $this->logger->ln();
        }
    }

    /**
     * Write to the logger, without newline
     *
     * @param  string  $msg    What to write.
     * @param  string  $style  (optional) Ie "italic" or "bold"
     * @return void
     */
    public function log($msg, $style = '')
    {
        if (isset($this->logger)) {
            $this->logger->log($msg, $style);
        }
    }
}
