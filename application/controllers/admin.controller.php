<?php

// Include utilities
require ROOT . 'application/utilities/auth.utility.php';

class AdminController {
    public $httpObj;
    public $response;

    public function __construct() {
        $this->httpObj = RestUtil::processRequest();
        $POST = $this->httpObj->getRequestVars();

        $this->DB = new DB();
        $this->response = array();

        if(isset($POST['adminKey']) && ($POST['adminKey']==md5(hash("sha256","13451j891hf8192eif80384189438f01038enf10834h8hf1083he08fh")))) {

	switch($POST['mtype']) {
		case "updates":
			$success = $this->delete_update($POST);
			break;
		case "songs":
			$success = $this->delete_song($POST);
			break;
		case "accounts":
			$success = $this->delete_account($POST);
			break;
		default:
			$success['status'] = 404;
			$success['message'] = "No method definied, please try again";
			break;
	}

	$this->response['status']=$success['status'];
	$this->response['message']=$success['message'];
	$this->response['data'] = array();

        } else {
	$this->response['status']=500;
	$this->response['message']='Invalid admin key';
	$this->response['data'] = array();
        }

        // Return the response
        RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
    }

    private function delete_update($p) {
	$q = "DELETE FROM updates WHERE id=:id LIMIT 1";
	$sth = $this->DB->prepare($q);
	$sth->bindParam(":id",$p['id']);
	if($sth->execute()) {
		$response['status'] = 200;
		$response['message'] = "Successfully removed this update";
	} else {
		$response['status'] = 500;
		$response['message'] = "There was an error";
		$response['data'] = $sth->errorInfo();
	}
	return $response;
    }

    private function delete_song($p) {

	$q = "DELETE FROM songs WHERE id=:id LIMIT 1";
	$sth = $this->DB->prepare($q);
	$sth->bindParam(":id",$p['id']);
	if($sth->execute()) {
		$response['status'] = 200;
		$response['message'] = "Successfully removed this song";
	} else {
		$response['status'] = 500;
		$response['message'] = "There was an error";
		$response['data'] = $sth->errorInfo();
	}

	return $response;
    }

    private function delete_account($p) {
	$q = "DELETE FROM users WHERE id=:id LIMIT 1";
	$sth = $this->DB->prepare($q);
	$sth->bindParam(":id",$p['id']);
	if($sth->execute()) {
		$response['status'] = 200;
		$response['message'] = "Successfully removed this user account";
	} else {
		$response['status'] = 500;
		$response['message'] = "There was an error";
		$response['data'] = $sth->errorInfo();
	}
	return $response;
    }

}