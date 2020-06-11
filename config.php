<?php
# config.php
# One can configure database connections and other stuff by editing this file

error_reporting(E_ALL & ~E_WARNING);

class Config {
    const DB_NAME = "digi_sms_task";
    const DB_USER = "root";
    const DB_PASSWORD = "";
    const DB_HOST = "localhost";

    const API_URLS = [
        "1" => "http://localhost:81/sms/send/",
        "2" => "http://localhost:82/sms/send/",
    ];
        
    const BODY_MAX_LEN = 70;
    const NUMBER_LEN = 9;
    const NUMBER_PREFIX = "989";
}