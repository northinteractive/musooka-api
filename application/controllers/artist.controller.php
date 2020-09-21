<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

// Include Models
require ROOT . 'application/models/user.model.php';
require ROOT . 'application/models/artist.model.php';

class ArtistController {
    public $updates;
    public $httpObj;
    public $response;
    
    public function __construct() {
        // Create new Vote object
        $this->user = new User();
        $this->artist = new Artist();
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        
        // Get response from user model
        $this->response = $this->artist->load($this->httpObj);
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}