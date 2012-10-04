<?php
class PDOException extends Exception
{
    public $errorInfo = null;    // corresponds to PDO::errorInfo()
                                 // or PDOStatement::errorInfo()
    protected $message;          // textual error message
                                 // use Exception::getMessage() to access it
    protected $code;             // SQLSTATE error code
                                 // use Exception::getCode() to access it
}
?>
