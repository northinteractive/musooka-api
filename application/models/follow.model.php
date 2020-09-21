<?php

/* ========================================
* 
*     Follow Tools
 *      @construct // general
 *      @return null // return null no value
 *      @add // Add a new via artist_id and user_id
 *      @remove // remove a follow via artist_id and user_id
* 
======================================== */

interface followTemplate {
    public function __construct(); // General - instantiate DB / public & private values
    public function return_null(); // Return null value
    public function add($httpObj);
    public function remove($httpObj);
}

/* ========================================
* 
*     Follow Model
* 
======================================== */

class Follow implements followTemplate {
    
    private $DB;
    private $response;
    private $httpObj;

    private $key;
    private $userId;
    private $artistId;
    private $auth;
    
    public function __construct() {
        $this->auth = false;
        $this->DB = new DB();
        $this->artistId = false;
        $this->userId = false;
        $this->key = false;
        
        $this->loadStart = 0;
        $this->loadEnd = $this->loadStart+10;
        
        $this->response = array();
        $this->response['message'] = '';
        $this->response['status'] = null;
        $this->response['data'] = '';
    }
    
    public function return_null() {
        $this->response = array();
        $this->response['message'] = 'No action requested. Please post a follow_action';
        $this->response['status'] = 400;
        $this->response['data'] = '';
        return $this->response;
        exit;
    }
    
    public function add($httpObj) {
        $s3json = new S3json("muzooka-db");
        $POST = $httpObj->getRequestVars();
       
        if(authorize::key($POST['user_id'],$POST['key'])) {
       
            if(isset($POST['user_id']) && ($POST['user_id']!=''))     { $this->userId   = $POST['user_id'];   } else { $this->userId=false; }
            if(isset($POST['artist_id']) && ($POST['artist_id']!='')) { $this->artistId = $POST['artist_id']; } else { $this->artistId=false; }
            
            if(!$this->userId || !$this->artistId) {
                $this->response = array();
                $this->response['message'] = 'A required field was left empty. Please provide an artist_id and a user_id';
                $this->response['status'] = 412;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
            
            /*=================================================================
             * 
             *          S3Json check and save
             * 
             *          For Future Consideration
             *          To be added in and fully fleshed out at a later date
             *          Using S3 as a key store to save user follows
             * 
             =================================================================*/
            
            $uf = $s3json->get("follows:user:".$this->userId);
            
            if($uf) {
                if(!is_array($uf) || !in_array($this->artistId, $uf)) {
                    
                    // If not an array, make a new array and save correctly
                    if(!is_array()) {
                        $uf = array();
                    }
                    
                    // Add artist Id to array
                    $uf[] = $this->artistId;
                    $s3json->set("follows:user:".$this->userId,$uf);   
                } else {
                    // Not in array, do nothing
                }
            } else {
                // Nothing saved, create new entry
                $uf = array();
                $uf[] = $this->artistId;
                $s3json->set("follows:user:".$this->userId,$uf);
            }
            
            /*=================================================================
             *          Completed S3Json check and save
             =================================================================*/
            
            $q="SELECT artist_id,user_id FROM follows WHERE artist_id=:artist_id AND user_id=:user_id";
            
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":user_id",$this->userId);
            $sth->bindParam(":artist_id",$this->artistId);
            
            try { $sth->execute(); } catch (PDOException $e) {
                $info = $sth->errorInfo();
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e . $info;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
            
            $data=$sth->fetch(PDO::FETCH_LAZY);
            if(($data['user_id']==$POST['user_id']) && ($data['artist_id']==$POST['artist_id'])) {
                $this->response = array();
                $this->response['message'] = 'Duplicate entry found for this artist and user';
                $this->response['status'] = 409;
                $this->response['data'] = '';
                return $this->response;
                exit;
            } else {
                // No Duplicate entries found, insert new follow
                $q="INSERT INTO follows (id,user_id,artist_id,timestamp) values (0,:user_id,:artist_id,:timestamp)";
                
                $sth=$this->DB->prepare($q);
                $sth->bindParam(":user_id",$this->userId);
                $sth->bindParam(":artist_id",$this->artistId);
                $sth->bindParam(":timestamp",time());
                
                try {
                    $sth->execute();
                    
                    $this->response = array();
                    $this->response['message'] = 'Successfully added new follow';
                    $this->response['status'] = 200;
                    $this->response['data'] = '';
                    return $this->response;
                    exit;
                    
                } catch (PDOException $e) {
                    $info = $sth->errorInfo();
                    $this->response['status']=500;
                    $this->response['message']='Caught Exception ' . $e . $info;
                    $this->response['data'] = '';
                    return $this->response;
                    exit;
                }
            }
        
        } // Done checking Authorize
        else {
            $this->response['message'] = 'You are not authorized to make this request. Please submit the correct user_id and key pair to make this request';
            $this->response['status']  = 401;
            $this->response['data'] = '';
            return $this->response;
            exit;
        }
    }
    
    public function remove($httpObj) {
        $s3json = new S3json("muzooka-db");
        $POST = $httpObj->getRequestVars();
       
        if(authorize::key($POST['user_id'],$POST['key'])) {
       
            if(isset($POST['user_id']) && ($POST['user_id']!=''))   { $this->userId   = $POST['user_id'];   } else { $this->userId=false; }
            if(isset($POST['artist_id']) && ($POST['artist_id']!='')) { $this->artistId = $POST['artist_id']; } else { $this->artistId=false; }
            
            if(!$this->userId || !$this->artistId) {
                $this->response = array();
                $this->response['message'] = 'A required field was left empty. Please provide an artist_id and a user_id';
                $this->response['status'] = 412;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
            
            /*=================================================================
             * 
             *          S3Json check and save
             * 
             *          For Future Consideration
             *          To be added in and fully fleshed out at a later date
             *          Using S3 as a key store to save user follows
             * 
             =================================================================*/
            
            $uf = $s3json->get("follows:user:".$this->userId);
            if($uf) {
                if(in_array($this->artistId, $uf)) {
                    
                    if(!is_array($uf)) {
                        $uf = array();
                    }
                    
                    // check for key and remove
                    if(($key = array_search($this->artistId, $uf)) !== false) {
                        unset($uf[$key]);
                    }
                    // Save Changes
                    $s3json->set("follows:user:".$this->userId,$uf);
                } else {
                    // Not in array, do nothing
                }
            } else {
                // Nothing saved, create new entry
                $uf = array();
                $s3json->set("follows:user:".$this->userId,$uf);
            }
            /*=================================================================
             *          Completed S3Json check and save
             =================================================================*/
            
            $q="DELETE FROM follows WHERE artist_id=:artist_id AND user_id=:user_id";
            
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":user_id",$this->userId);
            $sth->bindParam(":artist_id",$this->artistId);
            
            try { $sth->execute(); } catch (PDOException $e) {
                $info = $sth->errorInfo();
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e . $info;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
            
            $this->response = array();
            $this->response['message'] = 'Successfully removed follow';
            $this->response['status'] = 200;
            $this->response['data'] = '';
            return $this->response;
            exit;
        
        } // Done checking Authorize
        else {
            $this->response['message'] = 'You are not authorized to make this request. Please submit the correct user_id and key pair to make this request';
            $this->response['status']  = 401;
            $this->response['data'] = '';
            return $this->response;
            exit;
        }
        
    }
}