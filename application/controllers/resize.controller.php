<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

if(!class_exists("PhpThumbFactory")) {
        require ROOT."/_resources/resize/phpthumb/ThumbLib.inc.php";
}

class ResizeController {
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
            
            /********************************************************
            * 
            *  Do iOS crop
            * 
            ********************************************************/
            
            // Define sizes
            $targ_w = 640;
            $targ_h = 300;
            $jpeg_quality = 80;

            $src = $POST['full_uri'];
            
            $img_r = imagecreatefromjpeg($src);
            $dst_r = ImageCreateTrueColor( $targ_w, $targ_h );
            
            // Crop the image
            imagecopyresampled($dst_r,$img_r,0,0,$POST['cx'],$POST['cy'],
                $targ_w,$targ_h,$POST['cw'],$POST['ch']);
            
            $final = "i_".substr(basename($POST['full_uri']),2,(strlen(basename($POST['full_uri']))-2));
            $output_filename = ROOT . "temp/".$final;
            
            // Save it to a temp location
            imagejpeg($dst_r, $output_filename, $jpeg_quality);
            
            // If successful, upload to S3
            $S3 = new AmazonS3;
            
            if($S3->if_bucket_exists('muzooka-devel')) {
                $response = $S3->create_object('muzooka-devel', "artists/".$POST['artist_id']."/".$final, array(
                    'acl' => AmazonS3::ACL_PUBLIC,
                    'fileUpload' => $output_filename
                ));
            }
            
            if($response) { // First S3 Save is good to go...
                
                /********************************************************
                * 
                *  Do Image Crop
                *  Use $output_filename as resource
                *  Crop to 84x2, save to s3
                * 
                ********************************************************/

               // Name Cropped Image
               $cropImage = "c_".substr(basename($POST['full_uri']),2,(strlen(basename($POST['full_uri']))-2));

               // Set up Ult
               $ult = ROOT . "/temp/".$cropImage;
               $path = "https://s3.amazonaws.com/muzooka-devel/artists/".$POST['artist_id']."/";

               // Do PHP thumb resize
               $thumb = PhpThumbFactory::create($output_filename);
               $thumb->adaptiveResize(84, 84);  
               $thumb->save($ult, $format = 'JPG');

               /*
               *  Push to S3
               */

               if($S3->if_bucket_exists('muzooka-devel')) {
                   $response = $S3->create_object('muzooka-devel', "artists/".$POST['artist_id']."/".$cropImage, array(
                               'acl' => AmazonS3::ACL_PUBLIC,
                               'headers' => array(
                                   'Content-Type' => 'image/jpeg',
                                   'Cache-Control' => 'public,max-age=30240000',
                               ),
                               'fileUpload' => $ult
                       ));

                       // Check Response
                       if($response) {
                            // Destroy Temporary files
                            unlink($output_filename);
                            unlink($ult);

                            global $redisSearch;
                            $redisSearch->set("avatar:ios:".$POST['artist_id'],"https://s3.amazonaws.com/muzooka-devel/artists/".$POST['artist_id']."/".$final);
                            $redisSearch->set("avatar:crop:".$POST['artist_id'],"https://s3.amazonaws.com/muzooka-devel/artists/".$POST['artist_id']."/".$cropImage);

                            $this->response['message'] = 'Successfully cropped the image';
                            $this->response['status']  = 200;
                            $this->response['data'] = array();
                       } else {
                            $this->response['message'] = 'There was an error uploading the ios image';
                            $this->response['status']  = 500;
                            $this->response['data'] = array();
                       }
                } // Done S3 Push
                
                
            } else {
                $this->response['message'] = 'There was an error uploading the crop image';
                $this->response['status']  = 500;
                $this->response['data'] = array();
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