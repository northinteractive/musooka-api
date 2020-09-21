<?php
session_start();

if(isset($_REQUEST['key']) && isset($_REQUEST['email']) && isset($_REQUEST['id'])) {
    
    $key = md5(USERSALT.$_REQUEST['id'].USERSALT);
    
    if($key == $_REQUEST['key']) {
        $_SESSION['muzooka_logged_in']=true;
        $_SESSION['email'] = $_REQUEST['email'];
        $_SESSION['userid'] = $_REQUEST['id'];
        $_SESSION['auth_key'] = md5(USERSALT.$_REQUEST['id'].USERSALT);
        
        if(isset($_REQUEST['artist_id']) && ($_REQUEST['artist_id'] != 'undefined')) {
            $_SESSION['is_artist'] = 1;
            $_SESSION['artist_id'] = $_REQUEST['artist_id'];
        } else {
            $_SESSION['is_artist'] = 0;
        }
        
        echo "success";
    } else {
        echo "Your account is not currently verified for beta access. We will let you know when your invite is available.";
    }
} else {
    echo "A required field was left empty. Please try again.";
}