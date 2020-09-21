<?php

/* ========================================
* 
*     Cache Types
 * 
 *      @avatar_from_cache
 *      @artist_vanity
 *      @cache_vote(
 *      @user_vote(user_id,song_id)
 *      @song_from_cache(song_id)
 *      @cache_song(song_id)
* 
======================================== */

interface cacheTemplate {
    // Artist Caching
    public function avatar_from_cache($artist_id);
    public function artist_vanity($artist_id);
    public function user_id_from_artist_id($artist_id);
    
    // User Caching
    public function cache_vote($user_id,$song_id);
    public function user_vote($user_id,$song_id);
    
    // Song Caching
    public function song_from_cache($song_id);
    public function cache_song($song_id);
    
    // List Caching
    public function hot($start,$userId);
    public function cache_hot($song_id,$rank);
    
    public function cache_top($song_id,$rank);
    
    public function cache_new();
}


class cache implements cacheTemplate {
    
    /* ========================================
     * 
     *     Artist Caching
     * 
    ======================================== */
    
    public function avatar_from_cache($artist_id = false) {
        if($artist_id) {
            return json_decode(general::get_artist_avatar($artist_id));
        } else {
            return false;
        }
    }
    
    public function user_id_from_artist_id($artist_id = false) {
        global $redisSearch;
        $db = new DB();
        
        if($artist_id) {
            $userId = $redisSearch->get("userid:".$artist_id);
            if($userId) {
                return $userId;
            } else {
                $q = "SELECT artists.user_id FROM artists WHERE artists.id = :artist_id LIMIT 1";
                $sth = $db->prepare($q);
                $sth->bindParam("artist_id",$artist_id);
                $sth->execute();
                $tmp = $sth->fetchAll();
                $userId = $tmp[0]['user_id'];
                
                // Populate Cache and return value
                $redisSearch->set("userid:".$artist_id,$userId);
                return $userId;
            }
        }
    }
    
    public function artist_vanity($artist_id = false) {
        global $redisSearch;
        $db = new DB();
        
        if($artist_id) {
            
            $vanity = $redisSearch->get("artistvanity:".$artist_id);
            
            if($vanity) {
                return $vanity;
            } else {
                $q = "SELECT users.vanity AS artist_vanity FROM users LEFT JOIN artists ON artists.user_id=users.id WHERE artists.id = :artist_id LIMIT 1";
                $sth = $db->prepare($q);
                $sth->bindParam("artist_id",$artist_id);
                $sth->execute();
                $tmp = $sth->fetchAll();
                $vanity = $tmp[0]['artist_vanity'];
                 
                $redisSearch->set("artistvanity:".$artist_id,$vanity);
                
                if($vanity) {
                    return $vanity;
                } else {
                    return false;
                }
            }
            
        } else {
            return false;
        }
    }
    
    /* ========================================
     * 
     *     User Caching
     * 
    ======================================== */
    
    /* ========================================
     * 
     *     Cache a users' vote
     * 
    ======================================== */
    
    public function cache_vote($user_id=false,$song_id=false) {
        global $redisSearch;
        $db = new DB();
        
        if(!$user_id || !$song_id) {
            return false;
        } else {
            $q = "SELECT COUNT(votes.id) AS vote_count FROM votes WHERE votes.song_id=:song_id AND votes.user_id=:user_id LIMIT 1";
            $sth = $db->prepare($q);
            $sth->bindParam(":song_id",$song_id);
            $sth->bindParam(":user_id",$user_id);
            $sth->execute();
            $tmp = $sth->fetchAll();

            $vote = $tmp[0]['vote_count'];

            if($redisSearch->set("uservote:".$user_id.":".$song_id,$vote)) {
                return true;
            } else {
                return false;
            }
        }
    }
    
    /* ========================================
     * 
     *     Check for user vote for a song_id
     * 
    ======================================== */
    
    public function user_vote($user_id=false,$song_id=false) {
        global $redisSearch;
        $db = new DB();
        
        $vote = $redisSearch->get("uservote:".$user_id.":".$song_id);
        
        if(!$vote) {
            $q = "SELECT COUNT(votes.id) AS vote_count FROM votes WHERE votes.song_id=:song_id AND votes.user_id=:user_id LIMIT 1";
            $sth = $db->prepare($q);
            $sth->bindParam(":song_id",$song_id);
            $sth->bindParam(":user_id",$user_id);
            $sth->execute();
            $tmp = $sth->fetchAll();
            
            $vote = $tmp[0]['vote_count'];
            
            $redisSearch->set("uservote:".$user_id.":".$song_id,$vote);
            
            return $vote;
        } else {
            return $vote;
        }
        
    }
    
    /* ========================================
     * 
     *     Song Caching - Single layer redis-based song caching
     * 
    ======================================== */
    
    public function song_from_cache($song_id = false) {
        global $redisSearch;
        
        if($song_id) {
            $song = $redisSearch->get("song:".$song_id);
            
            if($song) {
                return json_decode($song);
            } else {
                cache::cache_song($song_id);
                return json_decode($redisSearch->get("song:".$song_id));
            }
        }
    }
    
    /*=================================================================
    * 
    *         Cache song based on ID
    * 
    =================================================================*/
    
    public function cache_song($song_id = false) {
        global $redisSearch;
        $db = new DB();
        
        if($song_id) {
            $q = "SELECT (SELECT COUNT(id) FROM votes WHERE votes.song_id=songs.id) AS vote_count, songs.*, artists.name AS artist_name_full FROM songs LEFT JOIN artists ON artists.id = songs.artist_id WHERE songs.id = :song_id";
            
            $sth = $db->prepare($q);
            $sth->bindParam(":song_id",$song_id);
            if($sth->execute()) {
                
                $song = $sth->fetchAll();
                $v = $song[0];
                $rank = round(log(max(abs($v['vote_count']), 1), 10) + 1 * $v['upload_stamp'] / 45000, 7);
                
                $song = array();
    
                $song['song_title']=  $v['title'];
                $song['artist_name'] = $v['artist_name_full'];
                $song['artist_id'] = $v['artist_id'];
                $song['uri'] = $v['uri'];
                $song['genre'] = $v['genre'];
                $song['upload_stamp'] = $v['upload_stamp'];
                $song['vote_count'] = $v['vote_count'];
                $song['song_rank'] = $rank;
                $song['price'] = $v['price'];
                
                if($redisSearch->set("song:".$v['id'],json_encode($song))) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
            
        } else {
            return false;
        }
    }
    
    /* ========================================
     * 
     *     List Caching
     * 
    ======================================== */
    
    public function cache_hot($song_id=false,$rank=false) {
        global $redisSearch;
        if($song_id && $rank) {
            $song = cache::song_from_cache($song_id);
            if($song && $song->vote_count>4) {
                $redisSearch->zadd("muzooka:hot",round($rank),$song_id);
            } else {
                $redisSearch->zrem("muzooka:hot",$song_id);
            }
        } else {
            return false;
        }
    }
    
    public function hot($start = 0,$userId=false) {
        global $redisSearch;
        $db = new DB();
        $end = $start+19;
        $songs = $redisSearch->zrevrange("muzooka:hot",$start,$end);

        // Start Array
        $r = array();
        $i = 0;
        foreach($songs as $s) {
            $song = cache::song_from_cache($s);
            // Check if song exists
            if($song) {

                // Get User Vote from Cache
                if(cache::user_vote($userId,$s)) {
                    $vote = 1;
                } else {
                    $vote = null;
                }

                // Populate hot list
                $r[$i]['song_id'] = $s;
                $r[$i]['song_title'] = $song->song_title;
                $r[$i]['song_uri'] = $song->uri;
                $r[$i]['artist_name'] = $song->artist_name;
                $r[$i]['song_published'] = $song->upload_stamp;
                $r[$i]['artist_id'] = $song->artist_id;
                $r[$i]['song_rank'] = $song->song_rank;
                $r[$i]['vote_count'] = $song->vote_count;

                // Set Uservote from cache
                $r[$i]['user_vote'] = $vote;


                // Getting artist avatars
                $avatar = cache::avatar_from_cache($song->artist_id);
                $r[$i]['ios_avatar'] = $avatar->ios;
                $r[$i]['crop_avatar'] = $avatar->crop;
                // Done artist avatars

                $r[$i]['artist_avatar'] = $avatar->ios;
                $r[$i]['artist_vanity'] = cache::artist_vanity($song->artist_id);

                /* ========================================
                * 
                *    Temporary fix
                * 
                ======================================== */
                
                $q="SELECT
                        (
                            (
                                SELECT COUNT(votes.id) FROM votes
                                LEFT JOIN users ON users.id = votes.user_id
                                LEFT JOIN artists ON artists.user_id = users.id
                                WHERE artists.producer = 1 AND votes.song_id = songs.id
                            )
                        ) AS producer_activity
                    FROM songs
                    WHERE songs.id = :song_id";
                $sth = $db->prepare($q);
                $sth->bindParam(":song_id",$s);
                $sth->execute();
                $tmp = $sth->fetchAll();
                
                /* ========================================
                * 
                *     End Temporary fix
                * 
                ======================================== */
                
                $r[$i]['producer_activity'] = $tmp[0]['producer_activity'];
                $r[$i]['album_id'] = 0;

                $i++;
            }
        }

        /* ========================================
        * 
        *     Extended Caching needed in the future
        * 
        ======================================== */
        
        // $redisSearch->set("muzooka:cachehot:".$start,json_encode($r));
        // $redisSearch->expire("muzooka:cachehot:".$start,5);

        return json_encode($r);
        
    }
    
    public function cache_top($song_id=false,$rank=false) {
        global $redisSearch;
        if($song_id && $rank) {
            $redisSearch->zadd("muzooka:top",$rank,$song_id);
        } else {
            return false;
        }
    }
    
    /* ========================================
    * 
    *     Future Element
    * 
    ======================================== */
    
    public function cache_new() {
        
    }
    
}


?>
