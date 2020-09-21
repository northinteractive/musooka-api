<?php

/* ========================================
* 
*       Key Based Authorization tools
* 
======================================== */

interface authorizeTemplate {
    public function check_producer($user_id); // Check producer status
    public function key($id,$key); // check for valid key based on user id
}

/* ========================================
* 
*       Main Authorize Class
* 
======================================== */

class authorize implements authorizeTemplate {
    
    private $AUTH_PDO;
    
    public function check_producer($user_id) {
        $this->AUTH_PDO = new DB();
        
        $q = "SELECT artists.id AS producer_id, artists.producer FROM artists WHERE user_id = :user_id";
        $sth = $this->AUTH_PDO->prepare($q);
        $sth->bindParam(":user_id",$user_id);
        
        try {
            $sth->execute();
            $p=$sth->fetch(PDO::FETCH_LAZY);
            
            if($p['producer']==1) {
                return $p['producer_id'];
            } else {
                return false;
            }
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Main Key Check authentication
    
    public function key($id,$key) {
        if(md5(USERSALT.$id.USERSALT) == $key) {
            return true;
        } else {
            return false;
            
        }
    }
    
}