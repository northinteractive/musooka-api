<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

// Include Models
require ROOT . 'application/models/songs.model.php';

class NewController {
    public $updates;
    public $httpObj;
    public $response;
    
    public function __construct() {
        // Create new Vote object
        $this->songs = new Songs();
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        
        // Get response from user model
        $this->response = $this->songs->load_new($this->httpObj);
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}