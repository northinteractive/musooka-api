<?php
header('Content-type: application/json');

// Set global for web root
define ('ROOT',$_SERVER['DOCUMENT_ROOT']."/");
define ('SALT',SALT);
define ('USERSALT',USERSALT);

// PDO
require ROOT . '_resources/classes/class.db.php';

// Load Redis Client
require ROOT . '_resources/predis/SharedConfigurations.php';
$redisSearch = new Predis_Client($search_server);


$hot = $redisSearch->zrevrange("muzooka:hot",19,29);

$i = 0;
$r = array();

foreach($hot as $s) {
    $song = json_decode($redisSearch->get("song:".$s));
    
    if($song) {
        
        if($redisSearch->get("uservote:25:".$s)) {
            $vote = 1;
        } else {
            $vote = 0;
        }
        
        $r[$i]['song_id'] = $s;
        $r[$i]['song_title'] = $song->song_title;
        $r[$i]['uri'] = $song->uri;
        $r[$i]['artist_name'] = $song->artist_name;
        $r[$i]['artist_id'] = $song->artist_id;
        $r[$i]['song_rank'] = $song->song_rank;
        $r[$i]['vote_count'] = $song->vote_count;
        $r[$i]['user_vote'] = $vote;
        $i++;
    }
}

echo json_encode($r);