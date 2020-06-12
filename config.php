<?php
/**
 * Config
 *
 * One can  configure database constants, number of  API and thier URLs,
 * maximum length  of body, and phone  number structure  by editing this
 * file.
 */

error_reporting(E_ALL & ~E_WARNING);

/**
 * Config
 * 
 * Some useful constants are stored here. See description of each one.
 */
class Config {
    // Database Name
    const DB_NAME = "digi_sms_task";
    // Database Username
    const DB_USER = "root";
    // Database Password
    const DB_PASSWORD = "";
    // Database Servername
    const DB_HOST = "localhost";

    // Associative Array of API URLs
    const API_URLS = [
        "1" => "http://localhost:81/sms/send/",
        "2" => "http://localhost:82/sms/send/",
    ];
        
    // Maximum Length of Sms Body
    const BODY_MAX_LEN = 70;
    // Length of Importnat Part of Phone Number
    const NUMBER_LEN = 9;
    // Prefix of Phone Number (This only works with Iran phone numbers) 
    const NUMBER_PREFIX = "989";
}