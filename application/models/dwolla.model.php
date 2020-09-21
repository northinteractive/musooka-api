<?php

interface DwollaInterface {
    public function __construct();
    public function initiate($httpObj);
    public function get_artist_dwolla_id($artistToken);
    public function dwolla_song_info($httpObj);
    public function dwolla_purchase_info($httpObj);
    public function dwolla_purchase_list($httpObj);
    public function dwolla_payment($httpObj);
    public function get_artist_token($artist_id);
}

class Dwolla implements DwollaInterface {
    
    public $dwolla;
    public $redirectUrl;
    
    private $apiKey;
    private $apiSecret;
    private $token;
    
    public function __construct() {
        $this->dwolla = new DwollaRestClient("AflhbAlIl2n4KHXHGTEwfCQdDRY5uvG4TvJ8SUqy6ZWPIMVdA2","7FKZESNZgKxa2Xk14lPeNSMnGL2ez0ITsZGoODUWfUh7QXPrAp","http://muzooka.com/dwolla_connect.php");
        $this->dwolla->redirectUrl = $this->dwolla->getAuthUrl();
    }
    
    public function get_artist_token($artist_id=false) {
        $s3json = new S3json("muzooka-db");
        
        if($artist_id) {
            $this->artistUserId = cache::user_id_from_artist_id($song->artist_id);
            $this->artistToken = $s3json->get("dwollatoken:".$this->artistUserId);
        }
        
        return $this->artistToken;
    }
    
    public function initiate($httpObj) {
        $POST = $httpObj->getRequestVars();
        if(authorize::key($POST['user_id'],$POST['key'])) {
            
                $s3json = new S3json("muzooka-db");
                $token = $s3json->get("dwollatoken:".$POST['user_id']);
                
                if(!$token) {
                    $db = new DB();
                    $q = "SELECT dwolla_id FROM users WHERE user_id=:user_id LIMIT 1";
                    $sth = $db->prepare($q);
                    $sth->bindParam(":user_id",$POST['user_id']);
                    $sth->execute();
                    $tmp = $sth->fetchAll();
                    
                    // Get user token from Database
                    $dbToken = $tmp[0]['dwolla_id'];
                    
                    if($dbToken!='') {
                        $s3json->set("dwollatoken:".$POST['user_id'],$dbToken);
                        
                        // Valid Token Found, set token
                        $this->dwolla->token = $dbToken;
                        $this->dwolla->setToken($this->dwolla->token);

                        $me = $this->dwolla->me();

                        $this->response = array();
                        $this->response['message'] = 'Successfully Connected to Dwolla API, account is valid';
                        $this->response['status'] = 200;
                        $this->response['token'] = $this->dwolla->token;
                        $this->response['data'] = $me;
                        return $this->response;
                        exit;
                    } else {
                        $this->response = array();
                        $this->response['message'] = 'No Valid Token provided. Please follow this link to try again <a href="'.$this->dwolla->redirectUrl.'">'.$this->dwolla->redirectUrl.'</a>';
                        $this->response['status'] = 500;
                        $this->response['data'] = array("redirect_url" => $this->dwolla->redirectUrl);
                        return $this->response;
                        exit;
                    }
                } else {
                        // Valid Token Found, set token
                        $this->dwolla->token = $token;
                        $this->dwolla->setToken($this->dwolla->token);

                        $me = $this->dwolla->me();

                        $this->response = array();
                        $this->response['message'] = 'Successfully Connected to Dwolla API, account is valid';
                        $this->response['status'] = 200;
                        $this->response['token'] = $this->dwolla->token;
                        $this->response['data'] = $me;
                        return $this->response;
                        exit;
                }
        } else {
            $this->response = array();
            $this->response['message'] = 'Not Authorized: Please provide a valid user_id and key';
            $this->response['status'] = 500;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
    
    public function get_artist_dwolla_id($artistToken=false) {
        if($artistToken) {
            $artistDwolla = new DwollaRestClient("AflhbAlIl2n4KHXHGTEwfCQdDRY5uvG4TvJ8SUqy6ZWPIMVdA2","7FKZESNZgKxa2Xk14lPeNSMnGL2ez0ITsZGoODUWfUh7QXPrAp","http://muzooka.com/dwolla_connect.php");
            $artistDwolla->setToken($artistToken);
            return $artistDwolla->me();
        } else {
            return false;
        }
    }
    
    public function dwolla_song_info($httpObj) {
        $POST = $httpObj->getRequestVars();
        if(authorize::key($POST['user_id'],$POST['key'])) {
            
            $s3json = new S3json("muzooka-db");
            $token = $s3json->get("dwollatoken:".$POST['user_id']);
            
            if(isset($token) && $token) {
                
                // Valid Token Found, set token
                $this->dwolla->token = $token;
                $this->dwolla->setToken($token);
                
                if(isset($POST['song_id'])) { $this->songId = $POST['song_id'];} else { $this->songId = false; }
                
                if($this->songId) {
                    cache::cache_song($this->songId);
                    $song = cache::song_from_cache($this->songId);
                    $this->artistUserId = cache::user_id_from_artist_id($song->artist_id);
                    $this->artistToken = $s3json->get("dwollatoken:".$this->artistUserId);
                    
                    if($song->price!='') {
                        $this->songPrice = $song->price;
                    } else {
                        $this->songPrice = false;
                    }
                    
                    // Check if Artist Token Exists
                    if($this->artistToken) {
                        $artistInfo = $this->get_artist_dwolla_id($this->artistToken);
                        $artistDwollaId = $artistInfo['Id'];
                    } else {
                        $artistDwollaId = false;
                    }
                    
                    $data = array();
                    $data['artist_dwolla_id'] = $artistDwollaId;
                    $data['song_id'] = $this->songId;
                    $data['song_price'] = $this->songPrice;
                    
                    $this->response = array();
                    $this->response['message'] = 'Returning Song Info and Artist Dwolla Info';
                    $this->response['status'] = 200;
                    $this->response['data'] = $data;
                    return $this->response;
                    exit;
                    
                } else {
                    $this->response = array();
                    $this->response['message'] = 'A song ID is required';
                    $this->response['status'] = 500;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                }
            } else {
                $this->response = array();
                $this->response['message'] = 'Token has not been set, please reset account';
                $this->response['status'] = 500;
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
        } else {
            $this->response = array();
            $this->response['message'] = 'Invalide User id and key';
            $this->response['status'] = 500;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
    
    public function dwolla_purchase_info($httpObj) {
        $POST = $httpObj->getRequestVars();
        
        if(isset($POST['song_id']) && ($POST['song_id']!='')) {
            $this->songId = $POST['song_id'];
            
            $db = new DB();
            
            $q = "SELECT
                    songs.id AS song_id,
                    songs.title AS song_title,
                    songs.uri AS song_uri,
                    songs.artist_name AS artist_name,
                    songs.artist_id AS artist_id,
                    songs.price AS song_price
                    FROM songs WHERE id=:song_id LIMIT 1";
            $sth = $db->prepare($q);
            $sth->bindParam(":song_id",$this->songId);
            $sth->execute();
            
            $song = $sth->fetchAll();
            $song = $song[0];
            
            if(!empty($song)) {
                
                if($song['song_price']!='') {
                    
                    $data = array();
                    $data['song_id'] = $song['song_id'];
                    $data['song_title'] = $song['song_title'];
                    $data['song_uri'] = $song['song_uri'];
                    $data['artist_name'] = $song['artist_name'];
                    $data['song_price'] = $song['song_price'];
                    $data['artist_id'] = $song['artist_id'];
                    
                    // Getting artist avatars
                    $avatar = cache::avatar_from_cache($song['artist_id']);
                    $data['ios_avatar'] = $avatar->ios;
                    $data['crop_avatar'] = $avatar->crop;
                    // Done artist avatars
                    
                    // No Purchases Found
                    $this->response = array();
                    $this->response['message'] = 'Returning Song Purchase Data';
                    $this->response['status'] = 200;
                    $this->response['data'] = $data;
                    return $this->response;
                    exit;
                    
                } else {
                    
                    // No Purchases Found
                    $this->response = array();
                    $this->response['message'] = 'Sorry, this song is not available for purchase';
                    $this->response['status'] = 500;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                }
                
            } else {
                // No Purchases Found
                $this->response = array();
                $this->response['message'] = 'No Song info found, invalid song or song has been removed';
                $this->response['status'] = 500;
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
            
        } else {
            // No Purchases Found
            $this->response = array();
            $this->response['message'] = 'Invalid Song';
            $this->response['status'] = 500;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
    
    public function dwolla_purchase_list($httpObj) {
        $POST = $httpObj->getRequestVars();
        
        if(isset($POST['user_id'])) { $this->userId = $POST['user_id']; } else { $this->userId = false; }
        
        if(authorize::key($this->userId,$POST['key'])) {
            $s3json = new S3json("muzooka-db");
            $token = $s3json->get("dwollatoken:".$this->userId);
            
            if(isset($token) && $token) {
                // Valid Token Found, set token
                $this->dwolla->token = $token;
                $this->dwolla->setToken($token);
                
                $purchases = $s3json->get("purchases:".$this->userId);
                
                if($purchases) {
                    
                    $list = array();
                    
                    foreach($purchases as $p) {
                        $list[] = cache::song_from_cache($p->song_id);
                    }
                    
                    $this->response = array();
                    $this->response['message'] = 'Returning list of song purchases';
                    $this->response['status'] = 200;
                    $this->response['data'] = $list;
                    return $this->response;
                    exit;
                    
                } else {
                    // No Purchases Found
                    $this->response = array();
                    $this->response['message'] = 'No Purchases found';
                    $this->response['status'] = 500;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                }
                
            } else {
                // Get redirect link
                $this->response = array();
                $this->response['message'] = 'No Valid Token provided. Please follow this link to try again <a href="'.$this->dwolla->redirectUrl.'">'.$this->dwolla->redirectUrl.'</a>';
                $this->response['status'] = 500;
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
        } else {
            // No Purchases Found
                    $this->response = array();
                    $this->response['message'] = 'Invalid id/key combination';
                    $this->response['status'] = 500;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
        }
    }
    
    public function dwolla_payment($httpObj) {
        $POST = $httpObj->getRequestVars();
        if(authorize::key($POST['user_id'],$POST['key'])) {
            
            $s3json = new S3json("muzooka-db");
            
            $token = $s3json->get("dwollatoken:".$POST['user_id']);
            
            if(isset($token) && $token) {
                
                // Valid Token Found, set token
                $this->dwolla->setToken($token);
                
                /*
                 *          Required Values [ pin, destination, amount, notes, song_id, user_id ]
                 */
                
                $s3json = new S3json("muzooka-db");
                
                // check for song ID
                if(isset($POST['song_id'])) { $this->songId = $POST['song_id'];} else { $this->songId = false; }
                
                if(!$this->songId) {
                    $this->response = array();
                    $this->response['message'] = 'You must provide a song_id';
                    $this->response['status'] = 500;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                }
                
                $purchases = $s3json->get("purchases:".$POST['user_id']);
                
                if($purchases) {
                    if(is_array($purchases)) {
                        foreach($purchases as $p) {
                            if($p->song_id==$this->songId) {
                                $this->response = array();
                                $this->response['message'] = 'You have already purchased this song';
                                $this->response['status'] = 200;
                                $this->response['data'] = $p;
                                return $this->response;
                                exit;
                            }
                        }
                    } else {
                        if($purchases->song_id==$this->songId) {
                            
                            $this->response = array();
                            $this->response['message'] = 'You have already purchased this song';
                            $this->response['status'] = 500;
                            $this->response['data'] = array();
                            return $this->response;
                            exit;
                        }
                    }
                }
                
                $song = cache::song_from_cache($this->songId);
                $this->artistUserId = cache::user_id_from_artist_id($song->artist_id);
                $this->artistToken = $s3json->get("dwollatoken:".$this->artistUserId);
                
                $artistDwollaId = $this->get_artist_dwolla_id($this->artistToken);
                // Check for song price, if price, proceed with purchase
                if($song->price) {
                    // Set Object elements
                    $this->pin = $POST['pin'];
                    $this->destination = $artistDwollaId['Id'];
                    $this->amount = $song->price;
                    
                    if(isset($POST['notes'])) { $this->notes = $POST['notes']; } else { $this->notes = "N/A"; }
                    

                    // Set new purchase array items
                    $newPurchase = array();
                    $newPurchase['destination'] = $this->destination;
                    $newPurchase['amount'] = $this->amount;
                    $newPurchase['notes'] = $this->notes;
                    $newPurchase['song_id'] = $POST['song_id'];
                    $newPurchase['paid'] = true;

                    // Append new purchase to existing purchases list
                    $purchases[] = $newPurchase;

                    $success = $this->dwolla->send($this->pin, $this->destination, $this->amount, $this->notes);

                    if($success) {
                        // Save transaction
                        $s3json->set("purchases:".$POST['user_id'],$purchases);
                        
                        // Store Transaction
                        $this->response = array();
                        $this->response['message'] = 'Successfully Purchased Song';
                        $this->response['status'] = 200;
                        $this->response['data'] = array("request_id" => $success);
                        return $this->response;
                        exit;
                    } else {
                        $this->response = array();
                        $this->response['message'] = 'There was an error: '.$this->dwolla->getError();
                        $this->response['status'] = 500;
                        $this->response['data'] = array();
                        return $this->response;
                        exit;
                    }
                    
                } else {
                    
                    // Price is Null
                    
                    $this->response = array();
                    $this->response['message'] = 'This song is not available for sale';
                    $this->response['status'] = 500;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                }
                
                 
                
            } else {
                $this->response = array();
                $this->response['message'] = 'No Valid Token provided. Please follow this link to try again <a href="'.$this->dwolla->redirectUrl.'">'.$this->dwolla->redirectUrl.'</a>';
                $this->response['status'] = 500;
                $this->response['data'] = array();
                return $this->response;
                exit;
            }
            
        } else {
            $this->response = array();
            $this->response['message'] = 'Not Authorized: Please provide a valid user_id and key';
            $this->response['status'] = 500;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
    
}
