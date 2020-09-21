<?php

class Upload {
    
    public $file;
    public $filename;
    public $DB;
    public $size;
    public $type;
    public $artist_id;
    public $song_name;
    public $artist_name;
    
    public $error;
    public $message;
    
    public function __construct() {
        $this->DB = new DB();
        $this->s3 = new AmazonS3();
        $this->artist_id = '';
        $this->error = false;
        $this->message = false;
    }
    
    public function validate_file() {
        
        if(isset($_POST['artist_id'])) {
            $this->artist_id = $_POST['artist_id'];
        } else {
            $this->error = "A required field (artist id) was not provided. Please try again";
            return false;
            break;
        }
        
        if(isset($_POST['artist_name'])) {
            $this->artist_name = $_POST['artist_name'];
        } else {
            $this->error = "A required field (artist name) was not provided. Please try again";
            return false;
            break;
        }
        
        if(isset($_POST['song_name']) && (strlen($_POST['song_name'])>2)) {
            $this->song_name = $_POST['song_name'];
        } else {
            $this->error = "A required field (song name) was not provided. Please try again";
            return false;
            break;
        }
        
        // Check Extension
        $allowed_ext = array('mp3','m4a','ogg');
        $ext = strtolower(substr($this->file['name'],strlen($this->file['name'])-3,3));
        
        // Check for empty file
        if(!$this->file) {
            $this->error = "No file uploaded.";
            return false;
        } else if(!in_array($ext,$allowed_ext)) {
            $this->error = "Filetype is not allowed. At this time, we only support mp3, m4a and ogg audio formats";
            return false;
        } else {
            return true;
        }
    }
    
    public function move_file_to_queue() {
        global $redis;
        
        // Generate new filename
        $tstamp = time();
        $md5ified = md5(time());
        
        $ext = substr($this->file['name'],strlen($this->file['name'])-3,3);
        $this->filename = $tstamp.$md5ified.".".$ext;
        
        if(move_uploaded_file($this->file['tmp_name'],"/ebs1/queue/".$this->filename)) {
    
            $avatar_uri = "https://s3.amazonaws.com/muzooka-process-queue/".$this->filename;
            $s3_success = false;

            if($this->s3->if_bucket_exists('muzooka-process-queue')) {
                $s3_success = true;
                
                $response = $this->s3->create_object('muzooka-process-queue', $this->filename, array(
                    'acl' => AmazonS3::ACL_PUBLIC,
                    'fileUpload' => "/ebs1/queue/".$this->filename
                ));
                $s3_response = $response;
                // Check Response
                if($s3_response) {
                    $queueString = $tstamp . "|0|" . $this->filename . "|" . $this->artist_id . "|" . $this->song_name . "|" . $this->artist_name;
                    $re = $redis->rpush("global:encodingqueue", $queueString);
                    if(!$re) { $this->error = "Unable to update the queue"; return false; }
                } else {
                    $this->error = "Unable to move uploaded file, please try again.";
                    return false;
                }
            }
            
            $q = "INSERT INTO encoding_queue (timestamp,encoding,filename,artist_id,song_name,artist_name) values (".time().",0,:filename,:artist_id,:song_name,:artist_name)";
            $sth = $this->DB->prepare($q);
            $sth->bindParam(":filename",$this->filename);
            $sth->bindParam(":artist_id",$this->artist_id);
            $sth->bindParam(":song_name",$this->song_name);
            $sth->bindParam(":artist_name",$this->artist_name);
            
            try {
                $sth->execute();
                return true;
            } catch(PDOException $e) {
                $this->error = "Unable to update the queue";
                return false;
            }
        } else {
            $this->error = "Unable to move uploaded file, please try again.";
            return false;
        }
    }
    
    public function process_file($f) {
        $this->file = $f['file'];
        
        // Validate File
        if(!$this->validate_file()) {
            return $this->error;
            break;
        }
        
        // Try moving file to queue
        if(!$this->move_file_to_queue()) {
            return $this->error;
            break;
        }
        $this->message = "Your file has been updated and has entered the conversion queue. It should be available within the next 10 minutes";
        return true;
    }
    
    public function display_form($artist_id,$artist_name) {
        echo "<div class='clear'></div>";
        if($this->error!='') {
            echo "<span class='upload-error'>".$this->error."</span><br/>";
        } else if($this->message!='') {
            echo "<span class='upload-message'>".$this->message."</span><br/>";
        }
        
        echo "
        <div class='uploadform'>
        <form action='http://www.muzooka.com/upload/' method='post' enctype='multipart/form-data'>
            <input type='submit' name='upload_new_file' value='Upload &raquo;' class='upload-button' />
            
            <input type='hidden' name='artist_id' value='".$artist_id."' />
            <input type='hidden' name='MAX_FILE_SIZE' value='20000000' />
            <input type='hidden' name='artist_name' value='".$artist_name."' />
            <div class='left' style='width:300px;'>
                <label style='color:#fff;'>Song name</label>
                <input type='text' name='song_name' class='upload-song-name' />
                <div class='clear' style='height:10px;'></div>
                
                <div class='upload-box'>
                    <input type='file' name='file' id='file' />
                </div>
            </div>
            
            <div class='clear'></div>
        </form>
        </div>";
    }
    
    
}