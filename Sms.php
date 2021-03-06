<?php
/**
 * Sms
 *
 * The main file of the task. Almost all works are done here, including
 * sending sms, reporting reports,  installing db table, etc.  We write
 * HTML codes directly in strings in this file, this is because we have
 * very simple html pages and there is no need to put the HTML codes in
 * another files or manage them in any way.
 */


require_once "config.php";
require_once "Logger.php";

class Sms{
    /*****************************************************************/
    /** Public methods (Rendering pages) *****************************/
    /*****************************************************************/

    /**
     * send
     *
     * Sends sms by calling send sms APIs.
     * 
     * @return void
     */
    public static function send(){
        // init result structure
        $result = [
            "status" => false,
            "error" => "",
            "api" => "0",
        ];

        //// check body
        // check if body is set
        if(!isset($_GET["body"])){
            $result["error"] = "ERR_NO_BODY";
            echo json_encode($result);
            return;
        }

        // check body length
        if(strlen($_GET["body"]) > Config::BODY_MAX_LEN){
            $result["error"] = "ERR_BODY_MAX_LEN_REACHED";
            echo json_encode($result);
            return;
        }

        $body = $_GET["body"];

        //// check number
        // check if number is set
        if(!isset($_GET["number"])){
            $result["error"] = "ERR_NO_NUMBER";
            echo json_encode($result);
            return;
        }

        // check number length
        if(strlen($_GET["number"]) < Config::NUMBER_LEN){
            $result["error"] = "ERR_TOO_SHORT_NUMBER";
            echo json_encode($result);
            return;
        }

        // unify number format
        $number = Config::NUMBER_PREFIX.substr($_GET["number"], -Config::NUMBER_LEN);

        // shuffle API URLs (we don't want to put all the pressure on the first one)
        $apiKeys = array_keys(Config::API_URLS);
        shuffle($apiKeys);

        // try all APIs
        $apiKey = self::sendTryAll($body, $number);
        if($apiKey !== false){
            $result["status"] = true;
            $result["error"] = "";
            $result["api"] = $apiKey;
        }
        else{
            $result["status"] = false;
            $result["error"] = "ERR_EXTERNAL_API";
            $result["api"] = 0;
        }

        $id = null;
        // try to insert this sms record to database
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

        // add sms to queue of unsent messages if send process was failed
        if(!$result["status"] and $id != null){
			$redis = new \Predis\Client();
            $redis->connect('localhost', 6379);
            $redis->rpush("smsQueue", $id);
        }

        // echo result in JSON format
        echo json_encode($result);
        return;
    }

    /**
     * report
     *
     * Echos simple html report page.
     * 
     * @return void
     */
    public static function report(){
        // sms count
        $count = self::getCount();
        echo "
            <h1>Recorded Messages: <span style='color: rgb(66, 107, 220);'>{$count['recorded']}</span></h1>
            <h1>Sent Messages: <span style='color: rgb(66, 107, 220);'>{$count['sent']}</span></h1>
        ";

        // apis information
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

        // top ten numbers
        $users = self::getTopTen();
        echo "
            <h1>Top Ten Numbers:</h1>
            <style>
                table {
                    border-collapse: collapse;
                }
                table, th, td {
                    border: 1px solid black;
                }
                th, td {
                    text-align: center;
                }
            </style>
            <table>
                <tr>
                    <th>rank</th>
                    <th>number</th>
                    <th>sms_count</th>
                </tr>
        ";
        foreach($users as $user)
            echo "
                <tr>
                    <td>{$user['rank']}</td>
                    <td>{$user['number']}</td>
                    <td>{$user['sms_count']}</td>
                </tr>
            ";

        echo "
            </table>
        ";
        
        // search by number
        echo "
            <h1>Search By Number:</h1>
            <form>
                <input
                    name='number'
                    placeholder='number'
                    value='".(isset($_GET["number"]) ? $_GET["number"] : "")."'
                >
                <button>Search</button>
            </form>
        ";
        if(isset($_GET["number"])){
            $records = self::search($_GET["number"]);
            if(!$records)
                echo "
                    <h4 style='color: //666;'>No Records Found!</h4>
                ";
            else{
                echo "
                    <table>
                        <tr>
                            <th>body</th>
                            <th>api</th>
                            <th>request time</th>
                            <th>sent time</th>
                            <th>status</th>
                        </tr>
                ";
                foreach($records as $record){
                    if($record['sent']){
                        $color = "rgb(200, 256, 200)";
                        $emoji = "✅";
                    }
                    else{
                        $color = "rgb(256, 200, 200)";
                        $emoji = "❌";
                    }
                    echo "
                        <tr style='background-color: $color;'>
                            <td>{$record['body']}</td>
                            <td>{$record['api']}</td>
                            <td>{$record['request_time']}</td>
                            <td>{$record['sent_time']}</td>
                            <td>$emoji</td>
                        </tr>
                    ";
                }
                echo "
                    </table>
                ";
            }
        }

        // unsent messages queue
        $queue = implode(", ", self::getQueue());
        echo "
            <hr>
            <h1>Unsent Messages Queue:</h1>
            $queue
            <h3><a href='/sms/clear_queue'>Clear Queue</a></h3>
            <hr>
        ";
    }

    /**
     * index
     * 
     * Echos simple html page  which contains  a link to installer page
     * or report page.
     * 
     * @return void
     */
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
    }

    /**
     * install
     *
     * Creates db tables, clears redis queue and echos success or
     * failure message.
     * 
     * @return void
     */
    public static function install(){
        // empty redis unsent messages queue
        $redis = new \Predis\Client();
        $redis->connect('localhost', 6379);
        $redis->del("smsQueue");

        // sql query
        $query = "
            DROP TABLE IF EXISTS sms;

            CREATE TABLE sms (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                number VARCHAR(12) NOT NULL,
                body TEXT NOT NULL,
                api INT(6) UNSIGNED,
                request_time TIMESTAMP NULL DEFAULT NULL,
                sent_time TIMESTAMP NULL DEFAULT NULL,
                sent TINYINT(1) UNSIGNED
            );

            DROP TABLE IF EXISTS users;

            CREATE TABLE users (
                number VARCHAR(12) NOT NULL PRIMARY KEY,
                sms_ids TEXT NOT NULL,
                sms_count INT(6) UNSIGNED
            );

            DROP TABLE IF EXISTS apis;

            CREATE TABLE apis (
                api_key INT(6) UNSIGNED PRIMARY KEY,
                requests INT(6) UNSIGNED,
                fails INT(6) UNSIGNED
            );
        ";

        $apiKeys = array_keys(Config::API_URLS);
        foreach($apiKeys as $apiKey)
            $query .= "INSERT INTO apis (api_key, requests, fails) VALUES ($apiKey, 0, 0);";

        // try to connect and recreate database
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
    
    /**
     * uninstall
     * 
     * Removes db tables, clears redis queue and echos success or
     * failure message.
     *
     * @return void
     */
    public static function uninstall(){
        // empty redis unsent messages queue
        $redis = new \Predis\Client();
        $redis->connect('localhost', 6379);
        $redis->del("smsQueue");

        // sql query
        $query = "
            DROP TABLE IF EXISTS sms;
            DROP TABLE IF EXISTS users;
            DROP TABLE IF EXISTS apis;
        ";
        
        // try to connect and remove database
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

    /**
     * clearQueue
     *
     * Trys to send all unsent messages in queue.
     * 
     * @return void
     */
    public static function clearQueue(){
        // get list of unsent messages and clear it (will add them if can't send again)
        $redis = new \Predis\Client();
        $redis->connect('localhost', 6379);
        $queue = $redis->lrange("smsQueue", 0, -1);
        $redis->del("smsQueue");

        // try to send each of unsent messages
        foreach($queue as $smsId){
            $sent = false;
            try{
                $conn = self::connect();
                $query = "SELECT body, number FROM sms WHERE id = $smsId";
                $result = $conn->query($query);
                
                if($data = $result->fetch_assoc()){
                    $apiKey = self::sendTryAll($data['body'], $data['number']);
                    // update sms info in db on success send
                    if($apiKey !== false){
                        $sent = true;
                        $query = "UPDATE sms SET api = $apiKey, sent = 1, sent_time = CURRENT_TIMESTAMP WHERE id = $smsId";
                        $conn->query($query);
                    }
                }
            }
            catch(Exception $e){}

            // echo proper message and add id to queue if can't send
            if($sent){
                echo "sent <b>$smsId</b> successfully.<br>";
            }
            else{
                echo "failed to send <b>$smsId</b>.<br>";
                $redis->rpush("smsQueue", $smsId);
            }
        }

        echo "<h3><a href='/sms/report'>Back to Report</a></h3>";
    }

    /*****************************************************************/
    /** Private methods **********************************************/
    /*****************************************************************/

    /**
     * search
     *
     * Searchs for  sms records  in db  with specific phone number  and 
     * return the records.
     * 
     * @param string $number
     * @return array $records Array of associative arrays as sms recor-
     *                        ds containig string body, int api, times-
     *                        tamp  request_time,  timestamp sent_time,
     *                        and bool sent.
     */
    private static function search($number){
        $records = [];

        // check number length
        if(strlen($number) < Config::NUMBER_LEN)
            return $records;

        // unify number format
        $number = Config::NUMBER_PREFIX.substr($number, -Config::NUMBER_LEN);

        try{
            $conn = self::connect();

            $query = $conn->prepare("
                SELECT body, api, request_time, sent_time, sent FROM sms WHERE number = ?
            ");
            if ($query === false)
                return $records;
            $query->bind_param("s", $number);
            $query->execute();
            
            $result = $query->get_result();
            while($record = $result->fetch_assoc())
                $records[] = [
                    "body"          => $record["body"],
                    "api"           => $record["api"],
                    "request_time"  => $record["request_time"],
                    "sent_time"     => $record["sent_time"],
                    "sent"          => $record["sent"],
                ];
        }
        catch(Exception $e){}

        return $records;
    }

    /**
     * getCount
     *
     * Returns number of recorded and sent messages separately.
     * 
     * @return array $count Associative array  with keys  recorded  and
     *                      sent.
     */
    private static function getCount(){
        $count = [
            "recorded" => 0,
            "sent" => 0
        ];

        try{
            $conn = self::connect();

            $query = "SELECT COUNT(*) AS count FROM sms";
            $result = $conn->query($query);
            if($data = $result->fetch_assoc() and isset($data["count"]))
                $count["recorded"] = $data["count"];

            $query = "SELECT COUNT(*) AS count FROM sms WHERE sent = 1";
            $result = $conn->query($query);
            if($data = $result->fetch_assoc() and isset($data["count"]))
                $count["sent"] = $data["count"];
        }
        catch (Exception $e){}

        return $count;
    }

    /**
     * getApiInformation
     *
     * Returns usage and failure percentage of each API.
     * 
     * @param string $apiKey Key of the API  for fetching  information.
     *                       this key is  the key in  associative array
     *                       API_URLS in file Config.php.
     * @return array $info Associative array with keys requests  (total
     *                     number of requests sent to api), fails (num-
     *                     ber of requests with  failure  result),  and
     *                     failure_percentage (percentage of failures).
     */
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
                    "failure_percentage" => $data["requests"] != 0 ? $data["fails"] / $data["requests"] * 100 : 0
                ];
        }
        catch (Exception $e){}

        return $info;
    }

    /**
     * getTopTen
     *
     * Returns ten users with most sms records.
     * 
     * @return array $users Array of associative arrays as users conta-
     *                      inig rank, number, and sms_count.
     */
    private static function getTopTen(){
        $users = [];
        try{
            $conn = self::connect();
            $query = "SELECT number, sms_count FROM users ORDER BY sms_count DESC";
            $result = $conn->query($query);

            $i = 0;
            while($user = $result->fetch_assoc() and $i < 10){
                $users[] = [
                    "rank" => $i + 1,
                    "number" => $user["number"],
                    "sms_count" => $user["sms_count"]
                ];
                $i++;
            }
        }
        catch (Exception $e){}

        return $users;
    }

    /**
     * getQueue
     * 
     * Returns queue of unsent messages.
     *
     * @return array $queue List of unsent messages.
     */
    private static function getQueue(){
        $redis = new \Predis\Client();
        $redis->connect('localhost', 6379);
        return $redis->lrange("smsQueue", 0, -1);
    }

    /**
     * Checks if the app is installed.
     *
     * @return bool $isInstalled True if the app is installed and false
     *                           otherwise.
     */
    private static function isInstalled(){
        try{
            $conn = self::connect();

            if($conn->query("DESCRIBE sms"))
                // everything is fine
                return true;
            else
                // cannot find the table
                return false;
        }
        catch (Exception $e){
            // cannot connect to sql
            return false;
        }
    }

    /**
     * connect
     *
     * Return a sql db connection object.
     * 
     * @return mysqli $conn MySQLi object to use for executing queries.
     */
    private static function connect(){
        $conn = new mysqli(Config::DB_HOST, Config::DB_USER, Config::DB_PASSWORD, Config::DB_NAME);

        // throw an exception if there is an error
        if ($conn->connect_error)
          throw new Exception($conn->connect_error);

        return $conn;
    }

    /**
     * askApi
     *
     * Sends a get request to $apiUrl with data $data  and  returns the
     * response.
     * 
     * @param string $apiUrl API URL to send the data to.
     * @param string $data Data to send to API in http query format.
     * @return string $response Content of server response.
     */
    private static function askApi($apiUrl, $data){
        return file_get_contents("$apiUrl?$data");
    }
    
    /**
     * sendTryAll
     *
     * Trys to send sms with all APIs in random order,  till success or
     * APIs end.
     * 
     * @param string $body Body of sms to send.
     * @param string $number Number we want to send the sms to.
     * @return mixed $apiKey If the send process  was successful,  this
     *                       function returns API key of  the API  used
     *                       to send, otherwise returns false.
     */
    private static function sendTryAll($body, $number){
        // build query for external sms API
        $data = http_build_query(
            [
                "body" => $body,
                "number" => $number,
            ]
        );

        // shuffle API URLs (we don't want to put all the pressure on the first one)
        $apiKeys = array_keys(Config::API_URLS);
        shuffle($apiKeys);

        // try with each one
        foreach($apiKeys as $apiKey){
            Logger::info("Trying to send sms(number='$number', body='$body') on API $apiKey.");
            $status = false;

            // try current api
            try{
                $apiResult = self::askApi(Config::API_URLS[$apiKey], $data);
                $apiResult = json_decode($apiResult, true);
                if(isset($apiResult["status"]) and $apiResult["status"] === true)
                    $status = true;
            }
            catch (Exception $e){}

            // update api info in database
            $failure = 1;
            if($status)
                $failure = 0;                
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

            // end the loop if the sms is successfully sent
            if($status == true){
                Logger::info("sms(number='$number', body='$body') sent successfully on API $apiKey.");
                return $apiKey;
            }
            else
                Logger::info("sms(number='$number', body='$body') failed to send on API $apiKey.");
        }
        return false;
    }

    /**
     * storeSms
     * 
     * Stores one sms in database and add it to list of sms ids of rel-
     * ated user in table users, may add new record for user if it doe-
     * sn't exist.
     *
     * @param string $body Body of sms.
     * @param string $number Number we try to send the sms to.
     * @param int    $api The API we used to send the sms with.  (0  if
     *                    we couldn't send sms successfully.)
     * @param bool   $sent True if the sms is sent successfully and fa-
     *                     lse otherwise.
     * @return int   $id Id of stored sms.
     */
    private static function storeSms($body, $number, $api, $sent){
        // set up database connection
        $conn = self::connect();

        //// insert sms to sms table
        // set up request time and sent time
        $now = date("Y-m-d H:i:s");
        $request_time = $now;
        $sent_time = $sent ? $now : null;
        
        // insert the data
        $insertQuery = $conn->prepare("
            INSERT INTO
                sms    (number, body, api, request_time, sent_time, sent)
                VALUES (?     , ?   , ?  , ?           , ?        , ?   )
        ");
        if ($insertQuery === false)
            throw new Exception($conn->error);
        // string number, string body, int api, string request_time, string sent_time, intval(boolean) sent
        $insertQuery->bind_param("ssissi", $number, $body, $api, $request_time, $sent_time, intval($sent));
        $insertQuery->execute();
        $id = $conn->insert_id;
        
        //// insert/update user to users table
        // current list of sms ids
        $smsIds = [];
        // current number of messages
        $smsCount = 0;

        // query string for update/insert the user
        $userQuery = $conn->prepare("");

        // check if user exists in database or we need to add new record.
        // if exists, we get its list of sms ids to append new sms to it.
        // we also get their sms count to increment it.
        $findQuery = $conn->prepare("
            SELECT sms_ids, sms_count FROM users WHERE number = ?
        ");
        if ($findQuery === false)
            throw new Exception($conn->error);
        $findQuery->bind_param("s", $number);
        $findQuery->execute();
        
        $result = $findQuery->get_result();
        if($user = $result->fetch_assoc()){
            // try to decode JSON stored in database
            try{
                $smsIds = json_decode($user['sms_ids']);
                $smsCount = $user['sms_count'];
            }
            catch(Exception $e){
                // just assume it's empty if couldn't decode it
                $smsIds = [];
                $smsCount = 0;
            }

            $userQuery = $conn->prepare("
                UPDATE users SET
                    sms_ids = ?,
                    sms_count = ?
                WHERE number = ?
            ");
        }
        else{
            $smsIds = [];
            $userQuery = $conn->prepare("
                INSERT INTO
                    users  (sms_ids, sms_count, number)
                    VALUES (?      , ?        , ?     )
            ");
        }

        // append the list and JSON encode it, increment the sms count, and update/insert the user
        $smsIds[] = $id;
        $smsIds = json_encode($smsIds);

        $smsCount++;

        if ($findQuery === false)
            throw new Exception($conn->error);
        $userQuery->bind_param("sis", $smsIds, $smsCount, $number);
        $userQuery->execute();

        return $id;

    }

    /*****************************************************************/
    /** Test method **************************************************/
    /*****************************************************************/
    /**
     * test
     *
     * Send some random requests with 20 random numbers to our api.
     * 
     * @return void
     */
    public static function test(){
        for ($i = 0; $i < 20; $i++){
            // generate a random number
            $number = "";
            for ($j = 0; $j < 9; $j++)
                $number .= rand(0, 9);

            // generate random number of api requests
            $requests = rand(5, 30);

            echo "
                <h3 style='color: rgb(66, 107, 220);'>Number: $number</h3>
                <h3 style='color: rgb(66, 107, 220);'>Requests: $requests</h3>
            ";

            // generate and send each request
            for ($j = 0; $j < $requests; $j++){
                // generate random length for body
                $length = rand(5, 30);

                // generate random body
                // more chance of spaces
                $characters = " 0123456789 abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ ";
                $body = "";
                for ($k = 0; $k < $length; $k++)
                    $body .= $characters[rand(0, strlen($characters) - 1)];
                
                // send request
                $data = http_build_query(
                    [
                        "body" => $body,
                        "number" => $number,
                    ]
                );
                $apiResult = self::askApi("http://localhost:80/sms/send/", $data);
                echo "
                    <h4>Request:</h4>
                    <b>number</b>: $number<br>
                    <b>body</b>: $body<br>
                    <h4>Response:</h4>
                    $apiResult<br>
                    <hr>
                ";
            }
        }
    }
}
