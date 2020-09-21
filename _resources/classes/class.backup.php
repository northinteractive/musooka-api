<?php

class Backup {
    
    private $s3;
    public $backupName;
    
    public function __construct() {
        $this->s3 = new AmazonS3();
        $ts = time();
        $this->filename = $ts."-all-muzooka-db.sql";
        $this->backupName = "/ebs1/archive/".$this->filename;
    }
    
    public function exec_backup() {
        
        echo exec("mysqldump --all-databases > ".$this->backupName." --user=590540_muzooka --password=dbadmin1");
        
        $s3_success = false;
        
        if($this->s3->if_bucket_exists('muzooka-backups')) {
            $s3_success = true;
            
            $response = $this->s3->create_object('muzooka-backups', $this->filename, array(
                'acl' => AmazonS3::ACL_PUBLIC,
                'fileUpload' => $this->backupName
            ));
            
            $s3_response = $response;
            
            if(!$s3_response) {
                return "Failed to upload file to S3";
            } 
        } else {
            return "Bucket does not exist";
        }
    }
}