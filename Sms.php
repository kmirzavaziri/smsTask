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
        # init result structure
        $result = [
            "status" => false,
            "error" => "",
            "api" => "0",
        ];

        ## check body
        # check if body is set
        if(!isset($_GET["body"])){
            $result["error"] = "ERR_NO_BODY";
            echo json_encode($result);
            return;
        }

        # check body length
        if(strlen($_GET["body"]) > Config::BODY_MAX_LEN){
            $result["error"] = "ERR_BODY_MAX_LEN_REACHED";
            echo json_encode($result);
            return;
        }

        $body = $_GET["body"];

        ## check number
        # check if number is set
        if(!isset($_GET["number"])){
            $result["error"] = "ERR_NO_NUMBER";
            echo json_encode($result);
            return;
        }

        # check number length
        if(strlen($_GET["number"]) < Config::NUMBER_LEN){
            $result["error"] = "ERR_TOO_SHORT_NUMBER";
            echo json_encode($result);
            return;
        }

        # unify number format
        $number = Config::NUMBER_PREFIX.substr($_GET["number"], -Config::NUMBER_LEN);

        # build query for external sms API
        $data = http_build_query(
            [
                "body" => $body,
                "number" => $number,
            ]
        );

        # shuffle API URLs (we don't want to put all the pressure on the first one)
        $apiKeys = array_keys(Config::API_URLS);
        shuffle($apiKeys);

        # try with each random API till success or APIs end
        $result["error"] = "ERR_EXTERNAL_API";
        foreach($apiKeys as $apiKey){
            $failure = 0;
            try{
                $apiResult = self::askApi($apiKey, $data);
                $apiResult = json_decode($apiResult, true);
                # set result parameters
                if(isset($apiResult["status"]) and $apiResult["status"] === true){
                    $result["status"] = true;
                    $result["error"] = "";
                    $result["api"] = $apiKey;
                }
                else{
                    $failure = 1;
                }
            }
            catch (Exception $e){
                $failure = 1;
            }

            # update api info in database
            $query = "
                UPDATE apis SET
                    requests = requests + 1,
                    fails = fails + $failure
                WHERE
                    api_key = $apiKey
            ";
            try{
                $conn = self::connect();
                $conn->query($query);
            }
            catch (Exception $e){}

            # end the loop if the sms is successfully sent
            if($result["status"] == true)
                break;
        }

        # try to insert this sms record to database
        try{
            $id = self::storeSms($body, $number, $result["api"], $result["status"]);
        }
        catch(Exception $e){
            $result["status"] = false;
            $result["error"] = "ERR_DATABASE";
            $result["api"] = "0";
            echo json_encode($result);
            return;
        }

        # add sms to queue of unsent messages if send process was failed
        if(!$result["status"]){
			$redis = new \Predis\Client();
            $redis->connect('localhost', 6379);
            $redis->lpush("smsQueue", $id);
        }

        # echo result in JSON format
        echo json_encode($result);
        return;
    }

    # echo simple html report page
    public static function report(){
        # sms count
        echo "
            <h1>All SMS sent: <span style='color: rgb(66, 107, 220);'>".self::getCount()."</span></h1>
        ";

        # apis information
        $apiKeys = array_keys(Config::API_URLS);
        foreach($apiKeys as $apiKey){
            $apiInformation = self::getApiInformation($apiKey);
            echo "
                <h1>API $apiKey:</h1>
                <h2>Requests: <span style='color: rgb(66, 107, 220);'>{$apiInformation['requests']}</span></h2>
                <h2>
                    Failure:
                    <span style='color: rgb(66, 107, 220);'>
                        {$apiInformation['fails']} / {$apiInformation['requests']}
                        ({$apiInformation['failure_percentage']}%)
                    </span>
                </h2>
            ";
        }
    }

    # search for sms records in db with specific number and echo the records
    public static function search($number){
        echo "search: $number";
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
                number VARCHAR(12) NOT NULL,
                body TEXT NOT NULL,
                api INT(6) UNSIGNED,
                request_time TIMESTAMP,
                sent_time TIMESTAMP,
                sent TINYINT(1) UNSIGNED
            );

            DROP TABLE IF EXISTS users;

            CREATE TABLE users (
                number VARCHAR(12) NOT NULL PRIMARY KEY,
                sms_ids TEXT NOT NULL
            );

            DROP TABLE IF EXISTS apis;

            CREATE TABLE apis (
                api_key INT(6) UNSIGNED PRIMARY KEY,
                requests INT(6) UNSIGNED,
                fails INT(6) UNSIGNED
            );

            INSERT INTO apis (api_key, requests, fails) VALUES (1, 0, 0);
            INSERT INTO apis (api_key, requests, fails) VALUES (2, 0, 0);
        ";
        
        # try to connect and recreate database
        try{
            $conn = self::connect();
            if ($conn->multi_query($query) === true)
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
            DROP TABLE IF EXISTS apis;
        ";
        
        # try to connect and remove database
        try{
            $conn = self::connect();
            if ($conn->multi_query($query) === true)
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
        try{
            $conn = self::connect();
            $query = "SELECT COUNT(*) AS count FROM sms";
            $result = $conn->query($query);
            
            if($data = $result->fetch_assoc() and isset($data['count']))
                return $data['count'];

            return 0;
            
        }
        catch (Exception $e){
            return 0;
        }
    }

    # return usage and failure percentage of each API
    private static function getApiInformation($apiKey){
        $info = ["requests" => 0, "fails" => 0, "failure_percentage" => 0];
        try{
            $conn = self::connect();
            $query = "SELECT requests, fails FROM apis WHERE api_key = $apiKey";
            $result = $conn->query($query);
            
            if($data = $result->fetch_assoc())
                $info = [
                    "requests" => $data["requests"],
                    "fails" => $data["fails"],
                    "failure_percentage" => $data["requests"] != 0 ? $data["fails"] / $data["requests"] * 100 : 0,
                ];                

            return $info;
        }
        catch (Exception $e){
            return $info;
        }
    }

    # return ten number numbers with most sms records
    private static function getTopTen(){
        # TODO
    }

    # return queue of unsent messages
    private static function getQueue(){
        # TODO
    }

    # try to send all unsent messages in queue
    private static function clearQueue(){
        echo "clearQueue";
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

    # sends a get request to one of api addresses defined
    # in config.php according to $apiKey. returns the result
    private static function askApi($apiKey, $data){   
        $options = array(
            "http" => array(
                "header"  => "Content-type: application/x-www-form-urlencoded",
                "method"  => "GET",
                "content" => $data,
            ),
        );
         
        $context = stream_context_create($options);

        return file_get_contents(Config::API_URLS[$apiKey], false, $context);
    }
    
    # store one sms in database and add it to list of sms ids of
    # user table. add new record for user if doesn't exists.
    private static function storeSms($body, $number, $api, $sent){
        # set up database connection
        $conn = self::connect();

        ## insert sms to sms table
        # set up request time and sent time
        $now = date("Y-m-d H:i:s");
        $request_time = $now;
        $sent_time = $sent ? $now : 0;
        
        # insert the data
        $findQuery = $conn->prepare("
            INSERT INTO
                sms    (number, body, api, request_time, sent_time, sent)
                VALUES (?     , ?   , ?  , ?           , ?        , ?   )
        ");
        if ($findQuery === false)
            throw new Exception($conn->error);
        # string number, string body, int api, string request_time, string sent_time, intval(boolean) sent
        $findQuery->bind_param("ssissi", $number, $body, $api, $request_time, $sent_time, intval($sent));
        $findQuery->execute();
        $id = $conn->insert_id;
        
        ## insert/update user to users table
        # current list of sms ids
        $smsIds = [];

        # query string for update/insert the user
        $userQuery = $conn->prepare("");

        # check if user exists in database or we need to add new record.
        # if exists, we get its list of sms ids to append new sms to it.
        $findQuery = $conn->prepare("
            SELECT sms_ids FROM users WHERE number = ?
        ");
        if ($findQuery === false)
            throw new Exception($conn->error);
        $findQuery->bind_param("s", $number);
        $findQuery->execute();
        
        $result = $findQuery->get_result();
        if($user = $result->fetch_assoc()){
            # try to decode JSON stored in database
            try{
                $smsIds = json_decode($user['sms_ids']);
            }
            catch(Exception $e){
                # just assume it's empty if couldn't decode it
                $smsIds = [];
            }

            $userQuery = $conn->prepare("
                UPDATE users SET
                    sms_ids = ?
                WHERE number = ?
            ");
        }
        else{
            $smsIds = [];
            $userQuery = $conn->prepare("
                INSERT INTO
                    users  (sms_ids, number)
                    VALUES (?      , ?     )
            ");
        }

        # encode the JSON and update/insert the user
        $smsIds[] = $id;
        $smsIds = json_encode($smsIds);

        if ($findQuery === false)
            throw new Exception($conn->error);
        $userQuery->bind_param("ss", $smsIds, $number);
        $userQuery->execute();

    }

}
