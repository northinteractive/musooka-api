<?php

/* ========================================
* 
*       S3Json
 *      Amazon S3 Based Key Store
 *      Slow, but it's infinitely scalable - keep the calls few and small
 *      Written by Jonathan Coe, 2012
* 
======================================== */

interface S3jsonTemplate {
    public function __construct($bucket);
    public function set($key,$value);
    public function get($key);
    public function del($key);
}

/* ========================================
* 
 *      S3Json Utility
*       Requires Amazon PHP SDK (version ~1.5 tested)
* 
======================================== */

class S3json extends AmazonS3 implements S3jsonTemplate {

    private $bucket;
    public $jsonError;
    
    public function __construct($bucket) {
        parent::__construct();
        // Instantiate bucket
        $this->bucket = $bucket;
        
        // Check if bucket exists
        $bucket_check = $this->if_bucket_exists($bucket);
        if(!$bucket_check) { $this->jsonError = "Bucket does not exist"; } else { $this->jsonError = false; }
    }
    
    public function set($key,$value) {
        
        $JSON = json_encode($value);
        
        $this->create_object($this->bucket, $key.".JSON", array(
                // 'acl' => AmazonS3::ACL_PUBLIC,
                'body' => $JSON,
                'Cache-Control'    => 'max-age',
                'Content-Encoding' => 'gzip',
                'Content-Language' => 'en-US',
                'Content-Type' => 'application/json',
                'Expires'          => 'Thu, 01 Dec 1994 16:00:00 GMT',
                'grants' => array(
                    array( 'id' => $this->key, 'permission' => AmazonS3::GRANT_FULL_CONTROL),
                ),
        ));
    }
    
    public function get($key) {
        $JSON = $this->get_object($this->bucket, $key.".JSON");
        $decoded = json_decode($JSON->body);
        if($decoded=="") {
            return false;
        } else {
            return $decoded;
        }
    }
    
    public function del($key) {
        $response = $this->delete_object($this->bucket,$key.".JSON");
        return $response->isOK();
    }
}