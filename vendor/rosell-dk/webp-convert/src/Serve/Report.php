<?php
namespace WebPConvert\Serve;

use WebPConvert\Helpers\InputValidator;
use WebPConvert\Loggers\EchoLogger;
use WebPConvert\WebPConvert;

/**
 * Class for generating a HTML report of converting an image.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Report
{
    public static function convertAndReport($source, $destination, $options)
    {
        InputValidator::checkSourceAndDestination($source, $destination);
        ?>
<html>
    <head>
        <style>td {vertical-align: top} table {color: #666}</style>
        <script>
            function showOptions(elToHide) {
                document.getElementById('options').style.display='block';
                elToHide.style.display='none';
            }
        </script>
    </head>
    <body>
        <table>
            <tr><td><i>source:</i></td><td><?php echo htmlentities($source) ?></td></tr>
            <tr><td><i>destination:</i></td><td><?php echo htmlentities($destination) ?><td></tr>
        </table>
        <br>
        <?php
        try {
            $echoLogger = new EchoLogger();
            $options['log-call-arguments'] = true;
            WebPConvert::convert($source, $destination, $options['convert'], $echoLogger);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            echo '<b>' . $msg . '</b>';

            //echo '<p>Rethrowing exception for your convenience</p>';
            //throw ($e);
        }
        ?>
    </body>
    </html>
        <?php
    }
}
