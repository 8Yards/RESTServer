<?php
class Profiles extends Element {
	function __construct($request) {
		parent::__construct($request);
		$this->dispatchArray['get'][false][null] = 'retrieveOwnProfile';
//		$this->dispatchArray['get'][false][null] = 'retrieveAll';
		//$this->dispatchArray['get'][false][null] = 'test';
		$this->dispatchArray['get'][false]['return'] = 'reply';
		$this->dispatchArray['get'][false]['login'] = 'login';
		$this->dispatchArray['get'][true][null] = 'retrieveProfile';
		$this->dispatchArray['get'][false]['retrieveContacts'] = 'retrieveContacts';
		$this->dispatchArray['post'][false]['insert'] = 'insertUser';
		$this->dispatchArray['post'][false][null] = 'register';
		$this->dispatchArray['put'][false]['updatePosition'] = 'updatePosition';
		$this->dispatchArray['get'][false]['updatePosition'] = 'updatePosition';
		
		$this->dispatchArray['put'][false]['modifyProfile'] = 'modifyProfile';
		$this->dispatchArray['get'][false]['modifyProfile'] = 'modifyProfile';
		$this->dispatchArray['get'][false]['echo'] = 'echoMe';

		$this -> nebulaDB = new DB();
	}
	//update the GPS position with new coordinates
	public function updatePosition($data){
	    $userID = RestUtils::authentication();
	    if(!$userID)
    		RestUtils::error(401, "Authentication Error");
	    $OK = 200;
	    $ERROR = 200;
	    if((!isset($data['latitude']))||(!isset($data['longitude'])))
		return new Response($ERROR, "The parameters passed are not correct");
	    $earthRadius = 6367;
	    $theta = $data['longitude'];
	    $phi = 90 - abs($data['latitude']);
	    
	    $theta = ($theta*2*pi())/360;
	    $phi = ($phi*2*pi())/360;
	    $x = $earthRadius * (cos( $theta)) * (sin ($phi));
	    $y = $earthRadius * (sin ($theta)) * (sin ($phi));
	    $z = $earthRadius * (cos ($phi));

	    $updatePos = "UPDATE n_nebulauser SET x = $x,
						  y = $y,
						  z = $z
					      WHERE id = $userID";
	    echo($updatePos);
	    $execUpdate = $this-> nebulaDB -> query($updatePos);
	    $selectContact = "SELECT c.*
			      FROM n_nebulaoontact c INNER JOIN n_nebulauser n
			      WHERE c.ownerID = $userID
			      AND c.contactID =n.id
			      AND n.x IS NOT NULL
			      AND n.y IS NOT NULL
			      AND n.z IS NOT NULL";
	    $execSelect = $this -> nebulaDB -> query($selectContact);
	    while($ind = mysql_fetch_assoc($execSelect)){
		$contactID = $ind['contactID'];
		$updateQuery = "UPDATE n_usertouser SET distance = 
				(SELECT((SELECT ASIN((SELECT((SELECT SQRT( (
				SELECT sum( (SELECT POW( (SELECT (
				SELECT x
				FROM n_nebulauser
				WHERE id = $userID) - (
				SELECT x
				FROM n_nebulauser
				WHERE id =$contactID ) AS DIFF ) , 2 ) ) + (
				SELECT POW( (SELECT (SELECT y
				FROM n_nebulauser
				WHERE id =$userID
				) - (
				SELECT y
				FROM n_nebulauser
				WHERE id =$contactID ) AS DIFF ) , 2 )
				) + (
				SELECT POW( (SELECT (
				SELECT z
				FROM n_nebulauser
				WHERE id =$userID
				) - (
				SELECT z
				FROM n_nebulauser
				WHERE id =$contactID ) AS DIFF ) , 2 ))))))/(2*6371)))))*(2*6371)))
				WHERE ownerID=$userID and contactID=$contactID";
		$execUpdate = $this -> nebulaDB -> query($updateQuery);
	    }
	    if(mysql_affected_rows()<=0)
		return new Response($ERROR, "Unable to modify the position");
	    return new Response($OK, "Position modiifed");
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

		foreach ($dat as $field => $value) {
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
              	$q = $this -> nebulaDB -> query($sql);

			
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
		//data is in the format field=>value, we retrieve the data for db insertion
		foreach ($dat as $field => $value) {
			if ($field != "tableName")
			{
				$value = mysql_real_escape_string($value);
			
				$fields .= "$field, ";
				$values .= "'$value', ";
			}
		 }
		// remove ", " from $fields and $values
		$fields = preg_replace('/, $/', '', $fields);
		$values = preg_replace('/, $/', '', $values);
		$q = $this -> nebulaDB -> query("SELECT id from n_nebulauser WHERE username='$username'");
		if( mysql_num_rows($q) > 0 )
			return new Response(400, 'Username '.$data['username'].' is already used.');
		// create sql statement
		$sql = "INSERT INTO n_nebulauser ($fields) VALUES ($values)";
//echo $sql;
		$this -> nebulaDB -> query($sql);
		
		$result = array('id' => $this->nebulaDB->id());

		return new Response($status, $result);
	}
}
?>
