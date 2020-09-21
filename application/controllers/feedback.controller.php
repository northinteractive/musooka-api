<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';
require ROOT . '_resources/classes/ses.php';

class FeedbackController {
    public $update;
    public $httpObj;
    public $response;
    
    public function __construct() {
        
        // Process Request
        $this->httpObj = RestUtil::processRequest();
        $POST = $this->httpObj->getRequestVars();
        $DB = new DB();
        
        if(!isset($POST['feedbackBody']) || ($POST['user_id']=='')) {
            print_r($POST);
            $this->response['message'] = 'Some required fields were left empty. Please try again';
            $this->response['status'] = 500;
        } else if(strlen($POST['feedbackBody'])<64){
            
            $this->response['message'] = "Please add a few more details about your question (64 characters minimum)";
            $this->response['status'] = 404;
            
        } else {
            if(authorize::key($POST['user_id'],$POST['key'])) {
                
                if(isset($POST['user_id'])) {
                    $q = "SELECT email FROM users WHERE users.id=:id LIMIT 1";
                    $sth = $DB->prepare($q);
                    $sth->bindParam(":id",$POST['user_id']);
                    $sth->execute();
                    $a = $sth->fetchAll();
                    
                    $email = $a[0]['email'];
                    
                    $ses = new SimpleEmailService('AKIAIT3EMAPMQE4UVHQA', 'G4jeurRskhQdZ6S9jp+6u7AK3przkxqrLBEtAle8');
                    
                    $m = new SimpleEmailServiceMessage();
                    $m->addTo('jon@muzooka.com');
                    $m->setFrom('info@muzooka.com');
                    $m->setSubject('Muzooka - Feedback sent from '.$email);
                    $m->setMessageFromString($POST['feedbackBody']);
        
                    // Send the email
                    $success = $ses->sendEmail($m);
                    
                    if($success) {
                        $this->response['message'] = "Successfully sent feedback";
                        $this->response['status'] = 200;
                    } else {
                        $this->response['message'] = "Sorry, there was an error sending your feedback. Please try again later";
                        $this->response['status'] = 500;
                    }
                    
                } else {
                    $g = '';
                    $this->response['genre'] = array();
                    $this->response['message'] = "No user information passed";
                    $this->response['status'] = 404;
                }
                
            } else {
                $this->response['message'] = 'Invalid Credentials';
                $this->response['status']  = 401;
                $this->response['data'] = array();
            }
        }
        
        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }
}