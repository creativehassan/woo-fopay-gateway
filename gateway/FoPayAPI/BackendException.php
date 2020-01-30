<?php
class BackendException extends Exception{
    public function __construct($message)
    {
        parent::__construct($message); 
    }
}