<?php

class Contacts extends Element {
	function __construct($request) {
		parent::__construct($request);
		$this->dispatchArray['get'][false][null] = 'methodNotImplemented';
		$this->dispatchArray['get'][true][null] = 'retrieveGroup';
		$this->dispatchArray['get'][true]['retrieveGroupMembers'] = 'retrieveGroupMembers';
		$this->dispatchArray['post'][false][null] = 'methodNotImplemented';
		$this->dispatchArray['post'][true][null] = 'methodNotImplemented';
		$this->dispatchArray['post'][false]['addContact'] = 'addContact';
		$this->dispatchArray['put'][false][null] = 'methodNotImplemented';
		$this->dispatchArray['put'][true][null] = 'methodNotImplemented';
		$this->dispatchArray['delete'][false]['deleteContact'] = 'deleteContact';
		$this->dispatchArray['delete'][true]['deleteUserFromGroup'] = 'deleteUserFromGroup';
		
		$this -> nebulaDB = new DB();
		
	}

	public function methodNotImplemented() {
	
		if( !RestUtils::authentication() )
			RestUtils::error(401, "Authentication Error");

		$status = 200;
		$result = 'Method not implemented yet';
		return new Response($status, $result);
	}
	
	public function addContact($data) {
	
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");

		$contactID = $data['contactID'];
		$contactNickName = $data['nickName'];
		$sql = "INSERT INTO n_nebulacontacts VALUES ('',
													 '$userID',
													 '$contactID',
													 '$contactNickName')";

		//performs the add, checkings are performed in db class
		$query = $this -> nebulaDB -> query($sql);
		$status = 201;
		
		$result = array('id' => $this->nebulaDB->id());
		return new Response($status, $result);	
		
	}
	
	public function deleteContact($data) {
	
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");

		$contactID = $data['contactID'];
		$sql = "DELETE FROM n_nebulacontacts
				WHERE ownerID = '$userID'
				AND contactID = '$contactID'";

		//performs the elimination, checkings are performed in db class
		$query = $this -> nebulaDB -> query($sql);
		$status = 204;
		
		return new Response($status);	
		
	}

	
	public function addContactIntoGroup($data) {
	
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");

		$groupID = $data['groupID'];
		$userAddedID = $data['userAddedID'];

		$sql = "INSERT INTO n_groupusers VALUES ('',
												 '$groupID',
												 '$userAddedID')";

		//performs the add, checkings are performed in db class
		$query = $this -> nebulaDB -> query($sql);
		$status = 201;
		
		$result = array('id' => $this->nebulaDB->id());
		return new Response($status, $result);
		
	}
	
	public function deleteUserFromGroup($data) {
	
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");

		$groupID = $data['groupID'];
		$userDroppedID = $data['userDroppedID'];
		
		$sql = "DELETE FROM n_groupuser
				WHERE groupID = '$groupID'
				AND userID = '$userDroppedID'";

		//performs the elimination, checkings are performed in db class
		$query = $this -> nebulaDB -> query($sql);
		$status = 204;
		
		return new Response($status);
		
	}
	
	
}
?>
