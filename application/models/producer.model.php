<?php

interface producerTemplate {
    public function __construct();
    public function show_list($httpObj);
    public function history($httpObj);
}

class Producer implements producerTemplate {
    
    private $DB;
    public $songId;
    public $producerId;
    public $artistId;
    
    public function __construct() {
        $this->DB = new DB();
        $this->producerId = false;
        $this->artistId = false;
        $this->songId = false;
        
        $this->response = array();
        $this->response['message'] = '';
        $this->response['status'] = null;
        $this->response['data'] = '';
    }
    
    public function show_list($httpObj) {
        $POST = $httpObj->getRequestVars();
        $data = array();
        
        $q = "SELECT
            artists.name AS producer_name,
            artists.avatar AS producer_avatar,
            artists.id AS producer_id,
            artists.bio,
            users.vanity AS vanity,
            artists.id AS producer_id,
            (SELECT COUNT(follows.id) FROM follows WHERE follows.artist_id = artists.id) as followers,
            users.account_verified
            FROM artists
            LEFT JOIN users ON artists.user_id = users.id
            WHERE artists.producer = 1 AND users.account_verified = 1  ORDER BY producer_id DESC";
        $sth = $this->DB->prepare($q);
        
        try {
            $sth->execute();
            $a = $sth->fetchAll();
            $n = count($a);
            
            if($n>0) {
                for($i=0;$i<$n;$i++) {
                    $data[$i]['producer_name'] = $a[$i]['producer_name'];
                    $data[$i]['producer_avatar'] = $a[$i]['producer_avatar'];
                    
                    // Getting artist avatars
                    $avatar = json_decode(general::get_artist_avatar($a[$i]['producer_id']));
                    $data[$i]['ios_avatar'] = $avatar->ios;
                    $data[$i]['crop_avatar'] = $avatar->crop;
                    // Done artist avatars
                    
                    $data[$i]['producer_vanity'] = $a[$i]['vanity'];
                    $data[$i]['producer_id'] = $a[$i]['producer_id'];
                    $data[$i]['producer_followers'] = $a[$i]['followers'];
                    $data[$i]['bio'] = $a[$i]['bio'];
                }
                
                $this->response['message'] = 'Returning Producer List';
                $this->response['status'] = 200;
                $this->response['data'] = $data;
                return $this->response;
                exit;
                
            } else {
                $this->response['message'] = 'Producer List Unavailable';
                $this->response['status'] = 200;
                $this->response['data'] = $data;
                return $this->response;
                exit;
            }
            
        } catch (PDOException $e) {
            $info = $sth->errorInfo();
            $this->response['status']=500;
            $this->response['message']='Caught Exception ' . $e . $info;
            $this->response['data'] = '';
            return $this->response;
            exit;
        }
    }
    
    public function history($httpObj) {
        $POST = $httpObj->getRequestVars();
        
        $data = array();
        
        if(isset($POST['song_id'])) {
            $this->songId = $POST['song_id'];
        } else {
            $this->response = array();
            $this->response['message'] = 'A required field was left empty. Please provide a song_id';
            $this->response['status'] = 412;
            $this->response['data'] = $data;
            return $this->response;
            exit;
        }
        
        $q = "  SELECT
                songs.title AS song_title,
                artists.name AS producer_name,
                artists.avatar AS producer_avatar,
                users.vanity AS vanity,
                artists.id AS producer_id,
                users.account_verified,
                votes.id AS vote_id,
                votes.timestamp AS vote_timestamp
                
                FROM votes
                LEFT JOIN songs ON votes.song_id = songs.id
                LEFT JOIN artists ON votes.user_id = artists.user_id
                LEFT JOIN users ON artists.user_id = users.id
                WHERE votes.song_id = :song_id AND artists.producer = 1 AND users.account_verified = 1
                ORDER BY timestamp DESC";
        
        $sth = $this->DB->prepare($q);
        $sth->bindParam(":song_id",$this->songId);
        
        try {
            $sth->execute();
            $a = $sth->fetchAll();
            $n = count($a);
            
            if($n>0) {
                for($i=0;$i<$n;$i++) {
                    $data[$i]['producer_name'] = $a[$i]['producer_name'];
                    $data[$i]['producer_avatar'] = $a[$i]['producer_avatar'];
                    
                    // Getting artist avatars
                    $avatar = json_decode(general::get_artist_avatar($a[$i]['producer_id']));
                    $data[$i]['ios_avatar'] = $avatar->ios;
                    $data[$i]['crop_avatar'] = $avatar->crop;
                    // Done artist avatars
                    
                    $data[$i]['vote_id'] = $a[$i]['vote_id'];
                    $data[$i]['vote_timestamp'] = $a[$i]['vote_timestamp'];
                    $data[$i]['producer_vanity'] = $a[$i]['vanity'];
                    $data[$i]['producer_id'] = $a[$i]['producer_id'];
                    $data[$i]['approximate_time'] = time::time_approximator($a[$i]['vote_timestamp']);
                }
                
                $this->response['message'] = 'Returning all producer activity for the song';
                $this->response['status'] = 200;
                $this->response['data'] = $data;
                return $this->response;
                exit;
                
            } else {
                $this->response['message'] = 'No producer activity for this song';
                $this->response['status'] = 200;
                $this->response['data'] = $data;
                return $this->response;
                exit;
            }
            
        } catch (PDOException $e) {
            $info = $sth->errorInfo();
            $this->response['status']=500;
            $this->response['message']='Caught Exception ' . $e . $info;
            $this->response['data'] = '';
            return $this->response;
            exit;
        }
    }
    
}