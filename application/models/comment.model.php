<?php

interface commentTemplate {
    public function __construct();
    public function delete($httpObj);
    public function get($httpObj);
    public function create($httpObj);
}

class Comment implements commentTemplate {
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
    
        $this->response = array();
        $this->response['message'] = '';
        $this->response['status'] = null;
        $this->response['data'] = '';
    }
    
    public function delete($httpObj) {
        $POST = $httpObj->getRequestVars();
        
         if(authorize::key($POST['user_id'],$POST['key'])) {
            if(!isset($POST['comment_id']) || ($POST['comment_id']=='')) {
                $this->response['message'] = 'You must provide a parent_id';
                $this->response['status'] = 500;
                $this->response['data'] = array();
                return $this->response;
                exit;
            } else {
                $this->commentId     = $POST['comment_id'];
            }

            $q = " DELETE comments.* FROM comments 
                        LEFT JOIN updates ON updates.id=comments.parent_id
                        LEFT JOIN artists ON artists.id = updates.artist_id
                        LEFT JOIN users ON artists.user_id = users.id
                        WHERE comments.id=:id 
                        AND (users.id=:user_id
                        OR comments.user_id=:user_id)";
            $sth = $this->DB->prepare($q);
            
            $sth->bindParam(":id",$this->commentId);
            $sth->bindParam(":user_id",$POST['user_id']);
            
            try {
                $sth->execute();
                
                $n = $sth->rowCount();
                
                if($n>0) {
                    $this->response['message'] = 'Successfully deleted the comment';
                    $this->response['status'] = 200;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                } else {
                    $this->response['message'] = 'Comment was not deleted, invalid comment or update owner or comment does not exist.';
                    $this->response['status'] = 500;
                    $this->response['data'] = array();
                    return $this->response;
                    exit;
                }
                
                
            } catch(PDOException $e) {
                $this->response['message'] = 'Failed to get comments.';
                $this->response['status'] = 500;
                $this->response['data'] = $sth->errorInfo();
                return $this->response;
                exit;
            }
         } else {
            $this->response['message'] = 'Invalid Credentials';
            $this->response['status']  = 401;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }

    public function get($httpObj) {
        $POST = $httpObj->getRequestVars();
        
        if(!isset($POST['parent_id']) || ($POST['parent_id']=='')) {
            $this->response['message'] = 'You must provide a parent_id';
            $this->response['status'] = 500;
            $this->response['data'] = array();
            return $this->response;
            exit;
        } else {
            $this->parentId     = $POST['parent_id'];
        }
        
        $q = "SELECT
                comments.id AS comment_id,
                comments.parent_id AS update_id,
                comments.stamp,
                comments.comment,
                users.vanity,
                users.id AS user_id
                FROM comments
                LEFT JOIN users ON users.id = comments.user_id
                WHERE parent_id = :parent_id ORDER BY stamp DESC";
        
        $sth = $this->DB->prepare($q);
        $sth->bindParam(":parent_id",$this->parentId);
        
        try {
            $sth->execute();
            
            $comments = $sth->fetchAll();
            $n = count($comments);
            
            $data = array();
            
            for($i=0;$i<$n;$i++) {
                $data[$i]['comment_id'] = $comments[$i]['comment_id'];
                $data[$i]['user_id'] = $comments[$i]['user_id'];
                $data[$i]['parent_id'] = $comments[$i]['update_id'];
                $data[$i]['stamp'] = $comments[$i]['stamp'];
                $data[$i]['published'] = time::time_approximator($comments[$i]['stamp']);
                $data[$i]['vanity'] = $comments[$i]['vanity'];
                $data[$i]['comment'] = $comments[$i]['comment'];
            }
            
            $this->response['message'] = 'Returning all comments for this update';
            $this->response['status'] = 200;
            $this->response['data'] = $data;
            return $this->response;
            exit;
            
        } catch(PDOException $e) {
            $this->response['message'] = 'Failed to get comments.';
            $this->response['status'] = 500;
            $this->response['data'] = $sth->errorInfo();
            return $this->response;
            exit;
        }
    }

    public function create($httpObj) {
        $POST = $httpObj->getRequestVars();
        
        if(!isset($POST['user_id']) || !isset($POST['parent_id']) || ($POST['user_id']=='') || ($POST['parent_id']=='')) {
            $this->response['message'] = 'You must provide a user_id and parent_id';
            $this->response['status'] = 500;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
        
        if(authorize::key($POST['user_id'],$POST['key'])) {

            $this->userId       = $POST['user_id'];
            $this->parentId     = $POST['parent_id'];
            $this->comment      = html_entity_decode($POST['comment']);
            $this->auth         = true;
            
            if(($this->userId=='') || ($this->parentId=='') || ($this->comment=='')) {
                $this->response['message'] = 'You must provide a user_id and parent_id and comment must not be empty';
                $this->response['status'] = 500;
                $this->response['data'] = array();
            }
            
            $q="INSERT INTO comments (user_id,parent_id,stamp,comment) VALUES (:user_id,:parent_id,:stamp,:comment)";
            $sth = $this->DB->prepare($q);
            $sth->bindParam(":user_id",$this->userId);
            $sth->bindParam(":parent_id",$this->parentId);
            $sth->bindParam(":stamp",time());
            $sth->bindParam(":comment",$this->comment);
            
            try {
                $sth->execute();
                
                $q = "SELECT vanity FROM users WHERE id=:id";
                $sth = $this->DB->prepare($q);
                $sth->bindParam(":id",$this->userId);
                $sth->execute();
                $u = $sth->fetchAll();
                $vanity=$u[0]['vanity'];
                
                $data['comment_id'] = $this->DB->lastInsertId();
                $data['user_id'] = $this->userId;
                $data['parent_id'] = $this->parentId;
                $data['stamp'] = time();
                $data['published'] = date("M D");
                $data['vanity'] = $vanity;
                $data['comment'] = $this->comment;
                
                $q = "SELECT
                        users.id,
                        users.vanity,
                        users.email,
                        users.unsub
                      FROM updates
                      LEFT JOIN artists ON updates.artist_id=artists.id
                      LEFT JOIN users ON artists.user_id=users.id
                      WHERE updates.id = :parent_id LIMIT 1";
                $sth = $this->DB->prepare($q);
                $sth->bindParam(":parent_id",$this->parentId);
                $sth->execute();
                $u = $sth->fetchAll();
                
                if(isset($u[0]) && ($u[0]['email']!='') && ($u[0]['unsub']==0)) {
                    $parentUser=$u[0];
                
                    $key = md5(USERSALT.$parentUser['id']);
                    $id = $parentUser['id'];
                    $artistVanity = $parentUser['vanity'];
                    require ROOT . '/_resources/classes/ses.php'; // SES
                    $ses = new SimpleEmailService('AKIAIT3EMAPMQE4UVHQA', 'G4jeurRskhQdZ6S9jp+6u7AK3przkxqrLBEtAle8');
        
    
        
                    $body = <<<EOT
A new comment has been posted on your artist profile:

"$this->comment"
posted by $vanity

To view your profile, click the following link:
http://www.muzooka.com/#!/$artistVanity/

Thanks,
The Muzooka Team
@muzooka

To stop receiving notifications for comments, please click the following link:
http://www.muzooka.com/settings/

EOT;
                    // Prepare SES
                    $m = new SimpleEmailServiceMessage();
                    $m->addTo($parentUser['email']);
                    $m->setFrom('info@muzooka.com');
                    $m->setSubject('Muzooka - New Comment Posted');
                    $m->setMessageFromString($body);
        
                    // Send the email
                    $ses->sendEmail($m);
                }
                
                
                
                
                $this->response['message'] = 'Successfully added new comment';
                $this->response['status'] = 200;
                $this->response['data'] = $data;
                return $this->response;
                exit;
                
            } catch(PDOException $e) {
                $this->response['message'] = 'Failed to create new comment';
                $this->response['status'] = 500;
                $this->response['data'] = $sth->errorInfo();
                return $this->response;
                exit;
            }
            
        } else {
            $this->response['message'] = 'Invalid Credentials';
            $this->response['status']  = 401;
            $this->response['data'] = array();
            return $this->response;
            exit;
        }
    }
}