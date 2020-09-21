<?php

class Update {
    
    private $DB;
    private $response;
    private $httpObj;

    private $key;
    private $userId;
    private $artistId;
    private $songId;
    private $updateBody;
    private $auth;
    
    public function __construct() {
        $this->auth = false;
        $this->artist = false;
        $this->DB = new DB();
        $this->artistId = false;
        $this->songId = false;
        $this->updateBody = '';
        $this->userId = false;
        
        $this->response = array();
        $this->response['message'] = '';
        $this->response['status'] = null;
        $this->response['data'] = '';
    }
    
    public function updateToS3(){
            $s3json = new S3json("muzooka-db");

            $time = time();
            $id = general::microtime_float();
           
            // Set Update body
            $update = array();
            $update[$id]['update_body'] = $this->updateBody;
            $update[$id]['artist_id'] = $this->artistId;
            $update[$id]['timestamp'] = $time;
            
            $s3json->set("update:".$id,$update);
    }
    
    public function delete($httpObj) {
        $POST = $httpObj->getRequestVars();
        if(authorize::key($POST['user_id'],$POST['key'])) {
            
            // Check for artist ID
            $q="SELECT user_id,id FROM artists WHERE user_id=:user_id LIMIT 1";
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":user_id",$POST['user_id']);
            try { $sth->execute(); } catch (PDOException $e) {
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
            
            $artist = $sth->fetch(PDO::FETCH_LAZY);
            
            // if not artist, fail out 401
            if($artist['user_id'] == $POST['user_id']) {
                $this->artist = true;
                $this->artistId=$artist['id'];
                $this->userId=$POST['user_id'];
                if(isset($POST['song_id']) && ($POST['song_id']!=='')) { $this->songId=$POST['song_id']; } else { $this->songId=null; }
                $this->auth=true;
            } else {
                // Not an artist, die out
                $this->response['status']=401;
                $this->response['message']='This user is not an artist';
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
        }
        
        if(($this->auth) && ($this->artist)) {
            
            $this->updateId = $POST['update_id'];
            
            if($this->updateId) {
                $q = "DELETE FROM updates WHERE id = :update_id LIMIT 1";
                $sth = $this->DB->prepare($q);
                $sth->bindParam(":update_id", $this->updateId);
                try {
                    $sth->execute();
                    $this->response['message'] = 'Successfully deleted update';
                    $this->response['status']  = 200;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                } catch (PDOException $e) {
                    $this->response['status']=500;
                    $this->response['message']='Caught Exception ' . $e;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                }
                
            } else {
                
                // No update id provided
                $this->response['status']=404;
                $this->response['message']='You must provide an update_id';
                $this->response['data'] = array();
                return $this->response;
                exit;
                
            }
        }
    }
    
    public function create($httpObj) {
        $s3json = new S3json("muzooka-db");
        $POST = $httpObj->getRequestVars();
        
        if(authorize::key($POST['user_id'],$POST['key'])) {
            
            // Check for artist ID
            $q="SELECT user_id,id FROM artists WHERE user_id=:user_id LIMIT 1";
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":user_id",$POST['user_id']);
            try { $sth->execute(); } catch (PDOException $e) {
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
            
            $artist = $sth->fetch(PDO::FETCH_LAZY);
            
            // if not artist, fail out 401
            if($artist['user_id'] == $POST['user_id']) {
                $this->artist = true;
                $this->artistId=$artist['id'];
                $this->userId=$POST['user_id'];
                if(isset($POST['song_id']) && ($POST['song_id']!=='')) { $this->songId=$POST['song_id']; } else { $this->songId=null; }
                $this->auth=true;
            } else {
                // Not an artist, die out
                $this->response['status']=401;
                $this->response['message']='This user is not an artist';
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
        }
        
        if(($this->auth) && ($this->artist)) {
        
            $this->updateBody=htmlspecialchars($POST['update']);
            $q="INSERT INTO updates (id,body,artist_id,song_id,published_time) values (0,:body,:artist_id,:song_id,:timestamp)";
            
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":body",$this->updateBody);
            $sth->bindParam(":artist_id",$this->artistId);
            $sth->bindParam(":song_id",$this->songId);
            $sth->bindParam(":timestamp",time());
            
            /*=================================================================
             *          S3Json check and save
             =================================================================*/
            $this->updateToS3();
            /*===============================================================*/
            
            try {
                $sth->execute();
                $updateId = $this->DB->lastInsertId();
            } catch (PDOException $e) {
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e;
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
            
            if($updateId!='') {
                
                $data['artist_id'] = $this->artistId;
                $data['song_id'] = $this->songId;
                $data['body'] = $this->updateBody;
                $data['timestamp'] = time();
                $data['update_date']=date('M j, Y g:i a',$data['timestamp']);
                $data['update_id'] = $updateId;
                
                $this->response['message'] = 'Successfully created update';
                $this->response['status']  = 200;
                $this->response['data'] = $data;
                return $this->response;
                exit;
            } else {
                $this->response['message'] = 'No update created' . $sth->errorInfo();
                $this->response['status']  = 500;
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
            
        } else {
            $this->response['message'] = 'You are not authorized to make this request. Please submit the correct user_id and key pair to make this request';
            $this->response['status']  = 401;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
}