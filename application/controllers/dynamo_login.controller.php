<?php

// Include utilities
require ROOT . 'application/utilities/validate.utility.php';

// Include Models
require ROOT . 'application/models/user.model.php';


class Dynamo_loginController {
	public $user;       // User Model
	public $httpObj;
	public $response;

	public function __construct() {

		// Create new local User Object
		$this->user = new User();

		// Process Request
		$this->httpObj = RestUtil::processRequest();

		// Get response from user model

		if(method_exists($this->user,"dynamo_login")) {
			$this->response = $this->user->dynamo_login($this->httpObj);
		} else {
			echo "Invalid Method";
		}


		// Return the response
		RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
	}
}