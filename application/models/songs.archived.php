<?php

// OLD HOT FUNCTION

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

        $genres = json_decode(stripslashes($g[0]['genre']));
        $genreList = array();

        foreach($genres AS $k => $v) {
            if($v == 1) {
                array_push($genreList,$k);
            }

        }

        $filter = implode(",",$genreList);

        $q .= " WHERE songs.genre IN (".$filter.") AND vote_count>2 ";
    }

    $q .= " ORDER BY songs.song_rank DESC LIMIT ".$this->loadStart.",20";

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
            $updateinfo[$i]['user_vote']=$data[$i]['user_vote'];

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
            if(isset($data[$i]['producer_activity']) && ($data[$i]['producer_activity']==1)) {
                $updateinfo[$i]['producer_activity']=1;
            } else {
                $updateinfo[$i]['producer_activity']=0;
            }

        }

        $this->response['message'] = 'hot';
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
    WHERE vote_count>2 
    ORDER BY songs.song_rank DESC LIMIT ".$this->loadStart.",20";

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

        $this->response['message'] = 'hot';
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