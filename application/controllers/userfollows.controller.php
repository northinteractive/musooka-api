<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';
require ROOT . 'application/utilities/validate.utility.php';

// Include Models
require ROOT . 'application/models/user.model.php';


class UserfollowsController {
    public $user;       // User Model
    public $httpObj;
    public $response;
    
    public function __construct() {
        
        // Create new local User Object
        $this->user = new User();
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        
        // Get response from user model
        $this->response = $this->user->follows($this->httpObj);
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}