<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

// Include Models
require ROOT . 'application/models/comment.model.php';

class GetcommentsController {
    public $update;
    public $httpObj;
    public $response;
    
    public function __construct() {
        // Create new Vote object
        $this->comments = new Comment();
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        
        // Get response from user model
        $this->response = $this->comments->get($this->httpObj);
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}