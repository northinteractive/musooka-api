<?php

class Vote extends Ranking {
    private $DB;
    private $response;
    private $httpObj;

    private $key;
    private $userId;
    private $songId;
    private $auth;
    private $artistId;
    private $timestamp;
    private $userVote;


    public function __construct() {
        $this->auth = false;
        $this->DB = new DB();

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

    public function findrank($httpObj) {
        $this->httpObj = $httpObj;
        $POST = $this->httpObj->getRequestVars();

        return $this->hotness(25000,0,1324402826);
    }

    public function get_user_votes($httpObj) {
            $this->httpObj = $httpObj;
            $POST = $this->httpObj->getRequestVars();

            $q = "SELECT
                    votes.song_id,
                    votes.user_id,
                    votes.timestamp,
                    songs.title,
                    songs.artist_name,
                    songs.artist_id
                    FROM votes
                    LEFT JOIN songs ON songs.id = votes.song_id
                    WHERE votes.user_id = :user_id
                    ORDER BY votes.timestamp DESC";
            $sth = $this->DB->prepare($q);
            $sth->bindParam(":user_id",$POST['user_id']);
            $sth->execute();
            $tmp = $sth->fetchAll();

            $i = 0;
            foreach($tmp as $s) {
                    $data[$i]['song_id'] = $s['song_id'];
                    $data[$i]['timestamp'] = $this->time_approximator($s['timestamp']);
                    $data[$i]['artist_name'] = $s['artist_name'];
                    $data[$i]['artist_id'] = $s['artist_id'];
                    $data[$i]['song_title']= $s['title'];
                    $i++;
            }

            $this->response['message'] = 'Returning all Votes for this user';
            $this->response['status']  = 200;
            $this->response['data'] = $data;
            return $this->response;
            exit;
    }

    private function get_vote_count($songId) {

        // Hit the database
        $q = "SELECT COUNT(id) AS vote_count FROM votes WHERE song_id = :song_id";
        $sth = $this->DB->prepare($q);
        $sth->bindParam(":song_id",$songId);
        $sth->execute();
        $tmp = $sth->fetchAll();
        $v['count'] = $tmp[0]['vote_count'];
        
        return $v['count'];
    }

    private function get_vote_cast_for_user($songId,$userId) {
        // Prepare Query
        $q = "SELECT count(votes.id) AS vote_count FROM votes WHERE votes.user_id = :user_id AND votes.song_id = :song_id";
        $sth = $this->DB->prepare($q);
        $sth->bindParam(":user_id",$userId);
        $sth->bindParam(":song_id",$songId);

        // execute and return values
        $sth->execute();
        $tmp = $sth->fetch(PDO::FETCH_LAZY);
        $voteCount = $tmp['vote_count'];
        $sth->closeCursor();

        return $voteCount;
        //}
    }

    private function get_artist_id_from_song($songId) {

        // Prepare the query
        $q = "SELECT songs.upload_stamp,songs.artist_id FROM songs WHERE songs.id=:song_id";
        $sth = $this->DB->prepare($q);
        $sth->bindParam(":song_id",$songId);

        // Execute
        $sth->execute();
        $tmp = $sth->fetchAll();

        $v['upload_stamp'] = $tmp[0]['upload_stamp'];
        $v['artist_id'] = $tmp[0]['artist_id'];
        
        // return the value
        return $v;
    }

    private function rank_algorithm($voteCount,$timestamp) {
        //$rank = round(($timestamp-1134028003)/45000)+round(log10($voteCount)*$voteCount);
        $rank = round(log(max(abs($voteCount), 1), 10) + 1 * $timestamp / 45000, 7);
        return $rank;
    }

    private function return_song_rank($songId,$timestamp) {
        $voteCount = $this->get_vote_count($songId);
        //$rank = $this->hotness($voteCount,0,$timestamp);
        $rank = $this->rank_algorithm($voteCount,$timestamp);
        
        return $rank;
    }

    public function change($httpObj) {
        global $mc;
        $this->httpObj = $httpObj;
        $POST = $this->httpObj->getRequestVars();

        if(!isset($POST['song_id'])) {
            $this->response['message'] = 'You must provide a song_id';
            $this->response['status']  = 200;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }

        if(!isset($POST['user_id']) | !isset($POST['key'])) {
            $this->response['message'] = 'You must provide a user_id and key';
            $this->response['status']  = 200;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }

        if(authorize::key($POST['user_id'],$POST['key'])) {

            $this->userId=$POST['user_id'];
            $this->songId=$POST['song_id'];

            $this->auth=true;
        }

        if($this->auth) {

            // Get artist Info
            $v = $this->get_artist_id_from_song($this->songId);

            $this->artistId = $v['artist_id'];
            $this->timestamp = $v['upload_stamp'];

            // Get User Vote
            $this->userVote = $this->get_vote_cast_for_user($this->songId,$this->userId);

            if($this->userVote>0) {

                $q = "DELETE FROM votes WHERE song_id=:song_id AND user_id=:user_id";
                $sth=$this->DB->prepare($q);
                $sth->bindParam(":user_id",$this->userId);
                $sth->bindParam(":song_id",$this->songId);

                try {
                    // Execute and set memcache key for user vote
                    $sth->execute();
                } catch (PDOException $e) {
                    $this->response['status']=500;
                    $this->response['message']='Caught Exception ' . $e;
                    return $this->response;
                    exit;
                }

                // Rank caching
                // THANKS REDDIT round(log(max(abs(s), 1), 10) + sign * seconds / 45000, 7)

                $songRank = $this->return_song_rank($this->songId,$this->timestamp);
                $voteCount =  $this->get_vote_count($this->songId);

                $q = "UPDATE songs SET songs.vote_count = :vote_count, songs.song_rank = :song_rank WHERE songs.id = :song_id";

                $sth=$this->DB->prepare($q);
                $sth->bindParam(":vote_count",$voteCount);
                $sth->bindParam(":song_rank",$songRank);
                $sth->bindParam(":song_id",$this->songId);

                try {
                    $sth->execute();
                    
                    // Cache layer
                    cache::cache_song($this->songId);
                    cache::cache_vote($this->userId,$this->songId);
                    cache::cache_hot($this->songId,$songRank);
                    cache::cache_top($this->songId,$voteCount);
                    
                    $this->response['message'] = 'Successfully Unvoted';
                    $this->response['status']  = 200;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;

                } catch (PDOException $e) {
                    $this->response['status']=500;
                    $this->response['message']='Caught Exception in UPDATE query' . $e;
                    return $this->response;
                    exit;
                }

            } else {

                // Vote not cast, try inserting new vote

                $q="INSERT INTO votes (id,user_id,song_id,timestamp) values (0,:user_id,:song_id,:timestamp)";
                $sth=$this->DB->prepare($q);
                $sth->bindParam(":user_id",$this->userId);
                $sth->bindParam(":song_id",$this->songId);
                $sth->bindParam(":timestamp",time());

                try {

                    // Execute and set memcache key for user vote
                    $sth->execute();

                } catch (PDOException $e) {
                    $this->response['status']=500;
                    $this->response['message']='Caught Exception ' . $e;
                    return $this->response;
                    exit;
                }

                // Rank caching
                // THANKS REDDIT round(log(max(abs(s), 1), 10) + sign * seconds / 45000, 7)

                $songRank = $this->return_song_rank($this->songId,$this->timestamp);
                $voteCount =  $this->get_vote_count($this->songId);

                $q = "UPDATE songs SET songs.vote_count = :vote_count, songs.song_rank = :song_rank WHERE songs.id = :song_id";

                $sth=$this->DB->prepare($q);
                $sth->bindParam(":vote_count",$voteCount);
                $sth->bindParam(":song_rank",$songRank);
                $sth->bindParam(":song_id",$this->songId);

                    try {
                        $sth->execute();
                        
                        // Cache layer
                        cache::cache_song($this->songId);
                        cache::cache_vote($this->userId,$this->songId);
                        cache::cache_hot($this->songId,$songRank);
                        cache::cache_top($this->songId,$voteCount);
                        
                        $q = "SELECTsong_id FROM playlist_songs WHERE song_id=:song_id";
                        $sth=$this->DB->prepare($q);
                        $sth->bindParam(":song_id",$this->songId);
                        $sth->execute();
                        
                        $temp = $sth->fetchAll();
                        if(isset($temp[0]['song_id']) && ($temp[0]['song_id']==$this->songId)) {
                            
                            $q = "INSERT INTO playlist_songs (song_id,playlist_id,weight) values (:song_id,(SELECT id FROM playlists WHERE name='favorites' AND user_id=:user_id LIMIT 1),0)";
                            $sth=$this->DB->prepare($q);
                            $sth->bindParam(":user_id",$this->userId);
                            $sth->bindParam(":song_id",$this->songId);

                            try {
                                $sth->execute();
                                $this->response['message'] = 'Successfully voted';
                                
                                $this->response['status']  = 200;
                                $this->response['data'] = array();
                                return $this->response;
                                exit;

                            } catch (PDOException $e) {
                                $this->response['status']=500;
                                $this->response['message']='Caught Exception in UPDATE query' . $e;
                                return $this->response;
                                exit;
                            }
                            
                        } else {
                            
                            $this->response['message'] = 'Successfully voted';
                                $this->response['status']  = 200;
                                $this->response['data'] = array();
                                return $this->response;
                                exit;
                        }
                        
                        

                    } catch (PDOException $e) {
                        $this->response['status']=500;
                        $this->response['message']='Caught Exception in UPDATE query' . $e;
                        return $this->response;
                        exit;
                    }

                /*
                Last but not least, favorite any songs that are voted on
                */
            }

        } else {

            $this->response['message'] = 'You are not authorized to make this request. Please submit the correct user_id and key pair to make this request';
            $this->response['status']  = 401;
            return $this->response;
            exit;

        }
    }
}