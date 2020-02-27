<?php

namespace WebPConvert\Convert\Converters\BaseTraits;

/**
 * Trait for handling warnings (by logging them)
 *
 * This trait is currently only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning options.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait WarningLoggerTrait
{
    abstract public function logLn($msg, $style = '');

    /** @var string|array|null  Previous error handler (stored in order to be able pass warnings on) */
    private $previousErrorHandler;

    /** @var boolean  Suppress ALL warnings? (both from log and from bubbling up) */
    private $suppressWarnings;

    /** @var int  Count number of warnings */
    private $warningCounter;

    /**
     *  Handle warnings and notices during conversion by logging them and passing them on.
     *
     *  The function is a callback used with "set_error_handler".
     *  It is declared public because it needs to be accessible from the point where the warning is triggered.
     *
     *  @param  integer  $errno
     *  @param  string   $errstr
     *  @param  string   $errfile
     *  @param  integer  $errline
     *
     *  @return false|null|void
     */
    public function warningHandler($errno, $errstr, $errfile, $errline)
    {
        /*
        We do NOT do the following (even though it is generally recommended):

        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return false;
        }

        - Because we want to log all warnings and errors (also the ones that was suppressed with @)
        https://secure.php.net/manual/en/language.operators.errorcontrol.php

        If we were to decide suppressing the ones with @, I could do this:

        if (error_reporting() == 0) {
            /// @ sign temporary disabled error reporting
            return;
        }
        [https://stackoverflow.com/questions/7380782/error-suppression-operator-and-set-error-handler]

        However, that would also disable the warnings on systems with error reporting set to E_NONE.
        And I really want the conversion log file to contain these warnings on all systems.

        If it was possible to suppress the warnings with @ without suppressing warnings on systems
        with error reporting set to E_NONE, I would do that.
        */

        $this->warningCounter++;
        if ($this->suppressWarnings) {
            return;
        }

        $errorTypes = [
            E_WARNING =>             "Warning",
            E_NOTICE =>              "Notice",
            E_STRICT =>              "Strict Notice",
            E_DEPRECATED =>          "Deprecated",
            E_USER_DEPRECATED =>     "User Deprecated",

            /*
            The following can never be catched by a custom error handler:
            E_PARSE, E_ERROR, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING

            We do do not currently trigger the following:
            E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE

            But we may want to do that at some point, like this:
            trigger_error('Your version of Gd is very old', E_USER_WARNING);
            in that case, remember to add them to this array
            */
        ];

        if (isset($errorTypes[$errno])) {
            $errType = $errorTypes[$errno];
        } else {
            $errType = "Unknown error/warning/notice ($errno)";
        }

        $msg = $errType . ': ' . $errstr . ' in ' . $errfile . ', line ' . $errline . ', PHP ' . PHP_VERSION .
            ' (' . PHP_OS . ')';
        $this->logLn('');
        $this->logLn($msg, 'italic');
        $this->logLn('');

        if (!is_null($this->previousErrorHandler)) {
            // If previousErrorHandler is this very error handler, exit to avoid recursion
            // (this could happen if ::activateWarningLogger() were called twice)
            if (is_array($this->previousErrorHandler) &&
                isset($this->previousErrorHandler[0]) &&
                ($this->previousErrorHandler[0] == $this)
            ) {
                return false;
            } else {
                return call_user_func($this->previousErrorHandler, $errno, $errstr, $errfile, $errline);
            }
        } else {
            return false;
        }
    }

    /**
     *  Activate warning logger.
     *
     *  Sets the error handler and stores the previous so our error handler can bubble up warnings
     *
     *  @return  void
     */
    protected function activateWarningLogger()
    {
        $this->suppressWarnings = false;
        $this->warningCounter = 0;
        $this->previousErrorHandler = set_error_handler(
            array($this, "warningHandler"),
            E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE
        );
    }

    /**
     *  Deactivate warning logger.
     *
     *  Restores the previous error handler.
     *
     *  @return  void
     */
    protected function deactivateWarningLogger()
    {
        restore_error_handler();
    }

    protected function disableWarningsTemporarily()
    {
        $this->suppressWarnings = true;
    }

    protected function reenableWarnings()
    {
        $this->suppressWarnings = false;
    }

    protected function getWarningCount()
    {
        return $this->warningCounter;
    }

    protected function resetWarningCount()
    {
        $this->warningCounter = 0;
    }
}
