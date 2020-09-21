<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';
require ROOT . 'application/utilities/time.utility.php';

class UserdatasubscriptionsController {
    public $updates;
    public $httpObj;
    public $response;
    
    public function __construct() {
       
        $this->httpObj = RestUtil::processRequest();
        $POST = $this->httpObj->getRequestVars();
        $DB = new DB();
        
        $this->response = array();
        
        if(isset($POST['user_data_id'])) {
            $id = $POST['user_data_id'];
            
            $q = "SELECT playlists.name AS playlist_name, playlists.id AS playlist_id,
                    (SELECT count(playlist_follows.id) FROM playlist_follows WHERE playlist_follows.owner_id = playlists.user_id) AS subscribers,
                    (SELECT count(playlist_songs.id) FROM playlist_songs WHERE playlist_songs.playlist_id = playlists.id) AS song_count
                    FROM playlist_follows
                    LEFT JOIN playlists ON playlist_follows.playlist_id = playlists.id
                    WHERE playlist_follows.user_id = :id ORDER BY playlist_follows.id DESC";
                    
                    $sth = $DB->prepare($q);
                    $sth->bindParam(":id",$id);
                    if($sth->execute()) {
                        $this->response['message'] = "returning user subscriptions";
                        $this->response['status'] = 200;
                        $fol = $sth->fetchAll();
                        
                        $i = 0;
                        foreach($fol as $f) {
                            $data[$i]['playlist_id'] = $f['playlist_id'];
                            $data[$i]['playlist_name'] = $f['playlist_name'];
                            $data[$i]['subscribers'] = $f['subscribers'];
                            $data[$i]['song_count'] = $f['song_count'];
                            $i++;
                        }
                        $data['total'] = $i;
                        $this->response['data'] = $data;
                    } else {
                        $this->response['message'] = "Unable to load subscriptions";
                        $this->response['status'] = 500;
                        $this->response['data'] = $sth->errorInfo();
                    }
                    
                    
        } else {
            $this->response['message'] = "No id";
            $this->response['status'] = 404;
        }
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}