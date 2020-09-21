<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

class TogglefilterController {
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
            $q = "UPDATE users SET filtering = IF(filtering=1, 0, 1) WHERE id = :id LIMIT 1";
            $sth = $DB->prepare($q);
            $sth->bindParam(":id",$POST['user_id']);
            
            if($sth->execute()) {
                        
                $this->response['message'] = 'Successfully toggled filtering';
                $this->response['status']  = 200;
                $this->response['data'] = array();
                
            } else {
                $this->response['message'] = 'There was an error';
                $this->response['status']  = 500;
                $this->response['data'] = $sth->errorInfo();
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