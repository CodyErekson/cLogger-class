<?php
//A fully portable logging object
//Written by Cody Erekson
//http://blog.codyerekson.org
/* 
    When created, you can define the log method, either file or database
    If database, it will create a PDO handle
    If file, it will create a file handle

    Supports transactional logging, so you can build a log message over time, then write it
    Optionally you can write single "fastLogs"
*/

class cLogger{

    public $storage = NULL; //either file or database storage object -- needs to be public

    private $transaction = array();

    public $fast_heading = false; //set true to enable date and IP heading in all fastLog calls; normally heading is only at top of all transactional logs
    public $bsize = 10; //how many log messages to keep available for quick recall
    public $buffer = array();

    public $format = "string"; //the storage format, default is a plain string; "bunyan" is a json encoded string
    public $name = NULL; //application name -- only used for bunyan logging
    public $level = "30"; //logging detail -- used for bunyan logging
    /*
        trace (60): logging from external libraries
        debug (50): verbose debug information
        info (40): detail on regular information
        warn (30): something an operation should pay attention to
        error (20): fatal for a request / action
        fatal (10): the application exited because of some error
    */

    public function __construct($method='file', $bsize=false){
        if ( $method == "file" ){
            $this->storage = new logFile();
        } else if ( $method == "database" ) {
            $this->storage = new logDatabase();
        } else {
            throw new exception('Storage method not set');
        }
        if ( $bsize ){
            $this->bsize = (int)$bsize;
        }
        return true;
    }

    //do a quick write to the log
    //extra parameter is used for recording arrays, objects, serialized or json_encoded strings
    public function fastLog($msg, $extra=false, $level=false){
        if ( $extra ){
            if ( $extra = $this->toString($extra) ){
                $msg = $msg . "\n" . $extra;
            }
        }
        if ( $level ){
            $this->level = $level;
        }
        if ( $this->format == "string" ){
           if ( $this->fast_heading ){
                if ( isset($_SERVER['REMOTE_ADDR']) ){
                    $msg = "-- " . date("Y-m-d H:i:s") . " " . $_SERVER['REMOTE_ADDR'] . $msg;
                } else {
                    $msg = "-- " . date("Y-m-d H:i:s") . $msg;
                }
            }
            $msg .= "\n";
        } else {
            $msg = $this->bunyan($msg);
        }

        $this->putInBuffer($msg);
        return $this->storage->write($msg);
    }

    //store a log message in the transaction array
    //extra parameter is used for recording arrays, objects, serialized or json_encoded strings 
    public function log($msg, $extra=false, $level=false){
        if ( $extra ){
            if ( $extra = $this->toString($extra) ){
                $msg = $msg . "\n" . $extra;
            }
        }
        if ( $level ){
            $this->level = $level;
        }
        if ( $this->format == "string" ){
            //need to create the header
            if ( isset($_SERVER['REMOTE_ADDR']) ){
                $msg = "-- " . date("Y-m-d H:i:s") . " " . $_SERVER['REMOTE_ADDR'] . $msg;
            } else {
                $msg = "-- " . date("Y-m-d H:i:s") . $msg;
            }
            $msg .= "\n";
        } else {
            $msg = $this->bunyan($msg);
        }
        $this->transaction[] = $msg;
        $this->putInBuffer($msg);
        return true;
    }

    //push the transaction to the log and reset
    public function writeLog(){
        foreach($this->transaction as $msg){
            $this->storage->write($msg);
        }
        $this->transaction = array();
        return true;
    }
    
        //inspect and "stringify" any objects or arrays passed in
    private function toString($item){
        if ( is_string($item) ){
            //either serialized or json_encoded
            $item = @unserialize($item);
            if ( !$item ){
                if (!$item = @json_decode($item)){
                    return false;
                }
            }
        }
        if ( is_array($item) ){
            //just implode, separating with a newline
            return implode("\n", $item);
        } else if ( is_object($item) ){
            //iterate through all accessible properties and do the same
            $ret = "";
            foreach($item as $value){
                $ret .= $value . "\n";
            }
            return substr($ret, 0, -2);
        }
        return false;
    }

    //write everything in Bunyan log format
    private function bunyan($msg){
        $bunyan = array(
            "name" => $this->name,
            "hostname" => gethostname(),
            "pid" => getmypid(),
            "level" => (int) $this->level,
            "msg" => $msg,
            "time" => date(DateTime::ISO8601),
            "v" => 0
        );
        $string = json_encode($bunyan) . "\n";
        return $string;
    }
}

//structure class for storage objects to inherit from
class logStorage { 

    private $h; //storage handle
    private $storage = array(); //storage info (ie, DB connection or file path)

    public function __construct(){
        return true;
    }

    public function setStorage($storage){
        return true;
    }

    public function write($msg){
        return true;
    }
}

//file writing class
class logFile extends logStorage {

    //set the log file path and name
    public function setStorage($storage){
        if ( file_exists($storage) ){
            $this->storage['filename'] = $storage;
            return true;
        } else {
            throw new exception('File path ' . $storage . ' not found');
        }
    }

    //write the log message to a file
    public function write($msg){
        $this->h = fopen($this->storage['filename'], "a");
        $ret = fwrite($this->h, $msg);
        fclose($this->h);
        if ( !$ret ){
            return false;
        }
        return true;
    }

}

//database storage class
class logDatabase extends logStorage {

    //set the database connection credentials
    public function setStorage($storage){
        if ( !is_array($storage) ){
            throw new Exception("Storage paramater must be passed as an array.");
        }
        $this->storage['server'] = $storage['server'];
        $this->storage['database'] = $storage['database'];
        $this->storage['username'] = $storage['username'];
        $this->storage['password'] = $storage['password'];
        if ( $storage['table'] ){
            $this->storage['table'] = $storage['table'];
        } else {
            $this->storage['table'] = "log";  //have to define defaults this way due to using an array to pass in parameters
        }
        if ( $row ){
            $this->storage['row'] = $storage['row'];
        } else {
            $this->storage['row'] = "event";
        }
        try {
            $this->connectDatabase();
        } catch (Exception $e) {
            throw new Exception("Could not connect to database: " . $e->getMessage());
        }
        return true;
    }

    //write the log message to a database
    public function write($msg){
        if ( ( !isset($this->storage['table']) ) || ( !isset($this->storage['row']) ) ){
            throw new Exception("Database schema is not defined");
        }
        try {
            $query = "INSERT INTO " . $this->storage['table'] . " ( " . $this->storage['row'] . " ) VALUES( :message )";
            $sth = $this->h->prepare($query);
            $sth->bindValue(":message", $msg);
            $sth->execute();
            $sth->closeCursor();
            return true;
        } catch (PDOException $e) {
            throw new Exception("Could not connect to database: " . $e->getMessage());
        }
    }

    //open the database connection using PDO
    private function connectDatabase(){
        try {
            $this->h = new PDO("mysql:host=" . $this->storage['server'] . ";dbname=" . $this->storage['database'], $this->storage['username'], $this->storage['password']);
            $this->h->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Could not connect to database: " . $e->getMessage());
        }
    }

}

?>
