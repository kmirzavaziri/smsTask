<?php

class Logger {
    public static function info($msg){
        self::log("INFO - $msg");
    }

    public static function warning($msg){
        self::log("WARNING - $msg");
    }

    public static function log($msg){
        $folder = "logs";
        if (!file_exists($folder)) 
            mkdir($folder, 0777, true);
        $filename = $folder.DIRECTORY_SEPARATOR."log_".date('Y-m-d').'.log';
        file_put_contents($filename, date('Y-m-d - h:i:s')." - $msg\n", FILE_APPEND);
    }
}