<?php

/* ========================================
* 
*       Stopwords Library
* 
======================================== */
require ROOT . "_ini/stopwords.php";

/* ========================================
* 
*       Weight Sorting Tool
* 
======================================== */
function sort_by_weight($a,$b) {
    return $a['weight'] - $b['weight'];
}


/* ========================================
* 
*       Search Interface
* 
======================================== */

interface searchTemplate {
    public function all_songs($httpObj);
    public function all($httpObj);
    public function all_archive($httpObj); // Archived version of all
    public function playlists($httpObj);
    public function playlists_archive($httpObj); // Archived playlist Search
}

/* ========================================
* 
*       Main Search Class / Model
 *      
 *      To-Do:
 *      1) Requires re-implementation of Redis-based Search
* 
======================================== */

class Search implements searchTemplate {

    private $DB;
    private $response;
    private $httpObj;

    public function __construct() {

        // Default response
        $this->response = array();
        $this->response['message'] = '';
        $this->response['status'] = null;
        $this->response['data'] = array();
    }
    
    public function all_songs($httpObj) {
        global $redisSearch;
        global $stopwords;
        $db = new DB();

        $data = array();

        $POST = $httpObj->getRequestVars();
        if(isset($POST['term'])) {
            $this->term = $POST['term'];
        } else {
            $this->response['message'] = 'A search term is required';
            $this->response['status'] = 404;
            return $this->response;
            exit;
        }

        $searchResults = array();

        $clean = preg_replace("/[^a-z0-9\s]/", "", trim(strtolower($this->term)));
        $tmp = explode(" ",$clean);

        $i=0;

        foreach($tmp as $esn) {
            if(!in_array($esn, $stopwords) && ($esn!='')) {
                $result = $redisSearch->smembers("search:".$esn);

                foreach($result as $r) {
                    $string = $redisSearch->get("searchResult:".$r);
                    $arr = explode("|",$string);

                    if($arr[1]!=null) {
                        

                        $q = "SELECT 
                            songs.published,
                            songs.artist_id,
                            songs.artist_name,
                            songs.uri,
                            artists.avatar,
                            users.vanity
                            FROM songs
                            LEFT JOIN artists ON songs.artist_id = artists.id
                            LEFT JOIN users ON artists.user_id = users.id
                            WHERE songs.id=:song_id LIMIT 1";
                        $sth = $db->prepare($q);
                        $sth->bindParam(":song_id",$arr[1]);
                        $sth->execute();

                        $song = $sth->fetchAll();

                        if($song[0]['artist_name']!=null) {
                            $d[$i]['song_title'] = $arr[0];
                            $d[$i]['song_id']=$arr[1];
                            $d[$i]['song_published'] = $song[0]['published'];
                            $d[$i]['artist_id'] = $song[0]['artist_id'];
                            $d[$i]['artist_name'] = $song[0]['artist_name'];
                            $d[$i]['artist_vanity'] = $song[0]['vanity'];

                            // Getting artist avatars
                            $avatar = json_decode(general::get_artist_avatar($song[0]['artist_id']));
                            $d[$i]['ios_avatar'] = $avatar->ios;
                            $d[$i]['crop_avatar'] = $avatar->crop;
                            // Done artist avatars

                            $d[$i]['artist_avatar'] = $song[0]['avatar'];
                            $d[$i]['song_uri'] = $song[0]['uri'];

                            if($arr[0]!='') {
                                $weighted[$string] = $d[$i];
                            }

                            if(isset($weighted[$string]['weight'])) {
                                $weighted[$string]['weight']++;
                            } else {
                                $weighted[$string]['weight'] = 1;
                            }

                            $i++;
                        } else {
                            $redisSearch->del("searchResult:".$r);
                        }
                    }
                }

            }
        }

        if(isset($weighted) && count($weighted)>0) {

            usort($weighted, "sort_by_weight");

            $i=0;
            foreach($weighted AS $w) {
                if($w['song_id']!='') {
                    $data[$i] = $w;
                    $i++;
                }
            }

            $this->response['message'] = "Returning results for " . $this->term;
            $this->response['type'] = 'songs';
            $this->response['status'] = 200;
            $this->response['data'] = $data;
            return $this->response;
            exit;
        } else {
            $this->response['message'] = 'No Matches for: ' . $this->term;
            $this->response['status'] = 200;
            return $this->response;
            exit;
        }
    }

    public function all($httpObj) {
        global $redisSearch;
        global $stopwords;
        $db = new DB();

        $data = array();

        $POST = $httpObj->getRequestVars();
        if(isset($POST['term'])) {
            $this->term = $POST['term'];
        } else {
            $this->response['message'] = 'A search term is required';
            $this->response['status'] = 404;
            return $this->response;
            exit;
        }

        /*
        $searchResults = array();

        $clean = preg_replace("/[^a-z0-9\s]/", "", trim(strtolower($this->term)));
        $tmp = explode(" ",$clean);

        $i=0;
        foreach($tmp as $esn) {
            if(!in_array($esn, $stopwords) && ($esn!='')) {
                $result = $redisSearch->smembers("search:".$esn);

                foreach($result as $r) {
                    $string = $redisSearch->get("searchResult:".$r);
                    $arr = explode("|",$string);

                    $q = "SELECT 
                        songs.published,
                        songs.artist_id,
                        songs.artist_name,
                        songs.uri,
                        artists.avatar,
                        users.vanity
                        FROM songs
                        LEFT JOIN artists ON songs.artist_id = artists.id
                        LEFT JOIN users ON artists.user_id = users.id
                        WHERE songs.id=:song_id LIMIT 1";
                    $sth = $db->prepare($q);
                    $sth->bindParam(":song_id",$arr[1]);
                    $sth->execute();
                    
                    $song = $sth->fetchAll();
                    
                    if($song[0]['artist_name']!=null) {
                        $d[$i]['song_published'] = $song[0]['published'];
                        $d[$i]['artist_id'] = $song[0]['artist_id'];
                        $d[$i]['artist_name'] = $song[0]['artist_name'];
                        $d[$i]['artist_vanity'] = $song[0]['vanity'];

                        // Getting artist avatars
                        $avatar = json_decode(general::get_artist_avatar($song[0]['artist_id']));
                        $d[$i]['ios_avatar'] = $avatar->ios;
                        $d[$i]['crop_avatar'] = $avatar->crop;
                        // Done artist avatars

                        $d[$i]['artist_avatar'] = $song[0]['avatar'];
                        $d[$i]['song_uri'] = $song[0]['uri'];
                        
                        // Iterate
                        $i++;
                    } else {
                        $redisSearch->del("searchResult:".$r);
                    }
                }

            }
        }
        */
        
        $like = "%".$POST['term']."%";
        
        $q = "SELECT
                        artists.id AS artist_id,
                        artists.name AS artist_name,
                        artists.avatar,
                        users.vanity
                        FROM artists
                        LEFT JOIN users ON artists.user_id = users.id
                        WHERE artists.name LIKE :like";
        $sth = $db->prepare($q);
        $sth->bindParam(":like",$like);
        $sth->execute();
       
        $i = 0;
        foreach($sth->fetchAll() as $v) {
            $d[$i]['artist_id'] = $v['artist_id'];
            $d[$i]['artist_name'] = $v['artist_name'];
            $d[$i]['artist_vanity'] = $v['vanity'];

            // Getting artist avatars
            $avatar = json_decode(general::get_artist_avatar($v['artist_id']));
            $d[$i]['ios_avatar'] = $avatar->ios;
            $d[$i]['crop_avatar'] = $avatar->crop;
            // Done artist avatars

            $d[$i]['artist_avatar'] = $v['avatar'];
            $i++;
        }
        
        if(isset($d) && count($d)>0) {

            $this->response['message'] = "Returning results for " . $this->term;
            $this->response['type'] = 'songs';
            $this->response['status'] = 200;
            $this->response['data'] = $d;
            return $this->response;
            exit;
        } else {
            $this->response['message'] = 'No Matches for: ' . $this->term;
            $this->response['status'] = 200;
            return $this->response;
            exit;
        }
    }

    public function all_archive($httpObj) {
        $POST = $httpObj->getRequestVars();

        if(isset($POST['term'])) {
            $this->term = $POST['term'];
        } else {
            $this->response['message'] = 'A search term is required';
            $this->response['status'] = 404;
            return $this->response;
            exit;
        }

        if($this->term) {
            $this->cl->SetMatchMode($this->mode);
            $this->cl->SetConnectTimeout ( 1 );
            // $this->cl->SetArrayResult ( true );
            $this->cl->SetWeights ( array ( 100, 1 ) );

            $result = $this->cl->Query($this->term,'song-index');

            if ( $result === false ) {

                $this->response['message'] = 'Query failed' . $this->cl->GetLastError();
                $this->response['status'] = 200;
                $this->response['data'] = array();
                return $this->response;
                exit;

            } else {
                if(isset($result['matches']) && (count($result['matches'])>0)) {

                    $this->response['message'] = 'Returning search results for: ' . $this->term;
                    // Append cl error
                    if ( $this->cl->GetLastWarning() ) {
                       $this->response['message'] .= "WARNING: " . $this->cl->GetLastWarning() . " ";
                    }

                    $i=0;
                    foreach($result['matches'] as $k => $rm) {
                        $data[$i]['song_id']=$k;
                        $data[$i]['song_published'] = $rm['attrs']['song_published'];
                        $data[$i]['artist_id'] = $rm['attrs']['artist_id'];
                        $data[$i]['artist_name'] = $rm['attrs']['artist_name'];
                        $data[$i]['artist_avatar'] = $rm['attrs']['avatar'];
                        $data[$i]['song_rank'] = $rm['attrs']['song_rank'];
                        $data[$i]['vote_count'] = $rm['attrs']['vote_count'];
                        $data[$i]['weight'] = $rm['weight'];
                        $data[$i]['song_uri'] = $rm['attrs']['song_uri'];
                        $data[$i]['song_title'] = $rm['attrs']['song_title'];
                        $i++;
                    }

                    $this->response['type'] = 'songs';
                    $this->response['status'] = 200;
                    $this->response['data'] = $data;
                    return $this->response;
                    exit;
                } else {
                    $this->response['message'] = 'No Matches for: ' . $this->term;
                    // Append cl error
                    if ( $this->cl->GetLastWarning() ) {
                       $this->response['message'] .= "WARNING: " . $this->cl->GetLastWarning() . " ";
                    }

                    $this->response['status'] = 200;
                    return $this->response;
                    exit;
                }
            }
        }
    }
    
    public function playlists($httpObj) {
        global $redisSearch;
        global $stopwords;

        $data = array();

        $POST = $httpObj->getRequestVars();
        if(isset($POST['term'])) {
            $this->term = $POST['term'];
        } else {
            $this->response['message'] = 'A search term is required';
            $this->response['status'] = 404;
            return $this->response;
            exit;
        }

        $searchResults = array();

        $clean = preg_replace("/[^a-z0-9\s]/", "", trim(strtolower($this->term)));
        $tmp = explode(" ",$clean);

        $i=0;

        foreach($tmp as $esn) {
            if(!in_array($esn, $stopwords) && ($esn!='')) {
                $result = $redisSearch->smembers("playlistTerm:".$esn);

                foreach($result as $r) {
                    $string = $redisSearch->get("playlistResult:".$r);
                    $arr = explode("|",$string);

                    $d[$i]['playlist_name'] = $arr[0];
                    $d[$i]['playlist_id']=$arr[1];
                    $d[$i]['song_count']=$arr[2];
                    $d[$i]['follower_count']=$arr[3];
                    $d[$i]['playlist_owner']=$arr[4];
                    
                    $weighted[$string] = $d[$i];

                     if(isset($weighted[$string]['weight'])) {
                        $weighted[$string]['weight']++;
                    } else {
                        $weighted[$string]['weight'] = 1;
                    }

                    $i++;
                }
            }
        }

        if(isset($weighted) && count($weighted)>0) {

            usort($weighted, "sort_by_weight");

            $i=0;
            foreach($weighted AS $w) {
                $data[$i] = $w;
                $i++;
            }

            $this->response['message'] = "Returning results for " . $this->term;
            $this->response['type'] = 'playlists';
            $this->response['status'] = 200;
            $this->response['data'] = $data;
            return $this->response;
            exit;
            
        } else {
            
            $this->response['message'] = 'No Matches for: ' . $this->term;
            $this->response['status'] = 200;
            return $this->response;
            exit;
        }
    }

    public function playlists_archive($httpObj) {
        $POST = $httpObj->getRequestVars();

        if(isset($POST['user_id'])) {
            $this->userId = $POST['user_id'];
        } else {
            $this->userId = 0;
        }

        if(isset($POST['term'])) {
            $this->term = $POST['term'];
        } else {
            $this->response['message'] = 'A search term is required';
            $this->response['status'] = 404;
            return $this->response;
            exit;
        }

        if($this->term) {
            $this->cl->SetMatchMode($this->mode);
            $this->cl->SetConnectTimeout ( 1 );
            // $this->cl->SetArrayResult ( true );
            $this->cl->SetWeights ( array ( 100, 1 ) );

            $result = $this->cl->Query($this->term,'playlist-index');

            if ( $result === false ) {

                $this->response['message'] = 'Query failed' . $this->cl->GetLastError();
                $this->response['status'] = 200;
                $this->response['data'] = array();
                return $this->response;
                exit;

            } else {
                if(isset($result['matches']) && (count($result['matches'])>0)) {

                    $this->response['message'] = 'Returning search results for: ' . $this->term;

                    // Append cl error
                    if ( $this->cl->GetLastWarning() ) {
                       $this->response['message'] .= "WARNING: " . $this->cl->GetLastWarning() . " ";
                    }

                    $i=0;

                    $id_array = array();

                    foreach($result['matches'] as $m) {
                        array_push($id_array,$m['attrs']['playlist_id']);
                    }

                    $id_string = implode(",",$id_array);

                    $q = "
                        SELECT
                            (SELECT COUNT(playlist_songs.id) FROM playlist_songs WHERE playlist_songs.playlist_id=playlists.id) AS song_count,
                            (SELECT COUNT(playlist_follows.id) FROM playlist_follows WHERE playlist_follows.playlist_id=playlists.id) AS follower_count,
                            (SELECT COUNT(playlist_follows.id) FROM playlist_follows WHERE playlist_follows.user_id = :user_id AND playlist_follows.playlist_id = playlists.id) AS subscription,
                            playlists.id
                        FROM playlists WHERE playlists.id IN (".$id_string.")";
                    $sth = $this->DB->prepare($q);
                    $sth->bindParam(":user_id",$this->userId);
                    $sth->execute();
                    $playlist_info = $sth->fetchAll();

                    foreach($result['matches'] as $k => $rm) {
                        if($rm['attrs']['playlist_owner']!=$this->userId) {
                            $data[$i]['song_id']=$k;
                            $data[$i]['artist_name'] = $rm['attrs']['artist_name'];

                            $data[$i]['playlist_id'] = $rm['attrs']['playlist_id'];
                            $data[$i]['playlist_name'] = $rm['attrs']['playlist_name'];
                            $data[$i]['playlist_owner'] = $rm['attrs']['playlist_owner'];

                            // Loop through playlist info, add in information for playlist
                            foreach($playlist_info AS $pi) {
                                if($pi['id']==$rm['attrs']['playlist_id']) {
                                    $data[$i]['follower_count'] = $pi['follower_count'];
                                    $data[$i]['song_count'] = $pi['song_count'];
                                    $data[$i]['song_count'] = $pi['song_count'];
                                    $data[$i]['subscription'] = $pi['subscription'];
                                }
                            }
                        }
                        $i++;
                    }

                    $data = array_values($data);

                    $this->response['status'] = 200;
                    $this->response['data'] = $data;
                    $this->response['type'] = 'playlists';
                    return $this->response;
                    exit;

                } else {
                    $this->response['message'] = 'No Matches for: ' . $this->term;

                    // Append cl error
                    if ( $this->cl->GetLastWarning() ) {
                       $this->response['message'] .= "WARNING: " . $this->cl->GetLastWarning() . " ";
                    }

                    $this->response['status'] = 200;
                    return $this->response;
                    exit;
                }
            }
        }
    }
}