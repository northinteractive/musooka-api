<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

// Include Models
require ROOT . 'application/models/songs.model.php';

class CachesongController {
    public $updates;
    public $httpObj;
    public $response;
    
    public function __construct() {
        
        if($_REQUEST['song_id']!='') {
           if(cache::cache_song($_REQUEST['song_id'])) {
               echo json_encode("200");
           } else {
               echo json_encode("500");
           }
        }
        
    }
}