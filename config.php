<?php
# config.php
# One can configure database connections by editing this file

error_reporting(E_ALL & ~E_WARNING);

class Config {
    const DB_NAME = "digi_sms_task";
    const DB_USER = "root";
    const DB_PASSWORD = "";
    const DB_HOST = "localhost";
}