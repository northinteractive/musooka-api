<?php

class Updates {
    
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
        $this->updateId = false;
        $this->artistId = false;
        $this->userId = false;
        
        $this->loadStart = 0;
        $this->loadEnd = $this->loadStart+15;
        
        $this->response = array();
        $this->response['message'] = '';
        $this->response['status'] = null;
        $this->response['data'] = '';
    }
    
    public function time_approximator($timestamp) {
        $now = time();
        $d = $now-$timestamp;
        
        if($d < 60) {
            $epoch = $d;
            return "About ".$epoch." seconds ago";
        } else if (($d > 60) && ($d < 3600)) {
            $epoch = round($d/60);
            return "About ".$epoch." minutes ago";
        } else if (($d > 3600) && ($d < 86400)) {
            $epoch = round($d/3600);
            return "About ".$epoch." hours ago";
        } else if (($d > 86400) && ($d < 2592000)) {
            $epoch = round($d/86400);
            return "About ".$epoch." days ago";
        } else if (($d > 2592000) && ($d < 31104000)) {
            $epoch = round($d/2592000);
            return "About ".$epoch." months ago";
        } else if ($d > 31104000) {
            $epoch = round($d/31104000);
            return "About ".$epoch." years ago";
        }
    }
    
    public function loaduser($httpObj) {
        $POST = $httpObj->getRequestVars();
       
        if(isset($POST['user_id']) && ($POST['user_id']!=''))     { $this->userId   = $POST['user_id'];   } else { $this->userId=false; }
        if(isset($POST['artist_id']) && ($POST['artist_id']!='')) { $this->artistId = $POST['artist_id']; } else { $this->artistId=false; }
        if(isset($POST['update_id']) && ($POST['update_id']!='')) { $this->updateId = $POST['update_id']; } else { $this->updateId=false; }
        
        // Check load pagination
        if(isset($POST['load_start'])) {
            $this->loadStart = $POST['load_start'];
        } else {
            $this->loadStart=0;
        }
        
        if($this->userId) {
            
            $q="SELECT updates.id,updates.body,updates.published_time,updates.artist_id,artists.name,artists.avatar,
                (SELECT count(id) FROM comments WHERE comments.parent_id=updates.id) AS comment_count
                FROM updates
                INNER JOIN artists ON updates.artist_id = artists.id
                INNER JOIN follows ON follows.artist_id = updates.artist_id
                WHERE follows.user_id = :userid
                ORDER BY updates.published_time DESC LIMIT ".$this->loadStart.",15";
                
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":userid",$this->userId);
            
            try { $sth->execute(); } catch (PDOException $e) {
                $info = $sth->errorInfo();
                $this->response['status']=500;
               $this->response['message']='Caught Exception ' . $e . $info;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
            $data=$sth->fetchAll();
            $n=count($data);
            
            if($n>0) {
                $updateinfo=array();
            
                
                // Spit out update array
                for($i=0;$i<$n;$i++) {
                    $updateinfo[$i]['update_id']=$data[$i]['id'];
                    $updateinfo[$i]['comment_count']=$data[$i]['comment_count'];
                    $updateinfo[$i]['update_body']=stripslashes($data[$i]['body']);
                    $updateinfo[$i]['update_timecode']=$data[$i]['published_time'];
                    $updateinfo[$i]['update_date']=$this->time_approximator($data[$i]['published_time']);
                    
                    $updateinfo[$i]['artist_id']=$data[$i]['artist_id'];
                    
                    // Getting artist avatars
                    $avatar = json_decode(general::get_artist_avatar($data[$i]['artist_id']));
                    $updateinfo[$i]['ios_avatar'] = $avatar->ios;
                    $updateinfo[$i]['crop_avatar'] = $avatar->crop;
                    // Done artist avatars
                    
                    $updateinfo[$i]['artist_avatar']=$data[$i]['avatar'];
                    $updateinfo[$i]['artist_name']=$data[$i]['name'];
                }
                
                $this->response['message'] = 'Returning all updates for artists that this user follows';
                $this->response['status']  = 200;
                $this->response['data'] = $updateinfo;
                return $this->response;
                exit;
                
            } else {
                
                $this->response['message'] = 'No Data Returned for this user. User is not following any artists';
                $this->response['status']  = 200;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
            
        }
        
    }

    public function loadall($httpObj) {
        $POST = $httpObj->getRequestVars();
       
        if(isset($POST['user_id']) && ($POST['user_id']!=''))     { $this->userId   = $POST['user_id'];   } else { $this->userId=false; }
        if(isset($POST['artist_id']) && ($POST['artist_id']!='')) { $this->artistId = $POST['artist_id']; } else { $this->artistId=false; }
        if(isset($POST['update_id']) && ($POST['update_id']!='')) { $this->updateId = $POST['update_id']; } else { $this->updateId=false; }
        
        // Check load pagination
        if(isset($POST['load_start'])) {
            $this->loadStart = $POST['load_start'];
        } else {
            $this->loadStart=0;
        }
        
        // Check artist id
        if($this->artistId) {
            
            $q="SELECT updates.id,updates.body,updates.published_time,updates.artist_id,artists.name,artists.avatar,
                (SELECT count(id) FROM comments WHERE comments.parent_id=updates.id) AS comment_count
                FROM updates
                INNER JOIN artists ON updates.artist_id = artists.id
                WHERE updates.artist_id = :artist_id
                ORDER BY updates.published_time DESC LIMIT ".$this->loadStart.",15";
                
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":artist_id",$this->artistId);
            
            try { $sth->execute(); } catch (PDOException $e) {
                $info = $sth->errorInfo();
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e . $info;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
            $data=$sth->fetchAll();
            $n=count($data);
            
            if($n>0) {
                $updateinfo=array();
            
                // Spit out update array
                for($i=0;$i<$n;$i++) {
                    $updateinfo[$i]['update_id']=$data[$i]['id'];
                    $updateinfo[$i]['comment_count']=$data[$i]['comment_count'];
                    $updateinfo[$i]['update_body']=stripslashes($data[$i]['body']);
                    $updateinfo[$i]['update_timecode']=$data[$i]['published_time'];
                    $updateinfo[$i]['update_date']=$this->time_approximator($data[$i]['published_time']);
                    if(isset($data[$i]['user_id'])) { $updateinfo[$i]['user_id']=$data[$i]['user_id']; } else { $updateinfo[$i]['user_id'] = ''; }
                    $updateinfo[$i]['artist_id']=$data[$i]['artist_id'];
                    
                    // Getting artist avatars
                    $avatar = json_decode(general::get_artist_avatar($data[$i]['artist_id']));
                    $updateinfo[$i]['ios_avatar'] = $avatar->ios;
                    $updateinfo[$i]['crop_avatar'] = $avatar->crop;
                    // Done artist avatars
                    
                    if(isset($data[$i]['avatar'])) { $updateinfo[$i]['artist_avatar']=$data[$i]['avatar']; }
                    $updateinfo[$i]['artist_name']=$data[$i]['name'];
                }
                
                $this->response['message'] = 'Returning all updates for this artist';
                $this->response['status']  = 200;
                $this->response['data'] = $updateinfo;
                return $this->response;
                exit;
                
            } else {
                
                $this->response['message'] = 'No updates for this artist';
                $this->response['status']  = 200;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
            
        } else if($this->updateId) {
            
            
        } else { // Load everything
            
            $q="SELECT updates.id,updates.body,updates.published_time,updates.artist_id,artists.name,artists.avatar,
                (SELECT count(id) FROM comments WHERE comments.parent_id=updates.id) AS comment_count
                FROM updates
                LEFT JOIN artists
                ON updates.artist_id=artists.id
                ORDER BY published_time DESC LIMIT ".$this->loadStart.",15";
            $sth=$this->DB->prepare($q);
            
            try { $sth->execute(); } catch (PDOException $e) {
                $info = $sth->errorInfo();
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e . $info;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
            $data=$sth->fetchAll();
            $n=count($data);
            
            $updateinfo=array();
            
            // Spit out update array
            for($i=0;$i<$n;$i++) {
                $updateinfo[$i]['update_id']=$data[$i]['id'];
                    $updateinfo[$i]['comment_count']=$data[$i]['comment_count'];
                    $updateinfo[$i]['update_body']=stripslashes($data[$i]['body']);
                    $updateinfo[$i]['update_timecode']=$data[$i]['published_time'];
                    $updateinfo[$i]['update_date']=$this->time_approximator($data[$i]['published_time']);
                    if(isset($data[$i]['user_id'])) { $updateinfo[$i]['user_id']=$data[$i]['user_id']; } else { $updateinfo[$i]['user_id'] = ''; }
                    $updateinfo[$i]['artist_id']=$data[$i]['artist_id'];
                    
                    // Getting artist avatars
                    $avatar = json_decode(general::get_artist_avatar($data[$i]['artist_id']));
                    $updateinfo[$i]['ios_avatar'] = $avatar->ios;
                    $updateinfo[$i]['crop_avatar'] = $avatar->crop;
                    // Done artist avatars
                    
                    if(isset($data[$i]['avatar'])) { $updateinfo[$i]['artist_avatar']=$data[$i]['avatar']; }
                    $updateinfo[$i]['artist_name']=$data[$i]['name'];
            }
            
            // Display updates
            $this->response['message'] = 'Returning all updates';
            $this->response['status']  = 200;
            $this->response['data'] = $updateinfo;
            return $this->response;
            exit;
            
        }
    }
}
