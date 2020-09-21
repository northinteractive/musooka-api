<?php

class Artist {
    
    private $DB;
    private $s3;
    private $userInfo;
    
    public $upload_error;
    
    public function __construct() {
        $this->DB = new DB();
        $this->s3 = new AmazonS3();
        
        $q="SELECT id,name,avatar,bio,(SELECT count(id) FROM follows WHERE follows.artist_id = artists.id) AS follows FROM artists WHERE user_id=:user_id LIMIT 1";
        $sth=$this->DB->prepare($q);
        $sth->bindParam(":user_id",$_SESSION['userid']);
        $sth->execute();
        $this->userInfo = $sth->fetch(PDO::FETCH_LAZY);
        if(isset($this->userInfo['id'])) {
        
            if(isset($this->userInfo['id']))      { $this->artistId = $this->userInfo['id']; } else { $this->artistId = false; }
            if(isset($this->userInfo['name']))    { $this->artistName = $this->userInfo['name']; } else { $this->artistName = false; }
            if(isset($this->userInfo['avatar']))  { $this->artistAvatar = $this->userInfo['avatar']; } else { $this->artistAvatar= false; }
            if(isset($this->userInfo['bio']))     { $this->artistBio = $this->userInfo['bio']; } else { $this->artistBio = false; }
            if(isset($this->userInfo['follows'])) { $this->follows = $this->userInfo['follows']; } else { $this->follows = false; }
        } else {
            $this->follows = false;
            $this->artistId = false;
            $this->artistName = false;
            $this->artistAvatar= false;
            $this->artistBio = false;
        }
    }
    
    public function avatar($h) {
        $this->upload_error = false;
        
        $filename = preg_replace("/[^a-z0-9\.]/","",strtolower($h['avatar']['name']));
        $ext = substr($filename,(strlen($filename)-3),3);
        
        
        if($ext == 'jpg') {
            
            // Resizing
            if(is_writable(ROOT . 'avatars/')) {
                $tmp = ROOT . "avatars/".$filename;
                $tmp_to_resize = (isset($h['avatar']['tmp_name'])) ? urldecode($h['avatar']['tmp_name']) : null;
                try { $thumb = PhpThumbFactory::create($tmp_to_resize); } catch (Exception $e) { }
                
                $thumb->resize(200, 200);
                $thumb->save($tmp);
                
                // Amazon S3 Upload 
                $avatar_uri = "https://s3.amazonaws.com/muzooka-devel/artists/".$this->artistId."/".$filename;
                $s3_success = false;
                
                if($this->s3->if_bucket_exists('muzooka-devel')) {
                    $s3_success = true;
                    
                    $response = $this->s3->create_object('muzooka-devel', "artists/".$this->artistId."/".$filename, array(
                        'acl' => AmazonS3::ACL_PUBLIC,
                        'fileUpload' => $tmp
                    ));
                    $s3_response = $response;
                    
                    if($s3_response) {
                        $q="UPDATE artists SET avatar=:avatar WHERE user_id = :user_id LIMIT 1";
                        $sth = $this->DB->prepare($q);
                        $sth->bindParam(":avatar",$avatar_uri);
                        $sth->bindParam(":user_id",$_SESSION['userid']);
                        $sth->execute();
                        $this->artistAvatar = $avatar_uri;
                        
                        // All good, delete the tmp file
                        unlink($tmp);
                    } 
                } else {
                    $this->upload_error = "Bucket failure";
                }
            } else {
                $this->upload_error = "There was an error adding your image. Please try again";
            }
            
        } else {
            $this->upload_error = "Invalid file type. Profile image must be a jpg";
        }
    }
}