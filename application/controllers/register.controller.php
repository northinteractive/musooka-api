<?php

// Include utilities
require ROOT . 'application/utilities/validate.utility.php';

// Include Models
require ROOT . 'application/models/user.model.php';


class RegisterController {
    public $user;       // User Model
    public $rest;
    public $httpObj;
    public $registerResponse;

    public function __construct() {
            // Create new local User Object
            $this->user = new User();

            // Process Request
            $this->httpObj = RestUtil::processRequest();

            // Get response from user model
            $this->registerResponse = $this->user->register($this->httpObj);

            // Return the response
            RestUtil::sendResponse($this->registerResponse['status'],json_encode($this->registerResponse),'application/json');
    }
}