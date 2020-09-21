<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

class TopplaylistsController {
    public $update;
    public $httpObj;
    public $response;
    
    public function __construct() {
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        $POST = $this->httpObj->getRequestVars();
        $DB = new DB();
        
        $q = "SELECT playlists.name, playlists.id,
                (SELECT COUNT(playlist_follows.id) FROM playlist_follows WHERE playlists.user_id=playlist_follows.owner_id) AS cnt,
                users.vanity
                FROM playlists
                LEFT JOIN users ON playlists.user_id=users.id
                WHERE users.vanity!='shawn'
                AND (SELECT COUNT(playlist_songs.id) FROM playlist_songs WHERE playlists.id=playlist_songs.playlist_id)>0
                ORDER BY cnt DESC LIMIT 10
            ";
            
        $sth = $DB->prepare($q);
        $sth->execute();
        $playlists = $sth->fetchAll();
        $data = array();
        
        $i = 0;
        foreach($playlists as $p) {
            $data[$i]['playlist_name'] = $p['name'];
            $data[$i]['playlist_owner'] = $p['vanity'];
            $data[$i]['follows'] = $p['cnt'];
            $data[$i]['playlist_id'] = $p['id'];
            $i++;
        }
        
        $this->response['message'] = 'Returning Top Playlists';
        $this->response['status']  = 200;
        $this->response['data'] = $data;
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}