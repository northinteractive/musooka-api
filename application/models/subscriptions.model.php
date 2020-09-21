<?php

class Subscription {
    
    private $DB;
    private $response;
    private $httpObj;

    private $key;
    private $userId;
    private $auth;
    private $playlistId;
    private $ownerId;
    
    public function __construct() {
        $this->auth = false;
        $this->DB = new DB();
        
        $this->key = false;
        $this->userId = false;
        $this->ownerId = false;
        $this->playlistId = false;
        
        $this->response = array();
        $this->response['message'] = '';
        $this->response['status'] = null;
        $this->response['data'] = '';        
    }
    
    public function subscriptions($httpObj) {
        $POST = $httpObj->getRequestVars();
        
        if(authorize::key($POST['user_id'],$POST['key'])) {
            
            $this->userId = $POST['user_id'];
            
            $q = "SELECT
                    playlists.name AS playlist_name,
                    playlists.user_id AS owner_id,
                    playlists.id AS playlist_id,
                    (SELECT COUNT(playlist_songs.id) FROM playlist_songs WHERE playlist_songs.playlist_id = playlists.id) AS song_count
                FROM playlist_follows
                LEFT JOIN playlists ON (playlist_follows.playlist_id = playlists.id)
                WHERE playlist_follows.user_id = :user_id ORDER BY playlist_name
                ";
                
            $sth = $this->DB->prepare($q);
            $sth->bindParam(":user_id",$this->userId);
            
            try{
                $sth->execute();
                $subscriptions = $sth->fetchAll();
                
                $n = count($subscriptions);
                
                $data = array();
                
                for($i=0;$i<$n;$i++) {
                    $data[$i]['subscriptions']['playlist_name'] = $subscriptions[$i]['playlist_name'];
                    $data[$i]['subscriptions']['owner_id'] = $subscriptions[$i]['owner_id'];
                    $data[$i]['subscriptions']['playlist_id'] = $subscriptions[$i]['playlist_id'];
                    $data[$i]['subscriptions']['song_count'] = $subscriptions[$i]['song_count'];
                    $data[$i]['subscribed'] = 1;
                }
                
                $this->response['message'] = 'Returning all subscribed playlists';
                $this->response['status']  = 200;
                $this->response['data'] = $data;
                return $this->response;
                exit;
            } catch (PDOException $e) {
                
                $data['error'] = $sth->errorInfo();
                $data['exception'] = $e;
                
                $this->response['message'] = 'There was an error';
                $this->response['status']  = 500;
                $this->response['data'] = $data;
                return $this->response;
                exit;
                
            }
            
        } else {
            $this->response['message'] = 'Invalid Login Credentials';
            $this->response['status']  = 401;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
    
    public function subscribe($httpObj){
        $POST = $httpObj->getRequestVars();
        
        if(authorize::key($POST['user_id'],$POST['key'])) {
            
            if($POST['playlist_id']!='') {
                $this->playlistId = $POST['playlist_id'];
            } else {
                $this->response['message'] = 'You must provide a playlist_id';
                $this->response['status']  = 500;
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
            
            $this->userId = $POST['user_id'];
            
            $q = "SELECT id AS subscription_id,playlist_id,user_id FROM playlist_follows WHERE user_id = :user_id AND playlist_id = :playlist_id";
            $sth = $this->DB->prepare($q);
            $sth->bindParam(":user_id",$this->userId);
            $sth->bindParam(":playlist_id",$this->playlistId);
            
            try{
                $sth->execute();
                
                $existing_subscription = $sth->fetchAll();
                    
                if(isset($existing_subscription[0]['user_id']) && ($existing_subscription[0]['user_id'] == $this->userId)) {
                    
                    $this->response['message'] = 'You have already subscribed to this playlist';
                    $this->response['status']  = 409;
                    $this->response['data'] = $existing_subscription[0]['subscription_id'];
                    return $this->response;
                    exit;
                    
                } else {
                    $q = "  INSERT INTO playlist_follows (user_id,playlist_id,owner_id)
                    values (:user_id,:playlist_id,
                        (SELECT user_id FROM playlists WHERE id = :playlist_id LIMIT 1)
                    )";
                
                    $sth = $this->DB->prepare($q);
                    $sth->bindParam(":user_id",$this->userId);
                    $sth->bindParam(":playlist_id",$this->playlistId);
                    
                    try{
                        $sth->execute();
                        
                        $data['playlist_id'] = $this->playlistId;
                        
                        $this->response['message'] = 'successfully subscribed';
                        $this->response['status']  = 200;
                        $this->response['data'] = $data;
                        // $this->response['data'] = $sth->errorInfo();
                        return $this->response;
                        exit;
                    } catch (PDOException $e) {
                        $data['error'] = $sth->errorInfo();
                        $data['exception'] = $e;
                        
                        $this->response['message'] = 'There was an error';
                        $this->response['status']  = 500;
                        $this->response['data'] = $data;
                        return $this->response;
                        exit;
                    }
                }
            } catch (PDOException $e) {
                $data['error'] = $sth->errorInfo();
                $data['exception'] = $e;
                
                $this->response['message'] = 'There was an error';
                $this->response['status']  = 500;
                $this->response['data'] = $data;
                return $this->response;
                exit;
            }
        } else {
            $this->response['message'] = 'Invalid Login Credentials';
            $this->response['status']  = 401;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
    
    public function unsubscribe($httpObj) {
        $POST = $httpObj->getRequestVars();
        
        if(authorize::key($POST['user_id'],$POST['key'])) {
            
            if($POST['playlist_id']!='') {
                $this->playlistId = $POST['playlist_id'];
            } else {
                $this->response['message'] = 'You must provide a playlist_id';
                $this->response['status']  = 500;
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
            
            $this->userId = $POST['user_id'];
            
            $q = "DELETE FROM playlist_follows WHERE user_id = :user_id AND playlist_id = :playlist_id";
            $sth = $this->DB->prepare($q);
            $sth->bindParam(":user_id",$this->userId);
            $sth->bindParam(":playlist_id",$this->playlistId);
            
            try{
                $sth->execute();
                $a = $sth->rowCount();
                
                if($a>0) {
                    $this->response['message'] = 'Successfully removed the playlist from your subscription list';
                    $this->response['status']  = 200;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                } else {
                    $this->response['message'] = 'Subscription was not removed, nothing to do';
                    $this->response['status']  = 404;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                }
            } catch (PDOException $e) {
                $data['error'] = $sth->errorInfo();
                $data['exception'] = $e;
                
                $this->response['message'] = 'There was an error';
                $this->response['status']  = 500;
                $this->response['data'] = $data;
                return $this->response;
                exit;
            }
            
        } else {
            $this->response['message'] = 'Invalid Login Credentials';
            $this->response['status']  = 401;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
}