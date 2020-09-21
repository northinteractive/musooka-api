<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

class _S3updatesController {
    public $updates;
    public $httpObj;
    public $response;
    
    public function __construct() {
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        
        
        $s3json = new S3json("muzooka-db");
        $updates = $s3json->get("updates:global");
        
        $data = array();
        
        foreach($updates as $v) {
            $update = $s3json->get("update:".$v);
            $data[$v] = $update;
        }
        
        $this->response['message'] = "returning updates from S3";
        $this->response['status'] = 200;
        $this->response['data'] = $data;
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}
