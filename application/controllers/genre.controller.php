<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

class GenreController {
    public $update;
    public $httpObj;
    public $response;
    
    public function __construct() {
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        $POST = $this->httpObj->getRequestVars();
        $DB = new DB();
        
        if(!isset($POST['user_id']) || ($POST['user_id']=='')) {
            $this->response['message'] = 'You must provide a user_id';
            $this->response['status'] = 500;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
        
        if(authorize::key($POST['user_id'],$POST['key'])) {
            
            if(isset($POST['user_id'])) {
                
                $q = "SELECT genre,filtering FROM users WHERE users.id=:id LIMIT 1";
                $sth = $DB->prepare($q);
                $sth->bindParam(":id",$POST['user_id']);
                $sth->execute();
                $a = $sth->fetchAll();
                $g = json_decode(stripslashes($a[0]['genre']));
                
                if(count($g)>0) {
                    
                    $r = array();
                    
                    foreach($g as $k => $v) {
                        (int) $k;
                        $r[$k] = $v;
                    }
                } else {
                    $r = array();
                }
                
                
                (int) $this->response['filtering'] = (int) $a[0]['filtering'];
                
                $this->response['genre'] = $r;
                $this->response['message'] = "Returning subscribed genres";
                $this->response['status'] = 200;
                
            } else {
                $g = '';
                $this->response['genre'] = array();
                $this->response['message'] = "No Genres Subscribed";
                $this->response['status'] = 404;
            }
            
        } else {
            $this->response['message'] = 'Invalid Credentials';
            $this->response['status']  = 401;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}