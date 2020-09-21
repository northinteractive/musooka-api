<?php

/* ========================================
* 
*       Artists - Updated November 2012
* 
======================================== */

interface artistTemplate {
    public function __construct();
    public function return_null();
    public function update_bio($httpObj); // Tool to update artist bio - used by manager panel
    public function create($httpObj); // Create a new artist
    public function load($httpObj); // Primary Load artist functions || Requires caching layer
}

/* ========================================
* 
*       Artist Model 
 * 
 *      To-Do:
 *      1) Requires full cache implementation for artist info 
 *      2) Requires full cache implentation for songs 
 *  
 *      ** Functions are defined and cache is developed - check cache.utility.php
* 
======================================== */

class Artist implements artistTemplate {
    
    private $DB;
    private $response;
    private $httpObj;
    private $s3;

    private $key;
    private $userId;
    private $artistId;
    private $auth;
    private $rank;
    
    public $songs;
    public $data;
    
    public function __construct() {
        $this->DB = new DB();
        $this->s3 = new AmazonS3();
        $this->artistId = false;
        $this->userId = false;
        $this->key = false;
        $this->songs = array();
        $this->data = array();
        
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
    
    public function update_bio($httpObj) {
        $POST = $httpObj->getRequestVars();
        
        if(authorize::key($POST['user_id'],$POST['key'])) {
            $this->userId       = $POST['user_id'];
            $this->bio          = html_entity_decode($POST['bio']);
            $this->web          = html_entity_decode($POST['web']);
            $this->auth         = true;
        }
        
        if ($this->auth) {
            
            $q = "UPDATE artists SET bio = :bio, web = :web WHERE user_id = :user_id LIMIT 1";
            $sth = $this->DB->prepare($q);
            $sth->bindParam(":bio",substr($this->bio,0,256));
            $sth->bindParam(":web",substr($this->web,0,255));
            $sth->bindParam(":user_id",$this->userId);
            
            try {
                $sth->execute();
                
                $data['bio'] = $this->bio;
                
                $this->response['status']=200;
                $this->response['message']='Successfully updated your bio';
                $this->response['data'] = $data;
                return $this->response; 
                exit;
                
            } catch (PDOException $e) {
                $info = $sth->errorInfo();
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e . $info;
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
            
        } else {
            $info = $sth->errorInfo();
            $this->response['status']=401;
            $this->response['message']='Invalid Authorization Credentials';
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
    
    public function create($httpObj) {
        $POST = $httpObj->getRequestVars();
        
        if(authorize::key($POST['user_id'],$POST['key'])) {
            $this->userId       = $POST['user_id'];
            $this->artistName   = $POST['artist_name'];
            $this->vanity       = preg_replace("/[^a-z0-9]/","", str_replace(" ","",strtolower($this->artistName)));
            $this->auth=true;
        }
        
        if ($this->auth) {
            $q = "SELECT vanity,user_id FROM artists WHERE vanity=:vanity OR user_id=:user_id LIMIT 1";
            $sth = $this->DB->prepare($q);
            
            $sth->bindParam(":vanity",$this->vanity);
            $sth->bindParam(":user_id",$this->userId);
            
            try { $sth->execute(); } catch (PDOException $e) {
                $info = $sth->errorInfo();
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e . $info;
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
            
            $existing = $sth->fetchAll();
            
            if(isset($existing[0]['vanity']) && ($this->vanity == $existing[0]['vanity'])) {
                $this->response['status']=409;
                $this->response['message']='There is already an artist named '.$this->artistName;
                $this->response['data'] = array();
                return $this->response;
                exit;
            } else if(isset($existing[0]['user_id']) && ($this->userId == $existing[0]['user_id'])) {
                $this->response['status']=409;
                $this->response['message']='This account is already associated with an artist';
                $this->response['data'] = array();
                return $this->response;
                exit;
            } else {
                
                $q = "INSERT INTO artists (name,vanity,user_id,avatar,bio,timestamp_created)
                        values (:artist_name,:vanity,:user_id,'https://s3.amazonaws.com/muzooka-website/images/default.png','Write a short bio about your artist or band.',:timestamp);";
                    
                $sth = $this->DB->prepare($q);
                
                $sth->bindParam(":artist_name",$this->artistName);
                $sth->bindParam(":vanity",$this->vanity);
                $sth->bindParam(":user_id",$this->userId);
                $sth->bindParam(":timestamp",time());
                    
                try {
                    
                    $sth->execute(); 
                    
                    // Set User Id
                    $lastId = $this->DB->lastInsertId();
                    
                    $s3_success = false;
                    
                    if($this->s3->if_bucket_exists('muzooka-devel') && !empty($lastId)) {
                        $s3_success = true;
                        
                        $response = $this->s3->create_object('muzooka-devel', "artists/".$lastId."/", array(
                            'acl' => AmazonS3::ACL_PUBLIC,
                            'length' => 0
                        ));
                        $s3_success = $response;
                    }
                    
                    /*************** FINISHED S3 STUFF *******************/
                    
                    if($s3_success) {
                        
                        $data['artist_id'] = $lastId;
                        $data['artist_name'] = $this->artistName;
                        $data['artist_vanity'] = $this->vanity;
                        
                        $this->response['status']=200;
                        $this->response['message']='Successfully created a new artist';
                        $this->response['data'] = $data;
                        return $this->response; 
                        exit;
                        
                    } else {
                        
                        $err = array();
                        $err = $sth->errorInfo();
                        
                        $this->response['status']=500;
                        $this->response['message']='Sorry, there was a server error';
                        $this->response['data'] = $err;
                        return $this->response; 
                        exit;
                    }
                    
                } catch (PDOException $e) {
                    $info = $sth->errorInfo();
                    $this->response['status']=500;
                    $this->response['message']='Caught Exception ' . $e . $info;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                }
                
            }
        } else {
            $info = $sth->errorInfo();
            $this->response['status']=401;
            $this->response['message']='Invalid Authorization Credentials';
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
    
    public function load($httpObj) {
        $POST = $httpObj->getRequestVars();
        $this->DB = new DB();
        
        if(isset($POST['artist_id']) && ($POST['artist_id']!='')) { $this->artistId = $POST['artist_id']; } else { $this->artistId=false; }
        if(isset($POST['song_id']) && ($POST['song_id']!='')) { $this->songId = $POST['song_id']; } else { $this->songId=false; }
        if(isset($POST['user_id']) && ($POST['user_id']!='')) { $this->userId = $POST['user_id']; } else { $this->userId=false; }
        if(isset($POST['vanity']) && ($POST['vanity']!='')) { $this->vanity = $POST['vanity']; } else { $this->vanity=false; }
        
        
        if(!$this->artistId && !$this->vanity && !$this->userId) {
            $this->response = array();
            $this->response['message'] = 'A required field was left empty. Please provide an artist_id';
            $this->response['status'] = 412;
            $this->response['data'] = '';
            return $this->response;
            exit;
        }
        
        /* ========================================
        * 
        *       Still Requires Caching Layer
        * 
        ======================================== */
        
        if(isset($this->vanity) && ($this->vanity!='')) {
            
            // Mysql query
            $q="SELECT
                artists.id AS artist_id,
                artists.name AS artist_name,
                artists.avatar,
                artists.bio,
                artists.web,
                users.vanity,
                users.id AS user_id,
                artists.producer,
                (SELECT id FROM follows WHERE user_id=:user_id AND artist_id=artists.id LIMIT 1) as follow_id,
                (SELECT count(follows.id) FROM follows WHERE follows.artist_id = artists.id) AS follow_count,
                (SELECT count(updates.id) FROM updates WHERE updates.artist_id = artists.id) AS update_count,
                (SELECT count(votes.id) FROM songs LEFT JOIN votes ON (votes.song_id=songs.id) WHERE songs.artist_id=artists.id) AS vote_count
            FROM artists
            LEFT JOIN users ON artists.user_id=users.id
            WHERE users.vanity=:vanity LIMIT 1";
            
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":vanity",$this->vanity);
            $sth->bindParam(":user_id",$this->userId);
            
        } else if(isset($this->artistId) && ($this->artistId!='')) {
        
            //Mysql query
            $q="SELECT
                artists.id AS artist_id,
                artists.name AS artist_name,
                artists.avatar,
                artists.bio,
                artists.web,
                users.vanity,
                users.id AS artist_user_id,
                artists.producer,
                (SELECT id FROM follows WHERE user_id=:user_id AND artist_id=:artist_id LIMIT 1) as follow_id,
                (SELECT count(follows.id) FROM follows WHERE follows.artist_id = artists.id) AS follow_count,
                (SELECT count(updates.id) FROM updates WHERE updates.artist_id = artists.id) AS update_count,
                (SELECT count(votes.id) FROM songs LEFT JOIN votes ON (votes.song_id=songs.id) WHERE songs.artist_id=artists.id) AS vote_count
            FROM artists
            LEFT JOIN users ON artists.user_id=users.id
            WHERE artists.id = :artist_id LIMIT 1";
            
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":artist_id",$this->artistId);
            $sth->bindParam(":user_id",$this->userId);
            
        } else if(isset($this->userId) && ($this->userId!='')) {
            
            //Mysql query
            $q="SELECT
                artists.id AS artist_id,
                artists.name AS artist_name,
                artists.avatar,
                artists.bio,
                artists.web,
                users.vanity,
                users.id AS artist_user_id,
                artists.producer,
                (SELECT follows.id FROM follows WHERE follows.user_id=:user_id AND follows.artist_id=artists.id LIMIT 1) as follow_id,
                (SELECT count(follows.id) FROM follows WHERE follows.artist_id = artists.id) AS follow_count,
                (SELECT count(updates.id) FROM updates WHERE updates.artist_id = artists.id) AS update_count,
                (SELECT count(votes.id) FROM songs LEFT JOIN votes ON (votes.song_id=songs.id) WHERE songs.artist_id=artists.id) AS vote_count
            FROM artists
            LEFT JOIN users ON artists.user_id=users.id
            WHERE artists.user_id = :user_id LIMIT 1";
            
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":user_id",$this->userId);
            
        }
        
        // Retrieve artist info
        try {
            $sth->execute();
            $temp=$sth->fetchAll();
            $artist=$temp[0];
            
            $this->artistId = $artist['artist_id'];
            $this->producer = $artist['producer'];
            
            
        } catch (PDOException $e) {
            $info = $sth->errorInfo();
            $this->response['status']=500;
            $this->response['message']='Caught Exception ' . $e . $info;
            $this->response['data'] = '';
            return $this->response;
            exit;
        }
        
        if(!empty($this->artistId)) {
                
                    /* ========================================
                    * 
                    *       Requires  caching (future)
                    * 
                    ======================================== */
            
                    // Getting artist avatars
                    $avatar = json_decode(general::get_artist_avatar($this->artistId));
                    $this->data['artist']['ios_avatar'] = $avatar->ios;
                    $this->data['artist']['crop_avatar'] = $avatar->crop;
                    // Done artist avatars
            
	$this->data['artist']['artist_id']=$artist['artist_id'];
	$this->data['artist']['artist_name']=$artist['artist_name'];
	$this->data['artist']['producer']=$artist['producer'];
	$this->data['follow']=$artist['follow_id'];
	$this->data['artist']['artist_avatar']=$artist['avatar'];
	$this->data['artist']['artist_bio']=$artist['bio'];
	$this->data['artist']['artist_web']=$artist['web'];
	$this->data['artist']['vote_count']=$artist['vote_count'];
	$this->data['artist']['follow_count']=$artist['follow_count'];
	$this->data['artist']['update_count']=$artist['update_count'];
	$this->data['artist']['artist_vanity']=$artist['vanity'];
	$this->data['user']['user_id']=$artist['artist_user_id'];
	$this->data['songs']=array();

                    /* ========================================
                    * 
                    *       Data can be pulled from cache instead (future, as with Songs model)
                    * 
                    ======================================== */
        
	$q="
	    SELECT id, artist_id, title, uri, vote_count, song_rank,price,
	    (SELECT id FROM votes WHERE votes.user_id=:user_id AND votes.song_id=songs.id) as user_vote,
                        (
                            (
                                SELECT COUNT(votes.id) FROM votes
                                LEFT JOIN users ON users.id = votes.user_id
                                LEFT JOIN artists ON artists.user_id = users.id
                                WHERE artists.producer = 1 AND votes.song_id = songs.id
                            )
                        ) AS producer_activity
	    FROM songs
	    WHERE artist_id=:artist_id
	    ORDER BY upload_stamp DESC";
	$sth=$this->DB->prepare($q);
	
	$sth->bindParam(":artist_id",$this->artistId);
	$sth->bindParam(":user_id",$this->userId);
	
	try {
	    $sth->execute();
	    $this->songs=$sth->fetchAll();
	    $n = count($this->songs);
	    
	    if($n>0) {
	        for($i=0;$i<$n;$i++) {
		  $this->data['songs'][$i]['song_id']=$this->songs[$i]['id'];
		  $this->data['songs'][$i]['artist_id']=$this->songs[$i]['artist_id'];
		  $this->data['songs'][$i]['song_title']=$this->songs[$i]['title'];
		  $this->data['songs'][$i]['song_uri']=$this->songs[$i]['uri'];
		  $this->data['songs'][$i]['vote_count']=$this->songs[$i]['vote_count'];
		  $this->data['songs'][$i]['song_rank']=$this->songs[$i]['song_rank'];
                                          $this->data['songs'][$i]['price']=$this->songs[$i]['price'];
		  $this->data['songs'][$i]['user_vote']=$this->songs[$i]['user_vote'];
                                                
		  $this->data['songs'][$i]['producer_activity']=$this->songs[$i]['producer_activity'];
		  if(($this->songId) && ($this->songId==$this->songs[$i]['id'])) {
		      $this->data['songs'][$i]['selected']=1;
		  } else {
		      $this->data['songs'][$i]['selected']=0;
		  }
	        }
	    } else {
	        $this->data['songs']=array();
	    }
	} catch (PDOException $e) {
	    $info = $sth->errorInfo();
	    $this->response['status']=500;
	    $this->response['message']='Caught Exception ' . $e . $info;
	    $this->response['data'] = '';
	    return $this->response;
	    exit;
	}
            
            $this->response = array();
            $this->response['message'] = 'Returning all information for '.$this->data['artist']['artist_name'];
            $this->response['status'] = 200;
            $this->response['data'] = $this->data;
            return $this->response;
            exit;
            
        } else {
            
            $q = "SELECT
                users.id AS user_id,
                users.vanity
                FROM users WHERE users.vanity=:vanity LIMIT 1";
            $sth = $this->DB->prepare($q);
            $sth->bindParam(":vanity",$this->vanity);
            
            try {
                
                $sth->execute();
                $this->user=$sth->fetchAll();
                $n = count($this->user);
                
                if($n > 0) {
                    
                    $this->data['user_id']=$this->user[0]['user_id'];
                    $this->data['vanity']=$this->vanity;
                    
                    $q = "SELECT
                        songs.id AS song_id,
                        songs.title,
                        songs.artist_name,
                        artists.id AS artist_id,
                        votes.timestamp
                        FROM votes
                        LEFT JOIN songs ON songs.id = votes.song_id
                        LEFT JOIN artists ON songs.artist_id = artists.id
                        WHERE votes.user_id = :user_id ORDER BY votes.timestamp DESC";
                    $sth = $this->DB->prepare($q);
                    $sth->bindParam(":user_id",$this->data['user_id']);
                    $sth->execute();
                    $this->votes=$sth->fetchAll();
                    $n = count($this->votes);
                    
                    $this->data['votes']=array();
                    
                    for($i=0;$i<$n;$i++) {
                        $this->data['votes'][$i]['song_title'] = $this->votes[$i]['title'];
                        $this->data['votes'][$i]['artist_name'] = $this->votes[$i]['artist_name'];
                        $this->data['votes'][$i]['artist_id'] = $this->votes[$i]['artist_id'];
                        $this->data['votes'][$i]['song_id'] = $this->votes[$i]['song_id'];
                        $this->data['votes'][$i]['timestamp'] = time::time_approximator($this->votes[$i]['timestamp']);
                    }
                    
                    $q = "SELECT
                            playlists.name,
                            playlists.id AS playlist_id,
                            (SELECT count(id) FROM playlist_follows WHERE playlist_follows.playlist_id = playlists.id) AS subscriber_count,
                            (SELECT count(id) FROM playlist_songs WHERE playlist_songs.playlist_id = playlists.id) AS song_count
                            FROM playlists
                            WHERE playlists.user_id = :user_id";
                        
                    $sth = $this->DB->prepare($q);
                    $sth->bindParam(":user_id",$this->data['user_id']);
                    $sth->execute();
                    $this->playlists=$sth->fetchAll();
                    $n = count($this->playlists);
                    
                    $this->data['playlists']=array();
                    $this->data['playlist_count'] = $n;
                    
                    $subscribers = 0;
                    
                    for($i=0;$i<$n;$i++) {
                        $this->data['playlists'][$i]['name'] = $this->playlists[$i]['name'];
                        $this->data['playlists'][$i]['playlist_id'] = $this->playlists[$i]['playlist_id'];
                        $this->data['playlists'][$i]['song_count'] = $this->playlists[$i]['song_count'];
                        $this->data['playlists'][$i]['subscriber_count'] = $this->playlists[$i]['subscriber_count'];
                        $subscribers+=$this->playlists[$i]['subscriber_count'];
                    }
                    $this->data['subscriber_count'] = $subscribers;
                    
                    $this->response = array();
                    $this->response['message'] = 'Returning all information for user';
                    $this->response['status'] = 201;
                    $this->response['data'] = $this->data;
                    return $this->response;
                    exit;
                    
                } else {
                    $this->response = array();
                    $this->response['message'] = 'User not found';
                    $this->response['status'] = 404;
                    $this->response['data'] = $this->data;
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
}