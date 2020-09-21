<?php

// Time Utility - simple time approximation utility. No Dependencies

class time {
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
}