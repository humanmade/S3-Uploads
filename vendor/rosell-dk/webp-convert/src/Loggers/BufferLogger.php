<?php

namespace WebPConvert\Loggers;

use WebPConvert\Loggers\BaseLogger;

/**
 * Collect the logging and retrieve it later in HTML or plain text format.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class BufferLogger extends BaseLogger
{
    public $entries = array();

    /**
     * Write a message to the buffer - all entries can later be retrieved with getText() or getHtlm().
     *
     * @param  string  $msg     message to log
     * @param  string  $style   style (null | bold | italic)
     * @return void
     */
    public function log($msg, $style = '')
    {
        $this->entries[] = [$msg, $style];
    }

    /**
     * Write a new line to the buffer.
     *
     * @return void
     */
    public function ln()
    {
        $this->entries[] = '';
    }

    /**
     * Get everything logged - as HTML.
     *
     * @return string  The log, formatted as HTML.
     */
    public function getHtml()
    {
        $html = '';
        foreach ($this->entries as $entry) {
            if ($entry == '') {
                $html .= '<br>';
            } else {
                list($msg, $style) = $entry;
                $msg = htmlspecialchars($msg);
                if ($style == 'bold') {
                    $html .= '<b>' . $msg . '</b>';
                } elseif ($style == 'italic') {
                    $html .= '<i>' . $msg . '</i>';
                } else {
                    $html .= $msg;
                }
            }
        }
        return $html;
    }

    /**
     * Get everything logged - as markdown.
     *
     * @return string  The log, formatted as MarkDown.
     */
    public function getMarkDown($newLineChar = "\n\r")
    {
        $md = '';
        foreach ($this->entries as $entry) {
            if ($entry == '') {
                $md .= $newLineChar;
            } else {
                list($msg, $style) = $entry;
                if ($style == 'bold') {
                    $md .= '**' . $msg . '** ';
                } elseif ($style == 'italic') {
                    $md .= '*' . $msg . '* ';
                } else {
                    $md .= $msg;
                }
            }
        }
        return $md;
    }

    /**
     * Get everything logged - as plain text.
     *
     * @param  string  $newLineChar. The character used for new lines.
     * @return string  The log, formatted as plain text.
     */
    public function getText($newLineChar = ' ')
    {
        $text = '';
        foreach ($this->entries as $entry) {
            if ($entry == '') {  // empty string means new line
                if (substr($text, -2) != '.' . $newLineChar) {
                    $text .= '.' . $newLineChar;
                }
            } else {
                list($msg, $style) = $entry;
                $text .= $msg;
            }
        }

        return $text;
    }
}
