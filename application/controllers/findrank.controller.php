<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

// Include Models
require ROOT . 'application/models/rank.util.php';
require ROOT . 'application/models/vote.model.php';

class FindrankController {
    public $vote;
    public $httpObj;
    public $voteResponse;

    public function __construct() {
        // Create new Vote object
        $this->vote = new Vote();

        // Process Request
        $this->httpObj = RestUtil::processRequest();

        // Get response from user model
        $this->voteResponse = $this->vote->findrank($this->httpObj);

        // Return the response
        RestUtil::sendResponse($this->voteResponse['status'],json_encode($this->voteResponse),'application/json');
    }
}