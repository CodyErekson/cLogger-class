cLogger-class
=============

Portable Transactional Logging Object


Gives the option to save to a text file log or a database log.

Basic usage:

<code>$cLog = new cLogger();
$cLog->storage->setStorage("debug.log");</code>

Alternatively, if you wish to log to a database:

<code>$cLog = new cLogger("database");
$cLog->storage->setStorage(array("server" => "localhost", "database" => "mydatabase", "username" => "cody", "password" => "p@s$w0rd", "table" => "log"));</code>


Can write in two formats: either a simple string or "Bunyan" JSON encoded format.

When using string format (which is the default) a typical log entry will be written like so:

<code>$cLog->log("This is an error!");</code>

Will produce:

<code>2014-04-02 10:02:43  192.168.1.132  This is an error!</code>

It is also possible to pass in a scalar object to be logged, such as a JSON or serialized string:

<code>$cLog->log("This is an error", json_encode(array("status" => "false", "message" => "This sucks")));</code>

Will produce:

<code>2014-04-02 10:02:43  192.168.1.132  This is an error
  {"status":"false","message":"This sucks"}</code>
  
Bunyan format stores the entire message in JSON format, and adds a few additional details.  In addition, it requires some other parameters:

<code>$cLog->format = "bunyan";
$cLog->name = "My Cool Web Application";
$cLog->fastLog("A very serious error has happened.", false, "10");</code>

The logged message will look like:

<code>{"name":"My Cool Web Application","hostname":"localhost","pid":7774,"level":10,"msg":"A very serious error has happened.","time":"2014-04-03T17:31:44.712Z","v":0}</code>

There are a few things to note here.

Format: Can be either "string" or "bunyan"
Name: The name of your application -- whatever you want it to show in the log
Level: You pass this value in to define the seriousness of the message being logged.  The levels are outlined here:
        trace (60): logging from external libraries
        debug (50): verbose debug information
        info (40): detail on regular information
        warn (30): something an operation should pay attention to
        error (20): fatal for a request / action
        fatal (10): the application exited because of some error
        
So what is the difference between log() and fastWrite()?  Essentially it's about the buffer.  Every message you log will be stored in the buffer.  However, when you call log(), the message is stored in the transaction array until you call writeLog().  At that time, the transaction array is written into the log and flushed.  However, fastWrite() will simply write to the log immediately.  Transactional logging can be immensely useful when you are trying to debug events from a specific process that you wish to see logged in a certain order, or that are writing to an extremely busy log.

Additionally there is a buffer array in which the last 10 (by default) messages logged are stored for easy recall at any point.

<code>print_r($cLog->buffer);</code>
