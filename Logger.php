<?php
/**
 * Logger
 *
 * Just a static class used for logging sms status.
 */

/**
 * Logger
 * 
 * A static class used for logging.
 */
class Logger {    
    /**
     * info
     *
     * Logs a message as information.
     * 
     * @param  mixed $msg information message to log
     * @return void
     */
    public static function info($msg){
        self::log("INFO - $msg");
    }
    
    /**
     * warning
     *
     * Logs a message as warning.
     * 
     * @param  mixed $msg warning message to log
     * @return void
     */
    public static function warning($msg){
        self::log("WARNING - $msg");
    }
    
    /**
     * log
     *
     * Adds date and time before a message and logs it.
     * 
     * @param  mixed $msg message to log
     * @return void
     */
    private static function log($msg){
        $folder = "logs";
        if (!file_exists($folder)) 
            mkdir($folder, 0777, true);
        $filename = $folder.DIRECTORY_SEPARATOR."log_".date('Y-m-d').'.log';
        file_put_contents($filename, date('Y-m-d h:i:s')." - $msg\n", FILE_APPEND);
    }
}