<?php
class Profiles extends Element {
	function __construct($request) {
		parent::__construct($request);

		$this->dispatchArray['post'][false][null] = 'register';

		//Following functions are not used anywhere

		$this->dispatchArray['post'][false]['insert'] = 'insertUser';

		$this->dispatchArray['get'][false][null] = 'retrieveOwnProfile';
		$this->dispatchArray['get'][false]['return'] = 'reply';
		$this->dispatchArray['get'][false]['login'] = 'login';
		$this->dispatchArray['get'][true][null] = 'retrieveProfile';
		$this->dispatchArray['get'][false]['retrieveContacts'] = 'retrieveContacts';
		$this->dispatchArray['get'][false]['contactsDistance'] = 'contactsDistance';
		$this->dispatchArray['get'][false]['modifyProfile'] = 'modifyProfile';
		$this->dispatchArray['get'][false]['echo'] = 'echoMe';

		$this->dispatchArray['put'][false]['modifyProfile'] = 'modifyProfile';

		$this -> nebulaDB = new DB();
	}


	public function testpost() {
		if( !RestUtils::authentication() )
			RestUtils::error(401, "Authentication Error");
		$result = $this->request->getData();
		$response = array('res' => $result->test1);
		//$response = array('1' => array('z'=>'2','3','4'), 6 => array('a','b','c'));
		return new Response($status, $response);
	}

	public function retrieveProfile($data) {
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$userReq = $this -> request -> getID();
              	$q = $this -> nebulaDB -> query("SELECT username, domain, fullName, status,
						 phoneNumber, address, email_address 
						 FROM n_nebulauser WHERE id='$userReq'");
              	if( mysql_num_rows($q) > 0 ){			
                	$status = 200;
		        $result = mysql_fetch_assoc($q); 
		}
                return new Response($status, $result);	
	}

	public function modifyProfile($data) {
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");

		$fields = '';
		$values = '';
		$domain = 'nebula.com';
		$dat = array();
		foreach ($data as $field => $value) {
			//we don't allow the user to change username
			if($field!="username")
				$dat[$field] = $value;
		}
		//if the password is changed we need to remake the hash
		if(isset($dat['password']))
		{
              		$q = $this -> nebulaDB -> query("SELECT username
							 FROM n_nebulauser WHERE id='$userReq'");
			$dat['username'] = $q['username'];

			$dat['domain'] = $domain;
			$dat['ha1'] = md5($dat['username'] .':'. $domain .':'. $data['password']);
			$dat['ha1b'] = md5($dat['username'] .'@'. $domain .':'. $domain .':'. $dat['password']);
			//data is in the format field=>value, we retrieve it for db update 
		}

		/*foreach ($dat as $field => $value) {
			if ($field != "tableName")
			{
				$value = mysql_real_escape_string($value);
				$fields .= "$field = '$value',";
			}
 		}
		// remove ", " from $fields and $values
		$fields = preg_replace('/, $/', '', $fields);
		
		$fields = substr($fields,0,-1);
		// create sql statement
		$sql = "UPDATE n_nebulauser SET $fields
			WHERE id = $userID";
		echo($sql);
              	$q = $this -> nebulaDB -> query($sql);*/


		//v_subscriber

		$subscriber = array();
		$subscriber['username'] = $dat['username'];
		$subscriber['domain'] = $dat['domain'];
		$subscriber['password'] = $dat['password'];
		if(isset($dat['email_address']))
			$subscriber['email_address'] = $dat['email_address'];
		$subscriber['ha1'] = $dat['ha1'];
		$subscriber['ha1b'] = $dat['ha1b'];

		$this->nebulaDB->updateArray($subscriber, 'id', $userID, 'v_subscriber');

		//n_user

		$user = array();
		$user['id'] = $id;
		if(isset($dat['fullName']))
			$user['fullName'] = $dat['fullName'];
		if(isset($dat['status']))
			$user['status'] = $dat['status'];
		if(isset($dat['phoneNumber']))
			$user['phoneNumber'] = $dat['phoneNumber'];
		$user['sipURI'] = $dat['sipURI'];
		if(isset($dat['address']))
			$user['address'] = $dat['address'];

		$this->nebulaDB->updateArray($user, 'id', $userID, 'user');

			
//echo $sql;
		if(mysql_affected_rows()>0){
                	return new Response(200, "Profile modified");	
		}
                return new Response(500, "Unable to modify Profile");	
	}

	public function retrieveOwnProfile() {
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
              	$q = $this -> nebulaDB -> query("SELECT username, domain, fullName, status,
						 phoneNumber, address, email_address 
						 FROM n_nebulauser WHERE id='$userID'");
              	if( mysql_num_rows($q) > 0 ){			
                	$status = 200;
		        $result = mysql_fetch_assoc($q); 
		}
                return new Response($status, $result);	
	}

	public function test() {
		echo 'vatfairefoutre';
		$status = 200;
		$result = 'blabla';
		return new Response($status, $result);
	}
	
	public function echoMe($data) {
		$status = 200;
		$result = array($data['message']);
		return new Response($status, $result);
	}
	
	public function retrieveAll($data) {
		/*if( !RestUtils::authentication() )
			RestUtils::error(401, "Authentication Error");*/
              $user = mysql_real_escape_string($data['username']);
 
              $q = $this -> nebulaDB -> query("SELECT * from n_nebulauser WHERE username='$user'");
              if( mysql_num_rows($q) > 0 ){			
                         $status = 200;
		           $result = mysql_fetch_assoc($q); 
                         return new Response($status, $result);	
                 }	
		
	}

	public function retrieveContacts($data) {
		// Get the ID of the user from the autentication data
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
              
 	      $result = array();


		$sql = "SELECT n_nebulacontacts.contactUsername AS contactUsername,
						n_nebulauser.username AS username,
						 n_nebulauser.status AS userStatus,
						 n_groupcontact.groupID as groupID
						 FROM n_nebulacontacts
						LEFT JOIN n_nebulauser
						 ON n_nebulauser.id = n_nebulacontacts.contactID
						 LEFT JOIN n_groupcontact
						 ON n_groupcontact.userContactID = n_nebulacontacts.contactID
						 WHERE n_nebulacontacts.userID = $userID";
              $ind = $this -> nebulaDB -> query($sql);

		$status = 200;
		while($ris = mysql_fetch_assoc($ind)) 
			$result[] = $ris; 
			
		return new Response($status, $result);	
		
	}
	
	public function reply($data) {
		$status = 200;
		$result = $data['param'];
		return new Response($status, $result);
	}

	public function login($data) {
		$status = 401;
		$result = '';
		$username = mysql_real_escape_string($data['username']);
		$password = $data['password'];
		$domain = 'nebula.com';
		$ha1 = md5($username .':'. $domain .':'. $password);
		$ha1b = md5(username .'@'. $domain .':'. $domain .':'. $password);

		$q = $this -> nebulaDB -> query("SELECT * from n_nebulauser WHERE username='$user' AND ha1='$ha1' AND ha1b='$ha1b'");
              if( mysql_num_rows($q) > 0 ) {		
                $status = 200;
		  $result = mysql_fetch_assoc($q);
		}

                return new Response($status, $result);
	}

	public function register($data) {
		$status = 201;
		// define some vars
		$fields = '';
		$values = '';
		$domain = 'nebula.com';
		$username = $data['username'];
		$dat = array();
		foreach ($data as $field => $value) {
			$dat[$field] = $value;
		}
		$dat['domain'] = $domain;
		$dat['sipURI'] = $data['username'].'@'.$domain;
		$dat['ha1'] = md5($data['username'] .':'. $domain .':'. $data['password']);
		$dat['ha1b'] = md5($data['username'] .'@'. $domain .':'. $domain .':'. $data['password']);

		$q = $this -> nebulaDB -> query("SELECT id from n_nebulauser WHERE username='$username'");
		if( mysql_num_rows($q) > 0 )
			return new Response(400, 'Username '.$data['username'].' is already used.');

		//n_subscriber

		$subscriber = array();
		$subscriber['username'] = $dat['username'];
		$subscriber['domain'] = $dat['domain'];
		$subscriber['password'] = $dat['password'];
		if(isset($dat['email_address']))
			$subscriber['email_address'] = $dat['email_address'];
		$subscriber['ha1'] = $dat['ha1'];
		$subscriber['ha1b'] = $dat['ha1b'];

		$this->nebulaDB->insertArray($subscriber, 'v_subscriber');

		$id = $this->nebulaDB->id();
		$result = array('id' => $id);


		//n_user

		$user = array();
		$user['id'] = $id;
		if(isset($dat['fullName']))
			$user['fullName'] = $dat['fullName'];
		if(isset($dat['status']))
			$user['status'] = $dat['status'];
		if(isset($dat['phoneNumber']))
			$user['phoneNumber'] = $dat['phoneNumber'];
		$user['sipURI'] = $dat['sipURI'];
		if(isset($dat['address']))
			$user['address'] = $dat['address'];

		$this->nebulaDB->insertArray($user, 'user');

		return new Response($status, $result);
	}

}
?>
