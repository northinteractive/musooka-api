<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';
require ROOT . 'application/utilities/time.utility.php';

class UserdatafollowsController {
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
            
            $q = "SELECT follows.artist_id,follows.timestamp,artists.name AS artist_name,artists.avatar AS artist_avatar
                    FROM follows
                    LEFT JOIN artists ON follows.artist_id = artists.id
                    WHERE follows.user_id = :id ORDER BY timestamp DESC";
                    
                    $sth = $DB->prepare($q);
                    $sth->bindParam(":id",$id);
                    if($sth->execute()) {
                        $this->response['message'] = "returning user follows";
                        $this->response['status'] = 200;
                        $fol = $sth->fetchAll();
                        
                        $i = 0;
                        foreach($fol as $f) {
                            $data[$i]['artist_id'] = $f['artist_id'];
                            $data[$i]['artist_avatar'] = $f['artist_avatar'];
                            $data[$i]['timestamp'] = time::time_approximator($f['timestamp']);
                            $data[$i]['artist_name'] = $f['artist_name'];
                            $i++;
                        }
                        $data['total'] = $i;
                        $this->response['data'] = $data;
                    } else {
                        $this->response['message'] = "Unable to load follows";
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