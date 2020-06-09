<?php
# Sms.php
# This file is the main file of the task. Almost all works are done here, 
# including sending sms, reporting reports, installing db table, etc.  We
# write HTML codes  directly in strings in this file,  this is because we 
# have very simple html pages and  there is no need to put the HTML codes
# in another files or manage them in any way.

require_once "config.php";

class Sms{
    # send sms by calling send sms APIs
    public static function send(){
        echo "send";
        # TODO
    }

    # echo simple html report page
    public static function report(){
        echo "report";
        # TODO
    }

    # search for sms records in db with specific phone and echo the records
    public static function search($phone){
        echo "search: $phone";
        # TODO
    }

    # echo simple html page which contains a link to installer page or report page
    public static function index(){
        echo "<h1>Sms Task</h1>";
        if(self::isInstalled())
            echo "
                <h3><a href='sms/report'>See Report Page</a></h3>
                <h3><a href='sms/uninstall'>Uninstall App</a></h3>
            ";
        else
            echo "
                <h3><a href='sms/install'>Install App</a></h3>
            ";
        # TODO
    }

    # create db table for sms and echo success or failure message
    public static function install(){
        # sql query
        $query = "
            DROP TABLE IF EXISTS sms;

            CREATE TABLE sms (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                phone VARCHAR(12) NOT NULL,
                body TEXT NOT NULL,
                sms_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                tries TEXT NOT NULL
            );

            DROP TABLE IF EXISTS users;

            CREATE TABLE users (
                phone VARCHAR(12) NOT NULL PRIMARY KEY,
                sms_ids TEXT NOT NULL
            );

        ";
        
        # try to connect and recreate database
        try{
            $conn = self::connect();
            if ($conn->multi_query($query) === TRUE)
                echo "App installed successfully";
            else
                echo "Installation failed with message: {$conn->error}";
        }
        catch (Exception $e){
            echo "Installation failed with message: {$e->getMessage()}";
        }
        echo "<h3><a href='/'>Back to Index</a></h3>";
    }
    
    # remove db table for sms and echo success or failure message
    public static function uninstall(){
        # sql query
        $query = "
            DROP TABLE IF EXISTS sms;
            DROP TABLE IF EXISTS users;
        ";
        
        # try to connect and remove database
        try{
            $conn = self::connect();
            if ($conn->multi_query($query) === TRUE)
                echo "App uninstalled successfully";
            else
                echo "Uninstall process failed with message: {$conn->error}";
        }
        catch (Exception $e){
            echo "Uninstall process failed with message: {$e->getMessage()}";
        }
        echo "<h3><a href='/'>Back to Index</a></h3>";
    }

    # return number of sms records
    private static function getCount(){
        # TODO
    }

    # return usage and failure percentage of each API
    private static function getApiInformation(){
        # TODO
    }

    # return ten phone numbers with most sms records
    private static function getTopTen(){
        # TODO
    }

    # checks if the app is installed
    private static function isInstalled(){
        try{
            $conn = self::connect();

            if($conn->query("DESCRIBE sms"))
                # everything is fine
                return true;
            else
                # cannot find the table
                return false;
        }
        catch (Exception $e){
            # cannot connect to sql
            return false;
        }
    }

    # return a sql db connection object
    private static function connect(){        
        $conn = new mysqli(Config::DB_HOST, Config::DB_USER, Config::DB_PASSWORD, Config::DB_NAME);

        # throw an exception if there is an error
        if ($conn->connect_error)
          throw new Exception($conn->connect_error);

        return $conn;
    }
    
}
