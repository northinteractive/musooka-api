<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

class CheckvanityController {
    public $update;
    public $httpObj;
    public $response;
    
    public function __construct() {
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        $POST = $this->httpObj->getRequestVars();
        $DB = new DB();
        
        $q = "SELECT users.id,artists.name FROM users LEFT JOIN artists ON artists.user_id=users.id WHERE users.vanity=:vanity LIMIT 1";
        $sth = $DB->prepare($q);
        $sth->bindParam(":vanity",$POST['vanity']);
        $sth->execute();
        $user = $sth->fetchAll();
        $data = array();
        
        if(isset($user[0]['id'])) {
             if($user[0]['name']!='') {
                $this->response['message'] = 'Returning Artist';
                $this->response['status']  = 200;
                $this->response['data']['type'] = "artist";
             } else {
                $this->response['message'] = 'Returning User';
                $this->response['status']  = 200;
                $this->response['data']['type'] = "user";
             }
        } else {
            $this->response['message'] = 'Returning None';
            $this->response['status']  = 404;
            $this->response['data']['type'] = "none";
        }
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}