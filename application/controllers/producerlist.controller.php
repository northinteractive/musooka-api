<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

// Include Models
require ROOT . 'application/models/producer.model.php';

class ProducerlistController {
    public $updates;
    public $httpObj;
    public $response;
    
    public function __construct() {
        // Create new Vote object
        $this->producer = new Producer();
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        
        // Get response from user model
        $this->response = $this->producer->show_list($this->httpObj);
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}