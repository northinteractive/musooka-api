<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';
require ROOT . 'application/utilities/time.utility.php';

class AvatarController {
    public $updates;
    public $httpObj;
    public $response;
    
    public function __construct() {
       
        $this->httpObj = RestUtil::processRequest();
        $POST = $this->httpObj->getRequestVars();
        $DB = new DB();
        
        $this->s3 = new AmazonS3();
        
        $this->response = array();
        
        if(isset($POST['artist_id'])) {
            $id = $POST['artist_id'];
            
            $q = "SELECT avatar FROM artists WHERE artists.id=:artist_id";
            $sth = $DB->prepare($q);
            $sth->bindParam(":artist_id",$id);
                    if($sth->execute()) {
                        $temp = $sth->fetchAll();
                        $avatar = $temp[0]['avatar'];
                        
                        $path = "https://s3.amazonaws.com/muzooka-devel/artists/".$id."/";
                        $name = basename($avatar);
                        
                        if($name!="default.png") {
                        
                                $data['path'] = $path;
                                $data['primary'] = $name;

                                $large_check = $path . "l_".$name;
                                $largeExists = @file_get_contents($large_check,null,null,-1,1) ? true : false ;
                                if ($largeExists) {
                                    $data['large'] = "l_".$name;
                                } else {
                                    $data['large'] = null;
                                }

                                $crop_check = $path . "c_".$name;
                                $cropExists = @file_get_contents($crop_check,null,null,-1,1) ? true : false ;
                                if ($cropExists) {
                                    $data['crop'] = "c_".$name;
                                } else {
                                    $data['crop'] = null;
                                }

                                $small_check = $path . "s_".$name;
                                $smallExists = @file_get_contents($small_check,null,null,-1,1) ? true : false ;
                                if ($smallExists) {
                                    $data['small'] = "s_".$name;
                                } else {
                                    $data['small'] = null;
                                }

                                $ios_check = $path . "i_".$name;
                                $iosExists = @file_get_contents($ios_check,null,null,-1,1) ? true : false ;
                                
                                if ($iosExists) {
                                    $data['ios'] = "i_".$name;
                                } else {
                                    if($largeExists) {
                                        $i = $large_check;
                                    } else {
                                        $i = $path.$name;
                                    }

                                    $dest = ROOT . "/temp/temp_i_".$name;
                                    $ult = ROOT . "/temp/i_".$name;

                                    copy($i, $dest);

                                    require ROOT."/_resources/resize/phpthumb/ThumbLib.inc.php";
                                    $thumb = PhpThumbFactory::create($dest);
                                    $thumb->adaptiveResize(640, 300);  
                                    $thumb->save($ult, $format = 'JPG');

                                    if($this->s3->if_bucket_exists('muzooka-devel')) {
                                            $s3_success = true;
                                            
                                            $response = $this->s3->create_object('muzooka-devel', "artists/".$id."/i_".$name, array(
                                                    'acl' => AmazonS3::ACL_PUBLIC,
                                                    'headers' => array(
                                                        'Content-Type' => 'image/jpeg',
                                                        'Cache-Control' => 'public,max-age=30240000',
                                                    ),
                                                    'fileUpload' => $ult
                                            ));

                                            $s3_response = $response;

                                            // Check Response
                                            if($s3_response) {
                                                
                                                    unlink($ult);
                                                    unlink($dest);
                                                
                                                    $data['ios'] = "i_".$name;
                                                    $this->response['status'] = 200;
                                                    $this->response['message'] = "Artist Avatars Loaded Successfully";
                                            } else {
                                                    $data['ios'] = null;
                                                    $this->response['message'] = "Failed to upload new image crop to S3";
                                                    $this->response['status'] = 200;
                                            }
                                    }
                                }

                                // Return Data
                                $this->response['data'] = $data;
                            } else { // Default Image
                                $data['path'] = "https://s3.amazonaws.com/muzooka-website/images/";
                                $data['ios'] = "default.png";
                                $data['primary'] = "default.png";
                                $data['crop'] = "default.png";
                                $data['small'] = "default.png";
                                $data['large'] = "default.png";

                                $this->response['data'] = $data;
                                $this->response['status'] = 200;
                                $this->response['message'] = "returning default image";
                            }
                   } else { // Query Failed
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