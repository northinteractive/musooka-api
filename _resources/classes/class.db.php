<?php

class DB extends PDO {
    private $engine; 
    private $host; 
    private $database; 
    private $user; 
    private $pass; 

    public function __construct(){ 
        $this->engine = 'mysql';
	  
	  $this->host = HOST;
	  $this->database = DATABASE;
	  $this->user = USER;
	  $this->pass = DBPASS;
          
        $dns = $this->engine.':dbname='.$this->database.";host=".$this->host; 
        parent::__construct( $dns, $this->user, $this->pass ); 
    }
}
