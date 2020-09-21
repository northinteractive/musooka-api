<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

class TogglegenreController {
    public $update;
    public $httpObj;
    public $response;
    
    public function __construct() {
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        $POST = $this->httpObj->getRequestVars();
        $DB = new DB();
        
        if(!isset($POST['user_id']) || ($POST['user_id']=='')) {
            $this->response['message'] = 'You must provide a user_id';
            $this->response['status'] = 500;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
        
        if(authorize::key($POST['user_id'],$POST['key'])) {
            
            $q = "SELECT genre,filtering FROM users WHERE users.id=:id LIMIT 1";
            $sth = $DB->prepare($q);
            $sth->bindParam(":id",$POST['user_id']);
            $sth->execute();
            $a = $sth->fetchAll();
            
            $genres = json_decode(stripslashes($a[0]['genre']));
            $n = count($genres);
            
            $updated = false;
            
            (int) $genre = $POST['genre'];
            
            if($n>0) {
                foreach($genres as $k => $g) {
                    if($k == $genre) {
                        if($genres->$k==1) { $genres->$k = 0; $subd = $g; $updated = true;} else { $genres->$k = 1;  $subd = $g; $updated = true;}
                        
                    }
                }
            }
            
            if(!$updated) {
                $genres->$genre = 1;
            }
            
            if($subd == 1) { $subd = 0; } else { $subd = 1; }
            
            $newGenres = json_encode($genres);
            
            $q = "UPDATE users SET genre=:genre WHERE users.id=:id LIMIT 1";
            $sth = $DB->prepare($q);
            $sth->bindParam(":genre",$newGenres);
            $sth->bindParam(":id",$POST['user_id']);
            
            if($sth->execute()) {
                $this->response['message'] = 'Successfully toggled filter for this genre';
                $this->response['status']  = 200;
                $this->response['data'] = array("subscribed" => $subd);
            } else { 
                $this->response['message'] = 'There was an error';
                $this->response['status']  = 500;
                $this->response['data'] = $sth->errorInfo();
            }
            
        } else {
            $this->response['message'] = 'Invalid Credentials';
            $this->response['status']  = 401;
            $this->response['data'] = array(); 
            return $this->response;
            exit;
        }
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}