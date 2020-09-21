<?php

/* ========================================
* 
*       Validation tools for confirming password and email
* 
======================================== */

interface validateTemplate {
            public function email($email); // Validates Email
            public function password($pass); // validates password 
}

// Go Validate

class validate implements validateTemplate {
    
    public function email($email) {
        if(preg_match("/\\s/", $email) || empty($email) || strpos($email,"@")===false) {
            return false;
        } else {
            return true;
        }
    }
    
    public function password($pass) {
        if(preg_match("/\\s/", $pass) || empty($pass) || (strlen($pass)<7)) {
            return false;
        } else {
            return true;
        }
    }
    
}