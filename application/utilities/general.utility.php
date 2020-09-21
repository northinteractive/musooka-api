<?php

/* ========================================
* 
*     General Tools
 *      @microtime_float()
 *      @get_artist_avatar // construct artist avatars and upload to S3
* 
======================================== */

interface generalTemplate {
    public function microtime_float();
    public function get_artist_avatar($artist_id); // Now implemented by cache tools
}

/* ========================================
* 
*     General utilities
* 
======================================== */

class general implements generalTemplate {
    
    public function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
    
    public function get_artist_avatar($artist_id){
        
            global $redisSearch;
        
            /*
            * Instantiate Local Resources
            */
            
            if(!class_exists("PhpThumbFactory")) {
                    require ROOT."/_resources/resize/phpthumb/ThumbLib.inc.php";
            }
            
            $db = new DB();
            $s3 = new AmazonS3();
        
            if(!empty($artist_id)) {
                
                        $IOS_AVATAR = $redisSearch->get("avatar:ios:".$artist_id);
                        $CROP_AVATAR = $redisSearch->get("avatar:crop:".$artist_id);
                        
                        if(!$CROP_AVATAR || !$IOS_AVATAR) {
                            /*
                            * Pull avatar / image name from database
                            */

                            $q = "SELECT avatar FROM artists WHERE artists.id=:artist_id LIMIT 1";
                            $sth = $db->prepare($q);
                            $sth->bindParam(":artist_id",$artist_id);
                            $sth->execute();
                            $tmp = $sth->fetchAll();
                            $avatar = $tmp[0]['avatar'];

                            /*
                            * Instantiate S3 and get image name
                            */

                            $s3 = new AmazonS3();
                            $path = "https://s3.amazonaws.com/muzooka-devel/artists/".$artist_id."/";
                            $name = basename($avatar);

                            /*
                            * Check for default image name.
                            * If image not default, do iOS check
                            */

                            if($name!="default.png") {

                                    // Check for ios image
                                    $ios_check = $path . "i_".$name;
                                    $iosExists = @file_get_contents($ios_check,null,null,-1,1) ? true : false ;

                                    // Check for Large image
                                    $large_check = $path . "l_".$name;
                                    $largeExists = @file_get_contents($large_check,null,null,-1,1) ? true : false ;

                                    $crop_check = $path . "c_".$name;
                                    $cropExists = @file_get_contents($crop_check,null,null,-1,1) ? true : false ;

                                    if($cropExists) {
                                            $size = getimagesize($crop_check);
                                            if(($size[0]!=84) || ($size[1]!=84)) {
                                                    $cropExists = false;
                                            }
                                    }

                                    if ($cropExists) {
                                                $CROP_AVATAR = $path."c_".$name;
                                    } else {
                                                if($largeExists) {
                                                        $i = $large_check;
                                                } else {
                                                        $i = $path.$name;
                                                }

                                                $dest = ROOT . "/temp/temp_c_".$name;
                                                $ult = ROOT . "/temp/c_".$name;

                                                /*
                                                *  Duplicate Main Image
                                                *  Resize to crop size
                                                */

                                                copy($i, $dest);

                                                $thumb = PhpThumbFactory::create($dest);
                                                $thumb->adaptiveResize(84, 84);  
                                                $thumb->save($ult, $format = 'JPG');

                                                /*
                                                *  Push to S3
                                                */

                                                if($s3->if_bucket_exists('muzooka-devel')) {
                                                        $s3_response = $s3->create_object('muzooka-devel', "artists/".$artist_id."/c_".$name, array(
                                                                'acl' => AmazonS3::ACL_PUBLIC,
                                                                'headers' => array(
                                                                    'Content-Type' => 'image/jpeg',
                                                                    'Cache-Control' => 'public,max-age=30240000',
                                                                ),
                                                                'fileUpload' => $ult
                                                        ));

                                                        // Check Response
                                                        if($s3_response) {

                                                                unlink($ult);
                                                                unlink($dest);

                                                                $CROP_AVATAR = $path."c_".$name;
                                                        } else {
                                                                return false;
                                                        } // Done checking response
                                            } // Done S3 Push
                                } // Done checking if Crop Exists

                                if($iosExists) {
                                        $size = getimagesize($ios_check);
                                        if(($size[0]!=640) || ($size[1]!=300)) {
                                                $iosExists = false;
                                        }
                                }

                                if ($iosExists) {
                                            $IOS_AVATAR = $path."i_".$name;
                                } else {
                                            if($largeExists) {
                                                    $i = $large_check;
                                            } else {
                                                    $i = $path.$name;
                                            }

                                            $dest = ROOT . "/temp/temp_i_".$name;
                                            $ult = ROOT . "/temp/i_".$name;

                                            copy($i, $dest);
                                            $thumb = PhpThumbFactory::create($dest);
                                            $thumb->adaptiveResize(640, 300);  
                                            $thumb->save($ult, $format = 'JPG');

                                            if($s3->if_bucket_exists('muzooka-devel')) {
                                                        $s3_response = $s3->create_object('muzooka-devel', "artists/".$artist_id."/i_".$name, array(
                                                                'acl' => AmazonS3::ACL_PUBLIC,
                                                                'headers' => array(
                                                                    'Content-Type' => 'image/jpeg',
                                                                    'Cache-Control' => 'public,max-age=30240000',
                                                                ),
                                                                'fileUpload' => $ult
                                                        ));

                                                        // Check Response
                                                        if($s3_response) {

                                                                unlink($ult);
                                                                unlink($dest);

                                                                $IOS_AVATAR = $path."i_".$name;
                                                        } // Done Checking Response
                                            } // Done S3 Push
                                    } // Done IOS Check
                                    
                                    // Set new Redis values
                                    $redisSearch->set("avatar:ios:".$artist_id,$IOS_AVATAR);
                                    $redisSearch->set("avatar:crop:".$artist_id,$CROP_AVATAR);
                                    
                                    $response = array("ios" => $IOS_AVATAR, "crop" => $CROP_AVATAR);
                                    return json_encode($response);
                                    
                            } else {
                                // Image is default
                                $CROP_AVATAR = "https://s3.amazonaws.com/muzooka-website/images/default.png";
                                $IOS_AVATAR = "https://s3.amazonaws.com/muzooka-website/images/default.png";
                                
                                $response = array("ios" => $IOS_AVATAR, "crop" => $CROP_AVATAR);
                                return json_encode($response);
                            }
                        } else {
                            $response = array("ios" => $IOS_AVATAR, "crop" => $CROP_AVATAR);
                            return json_encode($response);
                        }
            } else {
                // No Id Passed
                return false;
            }
        }
}