<?php

class User {
                         private $DB;
                         private $response;
                         private $httpObj;

                         private $pass;
                         private $email;

                         public function __construct() {
                                              $this->auth = false;
                                              $this->DB = new DB();

                                              $this->key = false;
                                              $this->userId = false;

                                              $this->email='';
                                              $this->pass='';

                                              $this->response = array();
                                              $this->response['message'] = '';
                                              $this->response['status'] = null;
                                              $this->response['data'] = '';
                         }

                         public function info($httpObj) {
                                                  $POST = $httpObj->getRequestVars();

                                                  if(authorize::key($POST['user_id'],$POST['key'])) {
                                                                           $this->userId = $POST['user_id'];

                                                                           $q = "SELECT
                                                                                                    users.email, users.vanity,
                                                                                                    (SELECT count(id) FROM playlist_follows WHERE owner_id = :user_id) AS subscribers,
                                                                                                    (SELECT artists.name FROM artists WHERE user_id = :user_id) AS artist_name
                                                                                                    FROM users
                                                                                                    WHERE id = :user_id";

                                                                           $sth = $this->DB->prepare($q);
                                                                           $sth->bindParam(":user_id",$this->userId);

                                                                           try{
                                                                                                    $sth->execute();
                                                                                                    $data = array();

                                                                                                    $r = $sth->fetch(PDO::FETCH_LAZY);

										$data['user_id'] = $this->userId;
                                                                                                    $data['email'] = $r['email'];
                                                                                                    $data['subscribers'] = $r['subscribers'];
                                                                                                    $data['vanity'] = $r['vanity'];

                                                                                                    if(isset($r['artist_name']) && ($r['artist_name'] != '')) {
                                                                                                                             $data['artist_name'] = $r['artist_name'];
                                                                                                    } else {
                                                                                                                             $data['artist_name'] = 0;
                                                                                                    }


                                                                                                    $this->response['message'] = 'Returning user info';
                                                                                                    $this->response['status']  = 200;
                                                                                                    $this->response['data'] = $data;
                                                                                                    return $this->response;
                                                                                                    exit;

                                                                           } catch (PDOException $e) {
                                                                                                    $data['error'] = $sth->errorInfo();
                                                                                                    $data['exception'] = $e;

                                                                                                    $this->response['message'] = 'There was an error';
                                                                                                    $this->response['status']  = 500;
                                                                                                    $this->response['data'] = $data;
                                                                                                    return $this->response;
                                                                                                    exit;
                                                                           }
                                                  } else {
                                                                           $this->response['message'] = 'Invalid Login Credentials';
                                                                           $this->response['status']  = 401;
                                                                           $this->response['data'] = array();
                                                                           return $this->response;
                                                                           exit;
                                                  }

                         }

                         public function register($httpObj) {
                             
                                                  $POST = $httpObj->getRequestVars();
                                                  if(!isset($POST['email']) || !isset($POST['pass']) || !isset($POST['vanity'])) {
                                                      
                                                                           $this->response['message'] = 'Invalid or Empty request';
                                                                           $this->response['status']  = 400;
                                                                           $this->response['data'] = '';
                                                                           return $this->response;
                                                                           exit;
                                                  } else {
                                                                           $this->email=strtolower($POST['email']);
                                                                           // validate the email
                                                                           if(!validate::email($this->email))
                                                                           {
                                                                                                    $this->response['message'] = "invalid email address, please try again";
                                                                                                    $this->response['status']  = 406;
                                                                                                    $this->response['data'] = '';
                                                                                                    return $this->response;
                                                                                                    exit;
                                                                           } else {
                                                                                                    $this->email=htmlentities($POST['email']);
                                                                           }

                                                                           // Validate the password
                                                                           if(!validate::password($POST['pass']))
                                                                           {
                                                                                                    $this->response['message'] = "invalid password, try again";
                                                                                                    $this->response['status']  = 406;
                                                                                                    $this->response['data'] = '';
                                                                                                    return $this->response;
                                                                                                    exit;
                                                                           } else {
                                                                                                    $this->pass=md5(SALT . $POST['pass']);
                                                                           }

                                                                           $q="SELECT email FROM users WHERE email=:email LIMIT 1";
                                                                           $sth = $this->DB->prepare($q);
                                                                           $sth->bindParam(":email",$this->email);
                                                                           // Do check for existing email
                                                                           try { $sth->execute(); } catch (PDOException $e) {
                                                                                                    $this->response['status']=500;
                                                                                                    $this->response['message']='Caught Exception ' . $e;
                                                                                                    $this->response['data'] = '';
                                                                                                    return $this->response;
                                                                                                    exit;
                                                                           }
                                                                           
                                                                           $testemail=$sth->fetch(PDO::FETCH_LAZY);
                                                                           
                                                                           if($testemail['email']==$this->email) {
                                                                               
                                                                                                    $this->response['status']=409;
                                                                                                    $this->response['message']='Email address already exists, please try another';
                                                                                                    $this->response['data'] = array();
                                                                                                    return $this->response;
                                                                                                    exit;
                                                                           } else {
                                                                                                    $vanity = preg_replace("/[^a-z0-9]/", "", strtolower($POST['vanity']));
                                                                                                    $q = "SELECT vanity FROM users WHERE vanity = :vanity ";
                                                                                                    $sth = $this->DB->prepare($q);
                                                                                                    $sth->bindParam(":vanity",$vanity);
                                                                                                    $sth->execute();

                                                                                                    $checkVanity = $sth->fetchAll();
                                                                                                    
                                                                                                    if(isset($checkVanity[0]) && ($checkVanity[0]['vanity'] == $vanity)) {
                                                                                                        
                                                                                                        $this->response['status']=409;
                                                                                                        $this->response['message']='This vanity is already in use, please try again';
                                                                                                        $this->response['data'] = array();
                                                                                                        return $this->response;
                                                                                                        exit;
                                                                                                        
                                                                                                    } else {
                                                                                                        
                                                                                                        $genres = "{\"6\":1,\"7\":1,\"8\":1,\"9\":1,\"10\":1,\"12\":1,\"13\":1,\"14\":1}";
                                                                                        
                                                                                                        $q = 'INSERT INTO users (email,vanity,password,account_verified,filtering,genre) values(:email,:vanity,:password,0,1,:genre)';
                                                                                                        
                                                                                                        $sth = $this->DB->prepare($q);
                                                                                                        $sth->bindParam(":email",$this->email);
                                                                                                        $sth->bindParam(":password",$this->pass);
                                                                                                        $sth->bindParam(":vanity",$vanity);
                                                                                                        $sth->bindParam(":genre",$genres);
                                                                                                        // Do Register
                                                                                                        try { $sth->execute();} catch (PDOException $e) {


                                                                                                            $this->response['status']=500;
                                                                                                            $this->response['message']='Caught Exception ' . $e;
                                                                                                            $this->response['data'] = array();
                                                                                                            return $this->response;
                                                                                                            exit;
                                                                                                        }

                                                                                                        $data['userid']=$this->DB->lastInsertId();
                                                                                                        

                                                                                                        $q="INSERT INTO playlists (name,user_id,weight) values('Favorites',:user_id,0)";
                                                                                                        $sth = $this->DB->prepare($q);
                                                                                                        $sth->bindParam(":user_id",$data['userid']);

                                                                                                        try { $sth->execute(); } catch (PDOException $e) {
                                                                                                                                $this->response['status']=500;
                                                                                                                                $this->response['message']='Caught Exception ' . $e;
                                                                                                                                $this->response['data'] = array();
                                                                                                                                return $this->response;
                                                                                                                                exit;
                                                                                                        }

                                                                                                        $data['email']=$this->email;

                                                                                                        // Successfully created an account
                                                                                                        $this->response['status'] = 201;
                                                                                                        $this->response['message'] = "Thats it! You are now registered for an invite. We will send it along as soon as we are ready. To Get a Priority Invite Tweet @Muzooka";
                                                                                                        $this->response['data'] = $data;

                                                                                                        return $this->response;
                                                                                                        exit;
                                                                                                    }
                                                                           }
                                                  }
                         }
                         
                         public function login($httpObj) {

                                                  $POST = $httpObj->getRequestVars();

                                                  // Check for FB else check for empty POST
                                                  if(isset($POST['fb_id']) && ($POST['fb_id'])) {
                                                                           return $this->fb_check($POST); // Do FB
                                                  } else if(!isset($POST['email']) || !isset($POST['pass'])) {
                                                                           $this->response['message'] = 'invalid or Empty request';
                                                                           $this->response['status']  = 201;
                                                                           $this->response['data'] = '';
                                                                           return $this->response;
                                                                           exit;
                                                  } else {
                                                                           $POST['email']=strtolower($POST['email']);
                                                                           // validate the email
                                                                           if(!validate::email($POST['email']))
                                                                           {
                                                                                                    $this->response['message'] = "invalid email address, please try again";
                                                                                                    $this->response['status']  = 201;
                                                                                                    $this->response['data'] = array();
                                                                                                    return $this->response;
                                                                                                    exit;
                                                                           } else {
                                                                                                    $this->email=htmlentities(strtolower($POST['email']));
                                                                           }

                                                                           // Validate the password
                                                                           if(!validate::password($POST['pass']))
                                                                           {
                                                                                                    $this->response['message'] = "invalid password, try again";
                                                                                                    $this->response['status']  = 201;
                                                                                                    $this->response['data'] = '';
                                                                                                    return $this->response;
                                                                                                    exit;
                                                                           } else {
                                                                                                    $this->pass=md5(SALT . $POST['pass']);
                                                                           }

                                                                           // Prepare query
                                                                           $q="SELECT
                                                                                   users.id,
                                                                                   users.email,
                                                                                   users.password,
                                                                                   users.account_verified,
                                                                                   (SELECT id FROM artists WHERE artists.user_id = users.id) AS artist_id,
                                                                                   (SELECT count(playlist_follows.id) FROM playlist_follows WHERE playlist_follows.owner_id = users.id) AS subscriber_count,
                                                                                   (SELECT count(follows.id) FROM follows WHERE follows.user_id = users.id) AS follow_count,
                                                                                   (SELECT count(votes.id) FROM votes WHERE votes.user_id = users.id) AS vote_count
                                                                               FROM users
                                                                               WHERE users.email=:email
                                                                               AND users.password=:pass LIMIT 1";

                                                                            $sth = $this->DB->prepare($q);

                                                                            $sth->bindParam(':pass',$this->pass);
                                                                            $sth->bindParam(':email',$this->email);

                                                                            // Execute Query, apply data to $check
                                                                            try { 
                                                                               $sth->execute();
                                                                               
                                                                            } catch (PDOException $e) {
                                                                                                    $this->response['status']=500;
                                                                                                    $this->response['message']='Caught Exception ' . $e;
                                                                                                    $this->response['data'] = array();
                                                                                                    return $this->response;
                                                                                                    exit;
                                                                            }

                                                                            $data = $sth->fetch(PDO::FETCH_LAZY);

                                                                            // Verify user exists
                                                                            if(isset($data) && (($data['email']==$this->email) && ($data['password']==$this->pass))) {

                                                                            // Data array
                                                                            $user = array();
                                                                            $user['email'] = $this->email;
                                                                            $user['userid'] = $data['id'];
                                                                            $user['artist_id'] = $data['artist_id'];
                                                                            $user['account_verified'] = $data['account_verified'];
                                                                            $user['auth_key'] = md5(USERSALT.$user['userid'].USERSALT);
                                                                            $user['cookie_expiry'] = time() + (60 * 60 * 24 * 30);
                                                                            
                                                                            // S3 JSON Conversion
                                                                            // $s3json = new S3json;
                                                                            // $s3json->set("user:".$this->email.":id",$data['id']);
                                                                            // $s3json->set("user:".$this->email.":password",$data['password']);
                                                                            // $s3json->set("user:".$this->email.":artistid",$data['artist_id']);
                                                                            // End S3 JSON Conversion
                                                                            
                                                                            //Set Cookie
                                                                            setcookie('muzooka-server-session-key',$user['auth_key'],time()+(60*60*24*30),"/",".muzooka.com",0);
                                                                            setcookie('muzooka-server-session-user-id',$user['userid'],time()+(60*60*24*30),"/",".muzooka.com",0);
                                                                            $this->response['status'] = 200;
                                                                            $this->response['message'] = "Successfully logged in";
                                                                            $this->response['data'] = $user;
                                                                            return $this->response;
                                                                            exit;
                                                                                } else {
                                                                            $this->response['status'] = 201;
                                                                            $this->response['message'] = 'Invalid User Credentials';
                                                                            $this->response['data'] = array();

                                                                            return $this->response;
                                                                            exit;
                                                                      }
                                                  }
                         }

                         public function follows($httpObj) {
                                                  $POST = $httpObj->getRequestVars();

                                                  if(authorize::key($POST['user_id'],$POST['key'])) {
                                                                           $this->userId=$POST['user_id'];

                                                                           $q ="SELECT
                                                                                                    follows.id AS follow_id,
                                                                                                    artists.id AS artist_id,
                                                                                                    artists.avatar AS artist_avatar,
                                                                                                    artists.name AS artist_name,
                                                                                                    artists.vanity AS artist_vanity
                                                                                                    FROM follows
                                                                                                    LEFT JOIN artists ON follows.artist_id = artists.id
                                                                                                    WHERE follows.user_id = :user_id";

                                                                           $sth = $this->DB->prepare($q);
                                                                           $sth->bindParam(":user_id",$this->userId);

                                                                           try {
                                                                                                    $sth->execute();
                                                                                                    $follows = $sth->fetchAll();
                                                                                                    $i = 0;
                                                                                                    foreach($follows as $f) {
                                                                                                        $data[$i]['follow_id'] = $f['follow_id'];
                                                                                                        $data[$i]['artist_id'] = $f['artist_id'];
                                                                                                        $data[$i]['artist_name'] = $f['artist_name'];
                                                                                                        $data[$i]['artist_avatar'] = $f['artist_avatar'];
                                                                                                        $data[$i]['artist_vanity'] = $f['artist_vanity'];
                                                                                                        $i++;
                                                                                                    }

                                                                                                    $this->response['status']=200;
                                                                                                    $this->response['message']='returning list of follows for the user';
                                                                                                    $this->response['data'] = $data;
                                                                                                    return $this->response;
                                                                                                    exit;

                                                                           } catch(PDOException $e) {
                                                                                                    $this->response['status']=500;
                                                                                                    $this->response['message']='Caught Exception ' . $e;
                                                                                                    $this->response['data'] = array();
                                                                                                    return $this->response;
                                                                                                    exit;
                                                                           }

                                                  } else {
                                                                           $this->response['status'] = 404;
                                                                           $this->response['message'] = 'Invalid User Credentials';
                                                                           $this->response['data'] = array();

                                                                           return $this->response;
                                                                           exit;
                                                  }
                         }

    public function fb_check($POST) {

                         $this->response['status'] = 404;
                         $this->response['message'] = 'FB Support coming soon';
                         $this->response['data'] = array();

                         return $this->response;
                         exit;
    }

}