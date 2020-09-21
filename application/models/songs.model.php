<?php

/* ========================================
* 
*       Core Song Model
 * 
 *      To Do:
 *      1) Full cache functionality for songs and playlists (33% finished) (WIP)
* 
======================================== */

interface songInterface {
    public function __construct();
    
    // Song Tools and list tools
    public function load_song_from_cache($httpObj);
    public function set_price($httpObj);
    public function load_top($httpObj);
    public function load_new($httpObj);
    public function load_hot($httpObj);
    public function uri($httpObj);
    
    // Playlist implementation
    public function create_playlist($httpObj);
    public function add_song_to_playlist($httpObj);
    public function remove_song_from_playlist($httpObj);
    public function remove_playlist_item($httpObj);
    public function load_playlist_songs($httpObj);
    public function load_playlists($httpObj);
}

class Songs implements songInterface {

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
        $this->userId = false;

        $this->loadStart = 0;
        $this->loadEnd = $this->loadStart+20;

        $this->response = array();
        $this->response['message'] = '';
        $this->response['status'] = null;
        $this->response['data'] = '';
    }
    
    // Pull song from Redis
    public function load_song_from_cache($httpObj) {
        $POST = $httpObj->getRequestVars();
        
        if(isset($POST['song_id']) && ($POST['song_id']!='')) {
            
            // Pull song from cache
            $song = cache::song_from_cache($POST['song_id']);
            
            if($song) {
                $this->response['status']=500;
                $this->response['message']='Here comes the song';
                $this->response['data'] = $song;
                return $this->response;
                exit;
            } else {
                cache::cache_song($POST['song_id']);
                $this->response['status']=500;
                $this->response['message']='No song found';
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
        } else {
            $this->response['status']=500;
            $this->response['message']='No data passed. Please provide a song_id';
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
    
    public function set_price($httpObj) {
        $POST = $httpObj->getRequestVars();
        $db = new DB();
        
        if(authorize::key($POST['user_id'],$POST['key'])) {
            
            if(isset($POST['price']) && isset($POST['song_id'])) {
            
                $this->songId = $POST['song_id'];
                $this->price = preg_replace("/[^0-9\.]/","",$POST['price']);
                
                $song = cache::song_from_cache($this->songId);
                if($song) {
                    $this->songOwnerId = cache::user_id_from_artist_id($song->artist_id);
                    if($this->songOwnerId == $POST['user_id']) {
                        
                        $q = "UPDATE songs SET price=:price WHERE songs.id=:song_id LIMIT 1";
                        $sth = $db->prepare($q);
                        $sth->bindParam(":price",$this->price);
                        $sth->bindParam(":song_id",$this->songId);
                        
                        if($sth->execute()) {
                            cache::cache_song($this->songId);
                            $this->response['status']=200;
                            $this->response['message']='Successfully updated song, set price as $'.$this->price;
                            $this->response['data'] = array("song_id" => $this->songId, "price" => $this->price);
                            return $this->response;
                            exit;
                            
                        } else {
                            $this->response['status']=500;
                            $this->response['message']='Failed to update datebase';
                            $this->response['data'] = $sth->errorInfo();
                            return $this->response;
                            exit;
                        }
                                
                        
                    } else {
                        $this->response['status']=500;
                        $this->response['message']='You are not the owner of this song';
                        $this->response['data'] = array();
                        return $this->response;
                        exit;
                    }
                } else {
                    $this->response['status']=500;
                    $this->response['message']='Invalid Song Id';
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                }
                
            } else {
                $this->response['status']=500;
                $this->response['message']='A song_id and price are required';
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
            
        } else {
            $this->response['status']=500;
            $this->response['message']='Not Authorized';
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }

    public function load_top($httpObj) {
        $POST = $httpObj->getRequestVars();

        // Check load pagination
        if(isset($POST['load_start'])) {
            $this->loadStart = $POST['load_start'];
        } else {
            $this->loadStart=0;
        }

        if(isset($POST['user_id']) && ($POST['user_id']!=''))     { $this->userId   = $POST['user_id'];   } else { $this->userId=false; }
        if(isset($POST['artist_id']) && ($POST['artist_id']!='')) { $this->artistId = $POST['artist_id']; } else { $this->artistId=false; }

        if($this->userId) {
            
            $q = "SELECT genre,filtering FROM users WHERE id=:id LIMIT 1";
            $sth = $this->DB->prepare($q);
            $sth->bindParam(":id",$this->userId);
            $sth->execute();
            $g = $sth->fetchAll();
            
            $q="SELECT
                songs.id,
                songs.title,
                songs.published,
                songs.uri,
                songs.artist_id,
                songs.album_id,
                songs.song_rank,
                songs.vote_count,

                artists.name,
                artists.id AS artist_id,
                artists.avatar,
                users.vanity,
                (
                    (
                        SELECT COUNT(votes.id) FROM votes
                        LEFT JOIN users ON users.id = votes.user_id
                        LEFT JOIN artists ON artists.user_id = users.id
                        WHERE artists.producer = 1 AND votes.song_id = songs.id
                    )
                ) AS producer_activity,

                (SELECT id FROM votes WHERE user_id=:user_id AND song_id=songs.id) as user_vote
            FROM songs

            INNER JOIN artists ON songs.artist_id = artists.id
            LEFT JOIN users ON artists.user_id=users.id ";
            
            if($g[0]['filtering']==1) {
                
                $genres = json_decode($g[0]['genre']);
                $genreList = array();
                
                foreach($genres AS $k => $v) {
                    if($v == 1) {
                        array_push($genreList,$k);
                    }
                    
                }
                
                $filter = implode(",",$genreList);
                
                $q .= " WHERE songs.genre IN (".$filter.") ";
            }

            $q .= " ORDER BY songs.vote_count DESC LIMIT ".$this->loadStart.",20";

            $sth=$this->DB->prepare($q);
            $sth->bindParam(":user_id",$this->userId);

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
                    $updateinfo[$i]['song_id']=$data[$i]['id'];
                    $updateinfo[$i]['song_title']=trim($data[$i]['title']);
                    $updateinfo[$i]['song_uri']=$data[$i]['uri'];
                    $updateinfo[$i]['song_published']=$data[$i]['published'];
                    $updateinfo[$i]['song_rank']=$data[$i]['song_rank'];
                    $updateinfo[$i]['vote_count']=$data[$i]['vote_count'];
                    $updateinfo[$i]['album_id']=$data[$i]['album_id'];

                    $updateinfo[$i]['user_vote']=$data[$i]['user_vote'];

                    $updateinfo[$i]['artist_id']=$data[$i]['artist_id'];
                    
                    // Getting artist avatars
                    $avatar = cache::avatar_from_cache($data[$i]['artist_id']);
                    $updateinfo[$i]['ios_avatar'] = $avatar->ios;
                    $updateinfo[$i]['crop_avatar'] = $avatar->crop;
                    // Done artist avatars
                    
                    $updateinfo[$i]['artist_avatar']=$data[$i]['avatar'];
                    $updateinfo[$i]['artist_name']=$data[$i]['name'];
                    $updateinfo[$i]['artist_vanity']=$data[$i]['vanity'];
                    $updateinfo[$i]['producer_activity']=$data[$i]['producer_activity'];
                }

                $this->response['message'] = 'top';
                $this->response['status']  = 200;
                $this->response['data'] = $updateinfo;
                return $this->response;
                exit;

            } else {
                $this->response['message'] = 'No Songs match this users genre selection';
                $this->response['status']  = 200;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
        } else {
            $q="SELECT
                songs.id,
                songs.title,
                songs.published,
                songs.uri,
                songs.artist_id,
                songs.album_id,
                songs.song_rank,
                songs.vote_count,

                artists.name,
                artists.id AS artist_id,
                artists.avatar,
                users.vanity,
                (
                    (
                        SELECT COUNT(votes.id) FROM votes
                        LEFT JOIN users ON users.id = votes.user_id
                        LEFT JOIN artists ON artists.user_id = users.id
                        WHERE artists.producer = 1 AND votes.song_id = songs.id
                    )
                ) AS producer_activity
            FROM songs
            INNER JOIN artists ON songs.artist_id = artists.id
            LEFT JOIN users ON artists.user_id=users.id
            
            ORDER BY songs.vote_count DESC LIMIT ".$this->loadStart.",20";

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

            if($n>0) {
                $updateinfo=array();
                // Spit out update array
                for($i=0;$i<$n;$i++) {
                    $updateinfo[$i]['song_id']=$data[$i]['id'];
                    $updateinfo[$i]['song_title']=trim($data[$i]['title']);
                    $updateinfo[$i]['song_uri']=$data[$i]['uri'];
                    $updateinfo[$i]['song_published']=$data[$i]['published'];
                    $updateinfo[$i]['song_rank']=$data[$i]['song_rank'];
                    $updateinfo[$i]['vote_count']=$data[$i]['vote_count'];
                    $updateinfo[$i]['album_id']=$data[$i]['album_id'];

                    $updateinfo[$i]['artist_id']=$data[$i]['artist_id'];
                    
                    // Getting artist avatars
                    $avatar = cache::avatar_from_cache($data[$i]['artist_id']);
                    $updateinfo[$i]['ios_avatar'] = $avatar->ios;
                    $updateinfo[$i]['crop_avatar'] = $avatar->crop;
                    // Done artist avatars
                    
                    $updateinfo[$i]['artist_avatar']=$data[$i]['avatar'];
                    $updateinfo[$i]['artist_name']=$data[$i]['name'];
                    $updateinfo[$i]['artist_vanity']=$data[$i]['vanity'];
                    $updateinfo[$i]['producer_activity']=$data[$i]['producer_activity'];
                }

                $this->response['message'] = 'top';
                $this->response['status']  = 200;
                $this->response['data'] = $updateinfo;
                return $this->response;
                exit;

            } else {

                $this->response['message'] = 'No Data Returned for this user. No songs found';
                $this->response['status']  = 200;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
        }
    }

    public function load_new($httpObj) {
        $POST = $httpObj->getRequestVars();

        // Check load pagination
        if(isset($POST['load_start'])) {
            $this->loadStart = $POST['load_start'];
        } else {
            $this->loadStart=0;
        }

        if(isset($POST['user_id']) && ($POST['user_id']!=''))     { $this->userId   = $POST['user_id'];   } else { $this->userId=false; }
        if(isset($POST['artist_id']) && ($POST['artist_id']!='')) { $this->artistId = $POST['artist_id']; } else { $this->artistId=false; }

        if($this->userId) {
            
            $q = "SELECT genre,filtering FROM users WHERE id=:id LIMIT 1";
            $sth = $this->DB->prepare($q);
            $sth->bindParam(":id",$this->userId);
            $sth->execute();
            $g = $sth->fetchAll();
            
            $q="SELECT
                songs.id,
                songs.title,
                songs.published,
                songs.uri,
                songs.artist_id,
                songs.album_id,
                songs.song_rank,
                songs.vote_count,

                artists.name,
                artists.id AS artist_id,
                artists.avatar,
                users.vanity,
                (
                    (
                        SELECT COUNT(votes.id) FROM votes
                        LEFT JOIN users ON users.id = votes.user_id
                        LEFT JOIN artists ON artists.user_id = users.id
                        WHERE artists.producer = 1 AND votes.song_id = songs.id
                    )
                ) AS producer_activity,
                (SELECT id FROM votes WHERE user_id=:user_id AND song_id=songs.id LIMIT 1) as user_vote
            FROM songs

            INNER JOIN artists ON songs.artist_id = artists.id
            LEFT JOIN users ON artists.user_id=users.id ";
            
            if($g[0]['filtering']==1) {
                
                $genres = json_decode($g[0]['genre']);
                $genreList = array();
                
                foreach($genres AS $k => $v) {
                    if($v == 1) {
                        array_push($genreList,$k);
                    }
                    
                }
                
                $filter = implode(",",$genreList);
                
                $q .= " WHERE songs.genre IN (".$filter.") ";
            }

            $q .= " ORDER BY songs.upload_stamp DESC LIMIT ".$this->loadStart.",20";

            $sth=$this->DB->prepare($q);
            $sth->bindParam(":user_id",$this->userId);

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
                    $updateinfo[$i]['song_id']=$data[$i]['id'];
                    $updateinfo[$i]['song_title']=trim($data[$i]['title']);
                    $updateinfo[$i]['song_uri']=$data[$i]['uri'];
                    $updateinfo[$i]['song_published']=$data[$i]['published'];
                    $updateinfo[$i]['song_rank']=$data[$i]['song_rank'];
                    $updateinfo[$i]['vote_count']=$data[$i]['vote_count'];
                    $updateinfo[$i]['album_id']=$data[$i]['album_id'];

                    $updateinfo[$i]['user_vote']=$data[$i]['user_vote'];
                    $updateinfo[$i]['artist_id']=$data[$i]['artist_id'];
                    
                    // Getting artist avatars
                    $avatar = cache::avatar_from_cache($data[$i]['artist_id']);
                    $updateinfo[$i]['ios_avatar'] = $avatar->ios;
                    $updateinfo[$i]['crop_avatar'] = $avatar->crop;
                    // Done artist avatars
                    
                    $updateinfo[$i]['artist_avatar']=$data[$i]['avatar'];
                    $updateinfo[$i]['artist_name']=$data[$i]['name'];
                    $updateinfo[$i]['artist_vanity']=$data[$i]['vanity'];
                    $updateinfo[$i]['producer_activity']=$data[$i]['producer_activity'];
                }

                $this->response['message'] = 'new';
                $this->response['status']  = 200;
                $this->response['data'] = $updateinfo;
                return $this->response;
                exit;

            } else {
                $this->response['message'] = 'No Songs match this users genre selection';
                $this->response['status']  = 200;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
        } else {
            $q="SELECT
                songs.id,
                songs.title,
                songs.published,
                songs.uri,
                songs.artist_id,
                songs.album_id,
                songs.song_rank,
                songs.vote_count,

                artists.name,
                artists.id AS artist_id,
                artists.avatar,
                users.vanity,
                (
                    (
                        SELECT COUNT(votes.id) FROM votes
                        LEFT JOIN users ON users.id = votes.user_id
                        LEFT JOIN artists ON artists.user_id = users.id
                        WHERE artists.producer = 1 AND votes.song_id = songs.id
                    )
                ) AS producer_activity
            FROM songs
            INNER JOIN artists ON songs.artist_id = artists.id
            LEFT JOIN users ON artists.user_id=users.id
            ORDER BY songs.upload_stamp DESC LIMIT ".$this->loadStart.",20";

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

            if($n>0) {
                $updateinfo=array();
                // Spit out update array
                for($i=0;$i<$n;$i++) {
                    $updateinfo[$i]['song_id']=$data[$i]['id'];
                    $updateinfo[$i]['song_title']=trim($data[$i]['title']);
                    $updateinfo[$i]['song_uri']=$data[$i]['uri'];
                    $updateinfo[$i]['song_published']=$data[$i]['published'];
                    $updateinfo[$i]['song_rank']=$data[$i]['song_rank'];
                    $updateinfo[$i]['vote_count']=$data[$i]['vote_count'];
                    $updateinfo[$i]['album_id']=$data[$i]['album_id'];

                    $updateinfo[$i]['artist_id']=$data[$i]['artist_id'];
                    
                    // Getting artist avatars
                    $avatar = cache::avatar_from_cache($data[$i]['artist_id']);
                    $updateinfo[$i]['ios_avatar'] = $avatar->ios;
                    $updateinfo[$i]['crop_avatar'] = $avatar->crop;
                    // Done artist avatars
                    
                    $updateinfo[$i]['artist_avatar']=$data[$i]['avatar'];
                    $updateinfo[$i]['artist_name']=$data[$i]['name'];
                    $updateinfo[$i]['artist_vanity']=$data[$i]['vanity'];
                    $updateinfo[$i]['producer_activity']=$data[$i]['producer_activity'];
                }

                $this->response['message'] = 'new';
                $this->response['status']  = 200;
                $this->response['data'] = $updateinfo;
                return $this->response;
                exit;

            } else {

                $this->response['message'] = 'No Data Returned for this user. No songs found';
                $this->response['status']  = 200;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }
        }
    }

    public function load_hot($httpObj) {
        $POST = $httpObj->getRequestVars();
        
        // Check load pagination
        if(isset($POST['load_start'])) {
            $this->loadStart = $POST['load_start'];
        } else {
            $this->loadStart=0;
        }
        
        if(isset($POST['user_id']) && ($POST['user_id']!=''))     { $this->userId   = $POST['user_id'];   } else { $this->userId=false; }
        $cached_hot = cache::hot($this->loadStart,$this->userId);
        
        if($cached_hot) {
                $this->response['message'] = 'hot';
                $this->response['status']  = 200;
                $this->response['data'] = json_decode($cached_hot);
                return $this->response;
                exit;
        } else {
                $this->response['message'] = 'There was an error loading the hot list';
                $this->response['status']  = 500;
                $this->response['data'] = array();
                return $this->response;
                exit;
        }
    }

    public function remove_playlist($httpObj) {
        $POST = $httpObj->getRequestVars();

        if(authorize::key($POST['user_id'],$POST['key'])) {

            $this->userId=$POST['user_id'];
            $this->playlist_id=$POST['playlist_id'];

            $this->auth=true;
        }

        if($this->auth) {
            $q = "DELETE FROM playlists WHERE id=:playlist_id LIMIT 1";
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":playlist_id",$this->playlist_id);
            try {
                $sth->execute();
                $errors=false;
                $deleted = $sth->rowCount();
            } catch (PDOException $e) {
                $info = $sth->errorInfo();
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e . $info;
                $this->response['data'] = '';
                return $this->response;
                exit;
            }

            if($deleted==0) {
                $this->response['status']=404;
                $this->response['message']='This playlist does not exist, unable to delete';
                $this->response['data'] = array();
                return $this->response;
                exit;
            }

            if($errors==false) {
                $this->response['message'] = 'Successfully deleted the playlist';
                $this->response['status']  = 200;
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
        }
    }

    public function create_playlist($httpObj) {
        $POST = $httpObj->getRequestVars();

        if(authorize::key($POST['user_id'],$POST['key'])) {

            $this->userId=$POST['user_id'];
            $this->playlistName=$POST['playlist_name'];

            $this->auth=true;
        }

        if($this->auth) {

            if($this->playlistName=='') {
                $this->response['status']=500;
                $this->response['message']='Missing values. This method requires a playlist_name';
                $this->response['data']=array();
                return $this->response;
                exit;
            } else {
                $q = "SELECT name FROM playlists WHERE user_id=:user_id AND name=:name LIMIT 1";
                $sth = $this->DB->prepare($q);
                $sth->bindParam(":user_id",$this->userId);
                $sth->bindParam(":name",$this->playlistName);

                try { $sth->execute(); } catch (PDOException $e) {
                    $this->response['status']=500;
                    $this->response['message']='Caught Exception ' . $e . $info;
                    $this->response['data']=$info;
                    return $this->response;
                    exit;
                }

                $existing = $sth->fetchAll();
                if(isset($existing[0])) {
                    $existing = $existing[0]['name'];
                }


                if($existing == $this->playlistName) {

                    $this->response['message'] = 'You already have a playlist named ' . $this->playlistName;
                    $this->response['status']  = 200;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;

                } else if(strtolower($this->playlistName) == 'favorites') {

                    $this->response['message'] = 'You cannot create another favorites playlist';
                    $this->response['status']  = 200;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;

                } else {

                    $q = "INSERT INTO playlists (name,user_id) values (:name,:user_id)";
                    $sth = $this->DB->prepare($q);
                    $sth->bindParam(":name",$this->playlistName);
                    $sth->bindParam(":user_id",$this->userId);

                    try { $sth->execute(); } catch (PDOException $e) {
                        $this->response['status']=500;
                        $this->response['message']='Caught Exception ' . $e . $info;
                        $this->response['data']=$info;
                        return $this->response;
                        exit;
                    }

                    $lastId = $this->DB->lastInsertId();

                    $data['playlist_id'] = $lastId;
                    $data['playlist_name'] = $this->playlistName;

                    $this->response['message'] = 'Successfully created a new Playlist';
                    $this->response['status']  = 200;
                    $this->response['data'] = $data;
                    return $this->response;
                    exit;
                }
            } // Done checking empty name
        } else {
            $this->response['message'] = 'Authentication Failed';
            $this->response['status']  = 200;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
    
    public function add_song_to_playlist($httpObj) {
        $POST = $httpObj->getRequestVars();
        global $redisSearch;

        if(!isset($POST['song_id']) || !isset($POST['playlist_id'])) {
           $this->response['message'] = 'This function requires a song_id and playlist_id to be provided';
            $this->response['status']  = 200;
            $this->response['data'] = array();
            return $this->response;
            exit;
        } else {
            $this->songId = $POST['song_id'];
            $this->playlistId = $POST['playlist_id'];
        }

        $q="INSERT INTO playlist_songs (id,song_id,playlist_id,weight) values (0,:song_id,:playlist_id,0)";
        $sth=$this->DB->prepare($q);

        $sth->bindParam(":song_id",$this->songId);
        $sth->bindParam(":playlist_id",$this->playlistId);

        try { $sth->execute(); } catch (PDOException $e) {

            $this->response['status']=500;
            $this->response['message']='Caught Exception ' . $e . $info;
            $this->response['data']=$info;
            return $this->response;
            exit;
        }
            //
            // START SEARCH
            //
            
            $q = "  SELECT
                    playlists.name AS playlist_name,
                    playlists.id AS playlist_id,
                    playlists.user_id AS playlist_owner,
                    (SELECT count(id) FROM playlist_songs WHERE playlists.id = playlist_songs.playlist_id) AS song_count,
                    (SELECT count(id) FROM playlist_follows WHERE playlists.id = playlist_follows.playlist_id) AS follower_count
                    FROM playlists
                    WHERE playlists.id = :playlist_id";
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":playlist_id",$this->playlistId);
            $sth->execute();
            $playlistArr = $sth->fetchAll();
            $playlistInfo = $playlistArr[0];
            
            if($playlistInfo['playlist_name']!="Favorites") {
                $q = "SELECT songs.title AS song_title,songs.artist_name FROM songs WHERE songs.id = :id LIMIT 1";
                $sth=$this->DB->prepare($q);
                $sth->bindParam(":id",$this->songId);
                $sth->execute();
                $songArr = $sth->fetchAll();
                $song = $songArr[0];
                
                // Get Terms
                $t = preg_replace("/[^a-z0-9\s]/","",trim(strtolower($song['artist_name']))). " " . preg_replace("/[^a-z0-9\s]/","",trim(strtolower($song['song_title'])));
                $x = explode(" ",$t);
                
                require ROOT . '/_ini/stopwords.php';
                
                foreach($x as $w) {
                    if(!in_array($w,$stopwords) && ($w!='')) {
                        $uid = $this->playlistId;
                        $string = $playlistInfo['playlist_name']."|".$this->playlistId."|".$playlistInfo['song_count']."|".$playlistInfo['follower_count']."|".$playlistInfo['playlist_owner'];
                        
                        $redisSearch->set("playlistResult:".$uid,$string);
                        $redisSearch->sadd("playlistTerm:".$w,$uid);
                    }
                }
            }
            
            //
            // END SEARCH
            //
        
        // Success - return success message
        $this->response['message'] = 'Successfully added song to playlist';
        $this->response['status']  = 200;
        return $this->response;
        exit;
    }

    public function remove_song_from_playlist($httpObj) {
        $POST = $httpObj->getRequestVars();

        if(authorize::key($POST['user_id'],$POST['key'])) {

            $this->userId=$POST['user_id'];
            $this->playlistId=$POST['playlist_id'];

            $this->auth=true;
        }

        if($this->auth) {
            if(!isset($POST['song_id']) || !isset($POST['playlist_id'])) {
                $this->response['message'] = 'This function requires a song_id and playlist_id to be provided';
                $this->response['status']  = 404;
                $this->response['data'] = array();
                return $this->response;
                exit;
            } else {
                $this->songId = $POST['song_id'];
                $this->playlistId = $POST['playlist_id'];
            }

            $q="DELETE FROM playlist_songs WHERE song_id=:song_id AND playlist_id=:playlist_id";
            $sth=$this->DB->prepare($q);

            $sth->bindParam(":song_id",$this->songId);
            $sth->bindParam(":playlist_id",$this->playlistId);

            try {
                $sth->execute();
                $errors=false;
                $deleted = $sth->rowCount();
            } catch (PDOException $e) {
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e . $info;
                $this->response['data']=$info;
                return $this->response;
                exit;
            }

            if($deleted==0) {
                $this->response['status']=404;
                $this->response['message']='This item does not exist, unable to delete';
                $this->response['data'] = array();
                return $this->response;
                exit;
            }

            // Success - return success message
            $this->response['message'] = 'Successfully removed song from playlist';
            $this->response['status']  = 200;
            $this->response['data'] = array();
            return $this->response;
            exit;

        } else {
            $this->response['message'] = 'Authentication Failed';
            $this->response['status']  = 200;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }

    }

    public function remove_playlist_item($httpObj) {
        $POST = $httpObj->getRequestVars();

        if(authorize::key($POST['user_id'],$POST['key'])) {

            $this->userId=$POST['user_id'];
            $this->itemId=$POST['item_id'];

            $this->auth=true;
        }

        if($this->auth) {
            if(!isset($POST['item_id'])) {
                $this->response['message'] = 'This function requires a item_id to be provided';
                $this->response['status']  = 404;
                $this->response['data'] = array();
                return $this->response;
                exit;
            } else {
                $this->itemId = $POST['item_id'];
            }

            $q="DELETE FROM playlist_songs WHERE id=:item_id";
            $sth=$this->DB->prepare($q);

            $sth->bindParam(":item_id",$this->itemId);

            try {
                $sth->execute();
                $errors=false;
                $deleted = $sth->rowCount();
            } catch (PDOException $e) {
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e . $info;
                $this->response['data']=$info;
                return $this->response;
                exit;
            }

            if($deleted==0) {
                $this->response['status']=404;
                $this->response['message']='This item does not exist, unable to delete';
                $this->response['data'] = array();
                return $this->response;
                exit;
            }

            // Success - return success message
            $this->response['message'] = 'Successfully removed song from playlist';
            $this->response['status']  = 200;
            $this->response['data'] = array();
            return $this->response;
            exit;

        } else {
            $this->response['message'] = 'Authentication Failed';
            $this->response['status']  = 200;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }

    }
    
    public function load_playlist_songs($httpObj) {
        $POST = $httpObj->getRequestVars();
        global $redisSearch;
        
        
        if(isset($POST['user_id'])) {
            $this->userId = $POST['user_id'];
        } else {
            $this->userId = 0;
        }

        if(isset($POST['playlist_id'])) {
            $this->playlistId = $POST['playlist_id'];
        } else {
            $this->response['status']=404;
            $this->response['message']='Playlist not found';
            return $this->response;
            exit;
        }

        $q="SELECT
                playlist_songs.id AS item_id,
                playlist_songs.song_id,
                artists.avatar,
                playlists.user_id AS owner_id,
                playlists.name AS playlist_name,
                (SELECT COUNT(playlist_follows.id) FROM playlist_follows WHERE playlist_follows.user_id = :user_id AND playlists.id = playlist_follows.playlist_id) AS subscription,
                (SELECT COUNT(votes.id) FROM votes WHERE votes.song_id = songs.id) AS vote_count
            FROM playlist_songs
            INNER JOIN songs ON playlist_songs.song_id = songs.id
            INNER JOIN artists ON songs.artist_id = artists.id
            INNER JOIN playlists ON playlists.id = playlist_songs.playlist_id
            WHERE playlist_id = :playlist_id
            GROUP BY playlist_songs.song_id 
            ORDER BY playlist_songs.id ASC";

        $sth=$this->DB->prepare($q);
        $sth->bindParam(":playlist_id",$this->playlistId);
        $sth->bindParam(":user_id",$this->userId);

        try { $sth->execute(); } catch (PDOException $e) {
            $info = $sth->errorInfo();
            $this->response['status']=500;
            $this->response['message']='Caught Exception ' . $e . $info;
            return $this->response;
            exit;
        }

        $data=$sth->fetchAll();
        $n = count($data);

        if($n>0) {
            $playlist=array();

            for($i=0;$i<$n;$i++) {
                $playlist[$i]['owner_id'] = $data[$i]['owner_id'];
                
                // Objects from Cache Queries
                $song = cache::song_from_cache($data[$i]['song_id']);
                $avatar = cache::avatar_from_cache($song->artist_id);
                
                // Variables from Cache Queries
                $playlist[$i]['artist_vanity']=cache::artist_vanity($song->artist_id);
                $playlist[$i]['user_vote'] = cache::user_vote($this->userId,$data[$i]['song_id']);
                
                // Set return values from Cache Objects
                $playlist[$i]['song_title'] = trim($song->song_title);
                $playlist[$i]['song_uri'] = urlencode($song->uri);
                $playlist[$i]['vote_count'] = $song->vote_count;
                $playlist[$i]['artist_id'] = $song->artist_id;
                $playlist[$i]['artist_name'] = $song->artist_name;
                
                $playlist[$i]['ios_avatar'] = $avatar->ios;
                $playlist[$i]['crop_avatar'] = $avatar->crop;
                
                
                // Non Cachable Items
                $playlist[$i]['song_id'] = $data[$i]['song_id'];
                $playlist[$i]['item_id'] = $data[$i]['item_id'];
                $playlist[$i]['subscription'] = $data[$i]['subscription'];
                $playlist[$i]['playlist_name'] = $data[$i]['playlist_name'];
                $playlist[$i]['artist_avatar']=$data[$i]['avatar'];
            }

            if(isset($playlist[0]['owner_id']) && ($playlist[0]['owner_id'] == $this->userId)) {
                $this->response['owner']=1;
            } else {
                $this->response['owner']=0;
            }

        } else {
            $playlist=array();

            $q="
                SELECT playlists.name AS playlist_name, playlists.user_id AS owner_id,
                (SELECT COUNT(playlist_follows.id) FROM playlist_follows WHERE playlist_follows.user_id = :user_id AND playlists.id = playlist_follows.playlist_id) AS subscription
                FROM playlists WHERE playlists.id = :playlist_id";
            $sth=$this->DB->prepare($q);
            $sth->bindParam(":playlist_id",$this->playlistId);
            $sth->bindParam(":user_id",$this->userId);

            try {
                $sth->execute();
                $data=$sth->fetchAll();

                if(isset($data[0]['owner_id']) && ($data[0]['owner_id'] == $this->userId)) {
                    $this->response['owner']=1;
                } else {
                    $this->response['owner']=0;
                }

                $playlist['subscription'] = $data[0]['subscription'];
                $playlist['owner'] = $data[0]['owner_id'];
                $playlist['playlist_name'] = $data[0]['playlist_name'];

            } catch (PDOException $e) {
                $info = $sth->errorInfo();
                $this->response['status']=500;
                $this->response['message']='Caught Exception ' . $e . $info;
                return $this->response;
                exit;
            }
        }

        $this->response['status']=200;
        $this->response['message']='Returning the playlist';
        $this->response['data']=$playlist;
        return $this->response;
        exit;
    }

            
    public function load_playlists($httpObj) {
        $POST = $httpObj->getRequestVars();
        if(isset($POST['user_id']) && ($POST['user_id']!=''))     { $this->userId   = $POST['user_id'];   } else {
            $this->response['message'] = 'This function requires a user_id to be provided';
            $this->response['status']  = 200;
            $this->response['data'] = '';
            return $this->response;
            exit;
        }

        $q="SELECT id,name,user_id,weight,
                (SELECT COUNT(playlist_follows.id) FROM playlist_follows WHERE playlists.id = playlist_follows.playlist_id) AS subscribers,
                (SELECT COUNT(playlist_songs.id) FROM playlist_songs WHERE playlists.id = playlist_songs.playlist_id) AS song_count
                FROM playlists
                WHERE user_id=:user_id ORDER BY id";
        $sth=$this->DB->prepare($q);
        $sth->bindParam(":user_id",$this->userId);

        try { $sth->execute(); } catch (PDOException $e) {
            $info = $sth->errorInfo();
            $this->response['status']=500;
            $this->response['message']='Caught Exception ' . $e . $info;
            return $this->response;
            exit;
        }
        $data=$sth->fetchAll();
        $n=count($data);

        for($i=0;$i<$n;$i++) {
            $this->data[$i]['playlist_id']=$data[$i]['id'];
            $this->data[$i]['playlist_name']=$data[$i]['name'];
            $this->data[$i]['user_id']=$data[$i]['user_id'];
            $this->data[$i]['playlist_order']=$data[$i]['weight'];
            $this->data[$i]['subscribers']=$data[$i]['subscribers'];
            $this->data[$i]['song_count']=$data[$i]['song_count'];
        }

        $this->response['message'] = 'Returning all playlists for this user';
        $this->response['status']  = 200;
        $this->response['data'] = $this->data;
        return $this->response;
        exit;

    }
    
    public function uri($httpObj) {
        $POST = $httpObj->getRequestVars();
        global $redisSearch;
        
        if(isset($POST['song_id']) && ($POST['song_id']!=''))     { $this->songId   = $POST['song_id']; } else {
            $this->response['message'] = 'This function requires an id to be provided';
            $this->response['status']  = 200;
            $this->response['data'] = '';
            return $this->response;
            exit;
        }

        $q="SELECT songs.title,songs.uri,artists.name,artists.avatar,artists.id AS artist_id,users.vanity FROM songs LEFT JOIN artists ON songs.artist_id = artists.id LEFT JOIN users ON users.id = artists.user_id WHERE songs.id=:song_id LIMIT 1";

        $sth=$this->DB->prepare($q);
        $sth->bindParam(":song_id",$this->songId);

        try { $sth->execute(); } catch (PDOException $e) {
            $info = $sth->errorInfo();
            $this->response['status']=500;
            $this->response['message']='Caught Exception ' . $e . $info;
            return $this->response;
            exit;
        }

        $data=$sth->fetchAll();

        $this->response['uri'] = $data[0]['uri'];
        $this->response['title'] = $data[0]['title'];
        $this->response['artist'] = $data[0]['name'];
        $this->response['avatar'] = $data[0]['avatar'];
        
        // Getting artist avatars
        $avatar = json_decode(general::get_artist_avatar($data[0]['artist_id']));
        $this->response['ios_avatar'] = $avatar->ios;
        $this->response['crop_avatar'] = $avatar->crop;
        // Done artist avatars
        
        $this->response['vanity'] = $data[0]['vanity'];

        $this->response['message'] = 'Returning the song URI';
        $this->response['status']  = 200;
        return $this->response;
        exit;
    }

}