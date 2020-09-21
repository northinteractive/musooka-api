<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

class ChecksessionController {
    public $httpObj;
    public $response;
    
    public function __construct() {
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        $POST = $this->httpObj->getRequestVars();
        
        if(authorize::key($POST['user_id'],$POST['key'])) {
            $this->response['message'] = 'Currently Logged in';
            $this->response['status']  = 200;
            $this->response['data'] = array();
        } else {
            $this->response['message'] = 'Invalid Credentials';
            $this->response['status']  = 404;
            $this->response['data'] = array();
        }
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}