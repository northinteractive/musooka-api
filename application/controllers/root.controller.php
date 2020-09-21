<?php

class RootController {
	public function __construct() {
		$this->db = new DB();
		echo "You must define an API function";    
	}
}
