<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

// Include Models
require ROOT . 'application/models/songs.model.php';

class RemoveplaylistController {
    public $updates;
    public $httpObj;
    public $response;
    
    public function __construct() {
        // Create new Vote object
        $this->songs = new Songs();
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        
        // Get response from user model
        $this->response = $this->songs->remove_playlist($this->httpObj);
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}