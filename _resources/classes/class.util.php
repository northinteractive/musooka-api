<?php

class Util {
    public function read_directory($DirectoryName) {
	    if (empty($DirectoryName)) { $this->error("Incorrect Directory Name"); return false; }
	    
	    $imageinfo = array();
	    $filelist = array();
	    $ii = 0;
	    
	    foreach (glob($DirectoryName."*.*") as $item) {
	$fs = filesize($item);
	$lm = date('F j, Y' , filemtime($item));				
	if ($imageinfo = getimagesize($item)) {
	    $imagewidth = $imageinfo[0];
	    $imageheight = $imageinfo[1]; 
	    $filelist[$ii]['width'] = $imagewidth;
	    $filelist[$ii]['height'] = $imageheight;
	}
	$filelist[$ii]['name'] = substr( $item, ( strrpos( $item, "/" ) +1 ) );  
	$filelist[$ii]['filesize'] = $fs;
	$filelist[$ii]['modifydate'] = $lm;
	$filelist[$ii]['dir'] = 0;
	$ii++;
	    }
	    
		    foreach (glob($DirectoryName."*") as $item) {
			    if (is_dir($item)) {
	    $filelist[$ii]['name'] = substr( $item, ( strrpos( $item, "/" ) +1 ) );
	    $filelist[$ii]['dir'] = 1;
	    $ii++;
	    }
		    }		
	    return $filelist;
    }
}