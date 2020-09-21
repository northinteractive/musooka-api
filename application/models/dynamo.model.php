<?php

/* ========================================
* 
*       Dynamo interaction
 *      Currently Archived
 *      Possible Future implementation
* 
======================================== */

interface dynamoTemplate{
    public function dynamo_register($httpObj);
    public function dynamo_login($httpObj);
}

class dynamo implements dynamoTemplate {
        public function dynamo_register($httpObj) {
            $dynamodb = new AmazonDynamoDB();
            $POST = $httpObj->getRequestVars();

            if(!isset($POST['email']) || !isset($POST['pass'])) {
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

                                $q = array();
                                $q['TableName'] = "auth";
                                $q['Key']['HashKeyElement'] = array(AmazonDynamoDB::TYPE_STRING => $this->email);
                                $response = $dynamodb->get_item($q);

                                if(($response->status == 200) && (isset($response->body->Item))) {
                                                        $b = $response->body->Item;
                                                        $testemail=$b->email->S;
                                } else {
                                                        $testemail=false;

                                }

                                if(($testemail) && ($testemail==$this->email)) {
                                                        $this->response['status']=409;
                                                        $this->response['message']='Email address already exists, please try another';
                                                        $this->response['data'] = array();
                                                        return $this->response;
                                                        exit;
                                } else {
                                                        $this->newId = uniqid();

                                                        // Set up batch requests
                                                        $queue = new CFBatchRequest();
                                                        $queue->use_credentials($dynamodb->credentials);

                                                        // Add items to the batch
                                                        $dynamodb->batch($queue)->put_item(array(
                                                                                    'TableName' => "users",
                                                                                    'Item' => array(
                                                                                                            'id'                     => array( AmazonDynamoDB::TYPE_NUMBER => $this->newId), // Primary (Hash) Key
                                                                                                            'email'                => array( AmazonDynamoDB::TYPE_STRING => $this->email),
                                                                                                            'password'          => array( AmazonDynamoDB::TYPE_STRING => $this->pass),
                                                                                                            'vanity'                => array( AmazonDynamoDB::TYPE_STRING => ''),
                                                                                                            'account_verified' => array( AmazonDynamoDB::TYPE_NUMBER => '0'),
                                                                                                            'fb_id'                 => array( AmazonDynamoDB::TYPE_NUMBER => '0'  )
                                                                                    )
                                                        ));

                                                        $dynamodb->batch($queue)->put_item(array(
                                                                                    'TableName' => "auth",
                                                                                    'Item' => array(
                                                                                                            'email'                => array( AmazonDynamoDB::TYPE_STRING => $this->email), // Primary (Hash) Key
                                                                                                            'password'          => array( AmazonDynamoDB::TYPE_STRING => $this->pass),
                                                                                                            'id'                     => array( AmazonDynamoDB::TYPE_NUMBER => $this->newId)
                                                                                    )
                                                        ));

                                                        // Execute the batch of requests in parallel
                                                        $responses = $dynamodb->batch($queue)->send();

                                                        // Check for success...
                                                        if ($responses->areOK())
                                                        {
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
                                                                                    $this->response['message'] = "Thats it! You are now registered for an invite. We will send it along as soon as we are ready.";
                                                                                    $this->response['data'] = $data;

                                                                                    return $this->response;
                                                                                    exit;
                                                        } else {
                                                                                    $this->response['status']=409;
                                                                                    $this->response['message']='There was an error registering, please try again later';
                                                                                    $this->response['data'] = $responses;
                                                                                    return $this->response;
                                                                                    exit;
                                                        }
                                }
            }
        }

        public function dynamo_login($httpObj) {
                $dynamodb = new AmazonDynamoDB();

                $POST = $httpObj->getRequestVars();

                if(!isset($POST['email']) || !isset($POST['pass']))
                {
                                $this->response['message'] = 'invalid or Empty request';
                                $this->response['status']  = 400;
                                $this->response['data'] = '';
                                return $this->response;
                                exit;
                } else {
                                $POST['email']=strtolower($POST['email']);
                                // validate the email
                                if(!validate::email($POST['email']))
                                {
                                                        $this->response['message'] = "invalid email address, please try again";
                                                        $this->response['status']  = 404;
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
                                                        $this->response['status']  = 404;
                                                        $this->response['data'] = '';
                                                        return $this->response;
                                                        exit;
                                } else {
                                                        $this->pass=md5(SALT . $POST['pass']);
                                }

                                $q = array();
                                $q['TableName'] = "auth";
                                $q['Key']['HashKeyElement'] = array(AmazonDynamoDB::TYPE_STRING => $this->email);
                                $response = $dynamodb->get_item($q);
                                $b = $response->body->Item;

                                if(($b->email->S == $this->email) && ($b->password->S==$this->pass)) {

                                                        $this->userId = (int) $b->id->N;

                                                        $q = array();
                                                        $q['TableName'] = "users";
                                                        $q['Key']['HashKeyElement'] = array(AmazonDynamoDB::TYPE_NUMBER => $b->id->N);
                                                        $response = $dynamodb->get_item($q);
                                                        $b = $response->body->Item;

                                                        // Data array
                                                        $user = array();
                                                        $user['email'] = $this->email;
                                                        $user['userid'] = $this->userId;
                                                        $user['artist_id'] = 0;
                                                        $user['account_verified'] = (int) $b->account_verified->N;
                                                        $user['auth_key'] = md5(USERSALT.$this->userId.USERSALT);
                                                        $user['cookie_expiry'] = time() + (60 * 60 * 24 * 30);

                                                        //Set Cookie
                                                        setcookie('muzooka-server-session-key',$user['auth_key'],time()+(60*60*24*30),"/",".muzooka.com",0);
                                                        setcookie('muzooka-server-session-user-id',$user['userid'],time()+(60*60*24*30),"/",".muzooka.com",0);

                                                        $this->response['status'] = 202;
                                                        $this->response['message'] = "Successfully logged in";
                                                        $this->response['data'] = $user;
                                                        return $this->response;
                                                        exit;
                                } else {
                                                        $this->response['status'] = 404;
                                                        $this->response['message'] = 'Invalid User Credentials';
                                                        $this->response['data'] = array();

                                                        return $this->response;
                                                        exit;
                                }
                }
        }
}

