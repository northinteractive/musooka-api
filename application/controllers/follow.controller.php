<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

// Include Models
require ROOT . 'application/models/follow.model.php';

class FollowController {
    public $updates;
    public $httpObj;
    public $response;
    
    public function __construct() {
        // Create new Vote object
        $this->follow = new Follow();
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        
        if(isset($_REQUEST['follow_action'])) {
            if($_REQUEST['follow_action']=='add') {
                // Add a new follow
                $this->response = $this->follow->add($this->httpObj);
            } else if($_REQUEST['follow_action']=='remove') {
                // Remove a new follow
                $this->response = $this->follow->remove($this->httpObj);
            }
        } else {
            $this->response = $this->follow->return_null($this->httpObj);
        }
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}