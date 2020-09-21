<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

class GenrelistController {
    public $update;
    public $httpObj;
    public $response;
    
    public function __construct() {
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        $POST = $this->httpObj->getRequestVars();
        
        $genres = array();
        
        $genres[6] = "Rock";
        $genres[7] = "Rap / Hip Hop";
        $genres[8] = "R&B";
        $genres[9] = "Jazz";
        $genres[10]= "Pop";
        $genres[12]= "Country";
        $genres[13]= "Blues";
        $genres[14]= "Electronic / Dance";
        
        $this->response['message'] = 'Returning a list of genres for Muzooka';
        $this->response['status']  = 200;
        $this->response['data'] = $genres;
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}