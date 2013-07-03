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

    private $method; //1=file,2=database
    private $storage = array(); //either file path or database connection details

    private $h; //storage handle

    private $transaction = array();

    public $fast_heading = false; //set true to enable date and IP heading in all fastLog calls; normally heading is only at top of all transactional logs
    public $bsize = 10; //how many log messages to keep available for quick recall
    public $buffer = array();

    public function __construct($method='file', $bsize=false){
        if ( $method == "file" ){
            $this->method = 1;
        } else {
            $this->method = 0;
        }
        if ( $bsize ){
            $this->bsize = (int)$bsize;
        }
        return true;
    }

    //set the log file path and name
    public function setLogFile($path){
        if ( $this->method == 1 ){
            if ( file_exists($path) ){
                $this->storage['filename'] = $path;
                return true;
            } else {
                throw new exception('File path not found');
            }
        }
        throw new exception('Incorrect storage method');
    }

    //set the database connection details and initiate the connection
    public function setDatabase($server, $database, $username, $password, $table="log", $row="event"){
        if ( $this->method == 2 ){
            $this->storage['server'] = $server;
            $this->storage['database'] = $database;
            $this->storage['username'] = $username;
            $this->storage['password'] = $password;
            if ( $table ){
                $this->storage['table'] = $table;
            }
            if ( $row ){
                $this->storage['row'] = $row;
            }
            try {
                $this->connectDatabase();
            } catch (Exception $e) {
                throw new Exception("Could not connect to database: " . $e->getMessage());
            }
            return true;
        }
        throw new exception('Incorrect storage method');
    }

    //do a quick write to the log
    public function fastLog($msg){
        if ( $this->fast_heading ){
            if ( isset($_SERVER['REMOTE_ADDR']) ){
                $msg = "-- " . date("Y-m-d H:i:s") . " " . $_SERVER['REMOTE_ADDR'] . $msg;
            } else {
                $msg = "-- " . date("Y-m-d H:i:s") . $msg;
            }
        }
        $msg .= "\n";
        $this->putInBuffer($msg);
        if ( $method == 1 ){
            return $this->writeToFile($msg);
        } else {
            return $this->writeToDatabase($msg);
        }
    }

    //store a log message in the transaction array
    public function log($msg){
        $this->transaction[] = $msg;
        $this->putInBuffer($msg);
        return true;
    }

    //push the transaction to the log and reset
    public function writeLog(){
        //need to create the header
        if ( isset($_SERVER['REMOTE_ADDR']) ){
            $event = "-- " . date("Y-m-d H:i:s") . " " . $_SERVER['REMOTE_ADDR'] . "\n";
        } else {
            $event = "-- " . date("Y-m-d H:i:s") . "\n";
        }
        foreach($this->transaction as $msg){
            $event .= $msg . "\n";
        }
        $this->transaction = array();
        if ( $method == 1 ){
            return $this->writeToFile($event);
        } else {
            return $this->writeToDatabase($event);
        }
    }

    /**** PRIVATE FUNCTIONS *****/

    //store the log message in the buffer
    private function putInBuffer($msg){
        array_unshift($this->buffer, $msg);
        while ( count($this->buffer) > $this->bsize ){
            //while the buffer is bigger than the allowed size, remove the last one
            $this->buffer = array_pop($this->buffer);
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
    
    //write the log message to a file
    private function writeToFile($msg){
        $this->h = fopen($this->storage['filename'], "a");
        $ret = fwrite($this->h, $msg);
        fclose($this->h);
        if ( !$ret ){
            return false;
        }
        return true;
    }

    //write the log message to a database
    private function writeToDatabase($msg){
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

}

?>
