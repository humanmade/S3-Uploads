<?php

namespace WebPConvert\Loggers;

/**
 * Echo the logs immediately (in HTML)
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class EchoLogger extends BaseLogger
{

    /**
     * Handle log() by echoing the message.
     *
     * @param  string  $msg     message to log
     * @param  string  $style   style (null | bold | italic)
     * @return void
     */
    public function log($msg, $style = '')
    {
        $msg = htmlspecialchars($msg);
        if ($style == 'bold') {
            echo '<b>' . $msg . '</b>';
        } elseif ($style == 'italic') {
            echo '<i>' . $msg . '</i>';
        } else {
            echo $msg;
        }
    }

    /**
     * Handle ln by echoing a <br> tag.
     *
     * @return void
     */
    public function ln()
    {
        echo '<br>';
    }
}
