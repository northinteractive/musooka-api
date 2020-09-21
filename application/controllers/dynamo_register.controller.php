<?php

// Include utilities
require ROOT . 'application/utilities/validate.utility.php';

// Include Models
require ROOT . 'application/models/user.model.php';


class Dynamo_registerController {
	public $user;       // User Model
	public $rest;
	public $httpObj;
	public $registerResponse;

	public function __construct() {

		// Create new local User Object
		$this->user = new User();

		// Process Request
		$this->httpObj = RestUtil::processRequest();

		if(method_exists($this->user,"dynamo_register")) {
		                 $this->response = $this->user->dynamo_register($this->httpObj);
		} else {
			echo "Invalid Method";
		}
		// Return the response
		RestUtil::sendResponse($this->response['status'],json_encode($this->response),'application/json');
	}
}