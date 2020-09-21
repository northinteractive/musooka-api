<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';
require ROOT . 'application/models/songs.model.php';

class SetpriceController {
    
    public $updates;
    public $httpObj;
    public $response;
    
    public function __construct() {
        
        $this->songs = new Songs();
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        
        // Get response from user model
        $this->response = $this->songs->set_price($this->httpObj);
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}