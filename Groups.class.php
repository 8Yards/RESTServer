<?php
class Groups extends Element {
	function __construct($request) {
		parent::__construct($request);

		$this->dispatchArray['post'][false]['insertUserIntoGroup'] = 'insertUserIntoGroup';
		$this->dispatchArray['post'][false]['insertGroup'] = 'insertGroup';		
		$this->dispatchArray['post'][false]['addContact'] = 'addContact';

		//$this->dispatchArray['get'][false]['addContact'] = 'addContact';

		$this->dispatchArray['get'][false]['retrieveAllGroupsMembers'] = 'retrieveAllGroupsMembers';
		$this->dispatchArray['get'][false]['insertGroup'] = 'insertGroup';
		$this->dispatchArray['get'][false]['distanceFromContact'] = 'distanceFromContact';
		$this->dispatchArray['get'][true]['modifyGroup'] = 'modifyGroup';
		$this->dispatchArray['get'][true]['deleteContact'] = 'deleteContact';
		$this->dispatchArray['get'][true]['modifyContact'] = 'modifyContact';
		$this->dispatchArray['get'][true]['deleteGroup'] = 'deleteGroup';
		$this->dispatchArray['get'][false]['insertUserIntoGroup'] = 'insertUserIntoGroup';
		$this->dispatchArray['get'][true]['userToUser'] = 'userToUser';	#HERE
		$this->dispatchArray['get'][false]['deleteMultipleGroups'] = 'deleteMultipleGroups';	#HERE

		$this->dispatchArray['put'][true]['modifyGroup'] = 'modifyGroup';
		$this->dispatchArray['put'][true]['modifyContact'] = 'modifyContact';

		$this->dispatchArray['delete'][true]['deleteGroup'] = 'deleteGroup';
		$this->dispatchArray['delete'][true]['deleteContact'] = 'deleteContact';
		$this->dispatchArray['delete'][false]['deleteContactFromGroup'] = 'deleteContactFromGroup';	#HERE

		$this->dispatchArray['post'][true][null] = 'methodNotImplemented';
		$this->dispatchArray['get'][false][null] = 'methodNotImplemented';
		$this->dispatchArray['put'][true][null] = 'methodNotImplemented';
		$this->dispatchArray['delete'][false][null] = 'methodNotImplemented';
		$this->dispatchArray['delete'][true][null] = 'methodNotImplemented';
		
		$this -> nebulaDB = new DB();
	}

	public function methodNotImplemented() {
	
		if( !RestUtils::authentication() )
			RestUtils::error(401, "Authentication Error");

		$status = 200;
		$result = 'Method not implemented yet';
		return new Response($status, $result);
	}

	//create a new relation between current user and a specified one into table usertouser
	public function addContact($data) {
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");

		$contactUsername = $data['username'];
		$contactNickName = $data['nickname'];
		$retrieveID = "SELECT * FROM n_nebulauser WHERE username='$contactUsername'";

//die($retrieveID);

		$exec = $this->nebulaDB->query($retrieveID);

		if( mysql_num_rows($exec) <= 0 ){			
				return new Response(500, "User not existing");
		}

		$data = mysql_fetch_assoc($exec);
		$contactID = $data['id'];

		$sql = "SELECT * FROM n_usertouser WHERE ownerID=$userID AND contactID=$contactID";
		$q = $this->nebulaDB->query($sql);
		if( mysql_num_rows($q) <= 0 ){			
			$sql = "INSERT INTO n_usertouser (ownerID, contactID, contactNickname) VALUES ($userID, $contactID, '$contactNickName')";
			$this->nebulaDB->query($sql);
			
			return new Response(201);
		}
		else	
			return new Response(500, "Contact is already existing");
	}

	// Retrieve the ID of the relation between the user logged in and the one passed as parameter on the GET request
	public function userToUser($data) {
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$status = 200;
		$result = "";
		//get the information about the group required as argument
		$contactID = $this -> request -> getID();
		$query = $this -> nebulaDB -> query("SELECT * from n_usertouser WHERE ownerID=$userID AND  contactID=".$contactID);
		if( mysql_num_rows($query) > 0 ){			
			$status = 200;
			$result = mysql_fetch_assoc($query);
		}
		else{
			$status = 500;
		}
		return new Response($status, $result);	
	}


	public function modifyContact($data) {
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		
		$contactID = $this -> request -> getID();
		$check = "SELECT id FROM n_usertouser 
				WHERE contactID = $contactID AND ownerID = $userID";
		$checkPerform = $this -> nebulaDB -> query($check);
		if(mysql_num_rows($checkPerform)<=0){
        	return new Response(200, "You don't own such a contact");	
		}
		
		$assoc = mysql_fetch_assoc($checkPerform);
		$userToUserID = $assoc['id'];
		$nickModified = 0;
		if(isset($data['contactNickname'])){
			$nickname = $data['contactNickname'];
			
			$updateNick = "UPDATE n_usertouser SET contactNickname = '$nickname'
			WHERE contactId = $contactID AND ownerID = $userID";
			$updateNickPerform = $this -> nebulaDB -> query($updateNick);
			if(mysql_affected_rows()<0)
				return new Response(200, "Unable to modify the contact nickname. Group belongings not performed also");	
			//if we just want to modify the nick, after having done return
			if(!isset($data['groupsNumber']))
				return new Response(200, "Nickname modified");	
			$nickModified = 1;
		}
		//if we just want to modify the nick, after having done return
		if(!isset($data['groupsNumber']))
			return new Response(200, "Nothing to be done");	
		if($data['groupsNumber']==0){
		    $this -> nebulaDB -> query ("DELETE FROM n_groupuser
						 WHERE userToUserID = $userToUserID");
			return new Response(200, "Deletions performed");	
		}
		$groupsNumber = $data['groupsNumber'];
		for($i = 0; $i < $groupsNumber; $i++){
		    $groupID[$data['group'.($i+1).'ID']] = $data['group'.($i+1).'ID'];
		}
		$retGroupList = "SELECT * FROM n_groupuser 
				WHERE userToUserID = $userToUserID";
		$info = $this -> nebulaDB -> query($retGroupList);
		$present = array();
		$groupsDroppedFrom = 0;
		//check which groups have been dropped
		while($ris = mysql_fetch_assoc($info)){
			if(!isset($groupID[$ris['groupID']])){
				//remove from the group	
				$gID = $ris['groupID'];
				$call = $this -> nebulaDB -> query("DELETE FROM n_groupuser
						WHERE groupID = $gID
						AND userToUserID = $userToUserID");
				$groupsDroppedFrom += mysql_affected_rows();
			}
			$present[$ris['groupID']] = $ris['groupID'];
		}
		
		$groupsNumber = $data['groupsNumber'];
		$groupsAddedInto = 0;
        for($i = 0; $i < $groupsNumber; $i++){
        	//$groupID = $data['groupToAdd'.($i+1).'ID'];
			//$updateGroup = "INSERT INTO n_groupuser (userToUserID, groupID) VALUES ($userToUserID, $groupID)";
			//$updateGroupPerform = $this -> nebulaDB -> query($updateGroup);
			//$contactAdded += mysql_affected_rows();
			//echo($updateGroup);
			//check if the group has to be added
			if(!isset($present[$data['group'.($i+1).'ID']])){
				$gID = $data['group'.($i+1).'ID'];
				$checkGroup = "SELECT * FROM n_nebulagroup
					       WHERE ownerUserID = $userID
					       AND id = $gID";

				$checkGroupPerform = $this -> nebulaDB -> query($checkGroup);
				if(mysql_num_rows($checkGroupPerform)>0){
				    $updateGroup = "INSERT INTO n_groupuser (userToUserID, groupID) VALUES ($userToUserID, $gID)";
				    $updateGroupPerform = $this -> nebulaDB -> query($updateGroup);
				    $groupsAddedInto += mysql_affected_rows();
				}
			}
			$present[$ris['groupID']] = $ris['groupID'];
		}
		if($nickModified == 0)
			return new Response(200, "Inserted contact in ".$groupsAddedInto." groups and removed from ".$groupsDroppedFrom);
		if($nickModified == 1)
			return new Response(200, "Inserted contact in ".$groupsAddedInto." groups and removed from ".$groupsDroppedFrom. ". Nickname modified");	
     
	}

	



	public function distanceFromContact(){
	    $userID = RestUtils::authentication();
	    if(!$userID)
    		RestUtils::error(401, "Authentication Error");
	    $OK = 200;
	    $ERRO = 500;
	    $query = "SELECT distance, username
		      FROM n_usertouser INNER JOIN n_nebulauser
		      WHERE ((n_usertouser.contactID = n_nebulauser.id
		      AND ownerID = $userID)
		      OR (n_usertouser.ownerID = n_nebulauser.id
		      AND contactID = $userID))
		      AND distance !=0";
	    $execQuery = $this -> nebulaDB -> query($query);
	    if(mysql_num_rows($execQuery)<0)
		return new Response($ERROR, "Impossible to retrieve contacts distance");
	    if(mysql_num_rows($execQuery)==0)
		return new Response($OK, "No contacts to retrieve distance from");
	    $result = array();
	    while($ind = mysql_fetch_assoc($execQuery)){
		if($ind['distance']<0.010){
		    if(!isset($result['0.010']))
			$result['0.010'] = 0;
		    $result['0.010'] += 1;
		}
		else if($ind['distance']<0.020){
		    if(!isset($result['0.020']))
			$result['0.020'] = 0;
		    $result['0.020'] += 1;
		}
		else if($ind['distance']<0.050){
		    if(!isset($result['0.050']))
			$result['0.050'] = 0;
		    $result['0.050'] += 1;
		}
		else if($ind['distance']<0.100){
		    if(!isset($result['0.100']))
			$result['0.100'] = 0;
		    $result['0.100'] += 1;
		}
		else if($ind['distance']<0.250){
		    if(!isset($result['0.250']))
			$result['0.250'] = 0;
		    $result['0.250'] += 1;
		}
		else if($ind['distance']<0.500){
		    if(!isset($result['0.500']))
			$result['0.500'] = 0;
		    $result['0.500'] += 1;
		}
		else if($ind['distance']<1){
		    if(!isset($result['1']))
			$result['1'] = 0;
		    $result['1'] += 1;
		}
		else if($ind['distance']<2){
		    if(!isset($result['2']))
			$result['2'] = 0;
		    $result['2'] += 1;
		}
		else if($ind['distance']<5){
		    if(!isset($result['5']))
			$result['5'] = 0;
		    $result['5'] += 1;
		}
		else if($ind['distance']< 10){
		    if(!isset($result['10']))
			$result['10'] = 0;
		    $result['10'] += 1;
		}
		else if($ind['distance']<20){
		    if(!isset($result['20']))
			$result['20'] = 0;
		    $result['20'] += 1;
		}
		elseif($ind['distance']<50){
		    if(!isset($result['50']))
			$result['50'] = 0;
		    $result['50'] += 1;
		}
		else if($ind['distance']<100){
		    if(!isset($result['100']))
			$result['100'] = 0;
		    $result['100'] += 1;
		}
		else if($ind['distance']<250){
		    if(!isset($result['250']))
			$result['250'] = 0;
		    $result['250'] += 1;
		}
		else if($ind['distance']<500){
		    if(!isset($result['500']))
			$result['500'] = 0;
		    $result['500'] += 1;
		}
	    }

		return new Response($OK, $result);
	}

	public function modifyGroup($data) {
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$fields = '';
		$values = '';
		$dat = array();
		if(isset($data['groupName']))
		    $dat['groupName'] = $data['groupName'];
		
		if(isset($data['status']))
		    $dat['status'] = $data['status'];
		$groupID = $this -> request -> getID();
		$check = "SELECT * FROM n_nebulagroup 
				WHERE id = $groupID AND ownerUserID = $userID";
		$checkPerform = $this -> nebulaDB -> query($check);
		if(mysql_num_rows($checkPerform)<=0){
        		return new Response(200, "You don't own such a group");	
		}
        
		foreach ($dat as $field => $value) {
		    $value = mysql_real_escape_string($value);
		    $fields .= "$field = '$value' ,";
 		}

		// remove ", " from $fields and $values
		$fields = preg_replace('/, $/', '', $fields);
		$fields = substr($fields,0,-1);
		if(strlen($fields)>0){
		// modify group name and status 
		    $sql = "UPDATE n_nebulagroup SET $fields
				WHERE id = $groupID";
		    $q = $this -> nebulaDB -> query($sql);
		}
		//if it's not required to modiy the users that belong
		//to the group return
		if(!isset($data['membersNumber'])){
		    return new Response(200, "Modification performed");	
		}
		if($data['membersNumber'] == 0){
		    $this -> nebulaDB -> query ("DELETE FROM n_groupuser
						 WHERE groupID = $groupID");
			return new Response(200, "Deletion performed");	
		}
		//fetching the members from $data	
	        $membersID = array();		
		$membersNumber = $data['membersNumber'];
		for($i = 0; $i < $membersNumber; $i++){
		    $membersID[$data['groupMember'.($i+1).'ID']] = $data['groupMember'.($i+1).'ID'];
		}
		$retMembersList = "SELECT * FROM n_groupuser INNER JOIN n_usertouser
				 ON n_groupuser.userToUserID = n_usertouser.id
				WHERE n_groupuser.groupID = $groupID";
		$info = $this -> nebulaDB -> query($retMembersList);
		$totalBefore = mysql_num_rows($info);
		$present = array();
		$membersDropped = 0;
		//check which contacts have been dropped
		while($ris = mysql_fetch_assoc($info)){
			if(!isset($membersID[$ris['contactID']])){
				//remove from the group	
				$gID = $ris['contactID'];
				$userToUserID = $ris['userToUserID'];
				$call = $this -> nebulaDB -> query("DELETE FROM n_groupuser
						WHERE groupID = $groupID
						AND userToUserID = $userToUserID");
				$membersDropped += mysql_affected_rows();
			}
			$present[$ris['contactID']] = $ris['userToUserID'];
		}
		//contacts to be added	
		$insertedMembers = 0;
		for($i = 0; $i < $membersNumber; $i++){
			$member = $data['groupMember'.($i+1).'ID'];
			if(!isset($present[$member])){
				$retrieveRelSql = "SELECT id FROM n_usertouser
						  WHERE ownerID = $userID AND
						  contactID = $member";
				$exec = $this -> nebulaDB -> query($retrieveRelSql);
				if(mysql_num_rows($exec)>0){
				    $fetched = mysql_fetch_assoc($exec);
				    $userToUserID = $fetched['id'];
				    $updateGroup = "INSERT INTO n_groupuser (userToUserID, groupID) VALUES ($userToUserID, $groupID)";
				    $updateGroupPerform = $this -> nebulaDB -> query($updateGroup);
				    $insertedMembers += mysql_affected_rows();
				}
			}
		}

		$retMembers = "SELECT * FROM n_groupuser INNER JOIN n_usertouser
				 ON n_groupuser.userToUserID = n_usertouser.id
				WHERE n_groupuser.groupID = $groupID";
		$information = $this -> nebulaDB -> query($retMembersList);
		$totalAfter = mysql_num_rows($info);
		if($totalAfter = $totalBefore + $insertedMembers - $membersDropped)
		    return new Response(200, "All operation done successfully");
		else
		    return new Response(200, "Impossible to perform all the operations required");
	}
	
	public function retrieveGroups($data) {
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$status = 200;
		//get the information about the group required as argument
		
		$query = $this -> nebulaDB -> query("SELECT * from n_nebulagroup WHERE ownerUserID=".$userID);
		if( mysql_num_rows($query) > 0 ){			
			$status = 200;
			while($c = mysql_fetch_assoc($query)){
				$result[] = $c;
			}
		}
		else{
			$status = 500;
			
		}
		
		return new Response($status, $result);	
	}
	
	public function retrieveGroup($data) {
	
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$status = 200;
		$result = "";
		//get the information about the group required as argument
		$groupID = $this -> request -> getID();
		
		$query = $this -> nebulaDB -> query("SELECT * from n_nebulagroup WHERE id=$groupID AND  ownerUserID=".$userID);
		if( mysql_num_rows($query) > 0 ){			
			$status = 200;
			$result = mysql_fetch_assoc($query);
		}
		else{
			$status = 500;
			
		}
		
		return new Response($status, $result);	
		
	}
	
	public function deleteGroup($data) {
	
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$status = 500;
		$result = "";
		
		//get the information about the group required as argument
		$groupID = $this -> request -> getID();
		$query = $this -> nebulaDB -> query("SELECT * from n_nebulagroup WHERE id=$groupID AND ownerUserID=$userID");
		if( mysql_num_rows($query) > 0 ){			
			
			$fetch = mysql_fetch_assoc($query);
			//if the user owns the group the dropping is performed,
			
			
				$call = $this -> nebulaDB -> query("DELETE FROM n_nebulagroup
						WHERE id = $groupID
						AND ownerUserID = $userID");
				
			if( mysql_affected_rows() > 0 ){			
					$remove = "DELETE FROM n_groupuser
						   WHERE groupID = '$groupID'";
					$removeAll = $this -> nebulaDB -> query($remove);
					$check = "SELECT id FROM n_groupuser
						  WHERE groupID = '$groupID'";
					$safety = $this -> nebulaDB -> query($check);
					if( mysql_num_rows($safety) == 0 ){			
						$status = 200;
						return new Response($status, "Group dropped correctly");	
					}
					else
						return new Response($status, "Group has been dropped, but its contact not");	
			
		}
		else{
			$status = 500;
			return new Response($status,"Impossible to drop the group");
		}
	}
			$status = 500;
			return new Response($status,"The group doesn't exist or it does not belong to you");
	}

	public function deleteContact($data) {
	
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$status = 200;
		$result = "";
		
		//get the information about the contact required as argument
		$contactID = $this -> request -> getID();
		$query = $this -> nebulaDB -> query("SELECT id from n_usertouser WHERE contactID=$contactID AND ownerID=$userID");
		if( mysql_num_rows($query) > 0 ){	
			$fetch = mysql_fetch_assoc($query);
			$id = $fetch['id'];
				$sql = "DELETE FROM n_usertouser
				WHERE id=$id"; 
				$number = $this -> nebulaDB -> query($sql);
				if( mysql_affected_rows() > 0 ){	
					$count = $this -> nebulaDB -> query("SELECT id FROM n_groupuser
						  			    WHERE userToUserID = $id");
					if( mysql_num_rows($count) > 0 )	
						return new Response($status, "Contact dropped correctly, but the groups belongings are still there");	
					return new Response($status, "Contact dropped correctly with all the groups belongings");	
			}
			return new Response($status,"Unable to drop the contact");
		}
		return new Response($status,"You don't have such a contact");
	}

	public function deleteContactFromGroup($data) {
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$status = 200;
		$result = "";
		$groupID = $data['groupID'];
		$contactID = $data['contactID'];
		//echo($contactID);
		//get the information about the contact required as argument
		$cmd = "SELECT n_groupuser.id FROM n_usertouser INNER JOIN n_groupuser ON n_groupuser.userToUserID = n_usertouser.id WHERE n_groupuser.groupID=$groupID AND  n_usertouser.contactID=$contactID AND n_usertouser.ownerID=$userID"; 
		$query = $this -> nebulaDB -> query($cmd);
		if( mysql_num_rows($query) > 0 ){	
		$fetch = mysql_fetch_assoc($query);
		$userToUserID = $fetch['id'];		
			$sql = "DELETE FROM n_groupuser
				WHERE id = $userToUserID";

			$exec = $this -> nebulaDB -> query($sql);
			if( mysql_num_rows($query) > 0 ){	
				$status = 200;
				return new Response($status, "Contact dropped correctly from the group");	
			}
			return new Response($status,"Impossible to perform such an operation");
		}
		else
		{
			return new Response($status,"You don't have such a contact in the group specified");
		}
		return new Response($status,"Impossible to perform such an operation");
		
	}
	/*public function deleteMultipleGroups($data) {
	
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$status = 500;
		$result = "";
		$number = $data['number'];
		$sqlString = "";
		//get the information about the group required as argument
		for($i=0; $i < $number-1; $i++)
		{
			if(!isset($data[$i]))
			{
				$status = 500;
				return new Response($status,"Less groups than the ones claimed are passed");
			}
				
			$sqlString .= $data[$i]. ",";
		}
		if(!isset($data[$number]))
		{
			$status = 500;
			return new Response($status,"Less groups than the ones claimed are passed");
		}
		$sqlString .= $data[$number-1];
		
		$query = $this -> nebulaDB -> query("SELECT * from n_nebulagroup WHERE id IN ($sqlString)");
		
		if( mysql_num_rows($query) > 0 ){			
			
			
			//if the user owns the group the dropping is performed,
			while($ris = mysql_fetch_assoc($query)){
				if($ris['ownerUserID'] != $userID)
					return new Response(200,"The group doesn't belong to you.");
			}
			$sql = "DELETE FROM n_nebulagroup
					WHERE id IN ($sqlString)
					AND ownerUserID = '$userID'";
			$query = $this -> nebulaDB -> query($sql);
			$status = 200;
			
		}
		
		else{
			$status = 200;
			return new Response($status,"Some of the groups specified don't exist");
		}
		
		return new Response($status, "Groups dropped correctly");	
		
	}*/
	
	public function insertUserIntoGroup($data) {
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$status = 201;
		$contactUsername = $data['username'];
		$groupID = $data['groupID'];
		$userToUserIDSql = "SELECT n_usertouser.id FROM n_usertouser INNER JOIN n_nebulauser
							ON n_usertouser.contactID = n_nebulauser.id
							WHERE ownerID = $userID
							AND n_nebulauser.username = '$contactUsername'";
		
		$run = $this -> nebulaDB -> query($userToUserIDSql);
		$data = mysql_fetch_assoc($run);
		$userToUserID = $data['id'];
		//return new Response(501);

		$sql = "SELECT id FROM n_groupuser WHERE userToUserID=$userToUserID AND groupID=$groupID";
		$q = $this -> nebulaDB -> query($sql);
		if(mysql_num_rows($q) > 0) {
			return new Response(500, 'Contact already exists in that group');
		}

		$sql = "INSERT INTO n_groupuser (userToUserID, groupID) VALUES ($userToUserID, $groupID )";
//echo $sql;
		
		$this -> nebulaDB -> query($sql);
		if(mysql_affected_rows()>0)
		    return new Response($status, "Data inserted correctly");
		else
		    return new Response(500, "Data not inserted");
		
	}
	
	public function insertGroup($data) {
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$status = 201;
		// define some vars
		$fields = '';
		$values = '';
		$domain = 'nebula.com';

		$dat = array();
		foreach ($data as $field => $value) {
			$dat[$field] = $value;
		}
		$dat['ownerUserID'] = $userID;
		$dat['groupName'] = $data['groupName'];
		$groupName = $data['groupName'];
		$search = ("SELECT * FROM n_nebulagroup 
					WHERE ownerUserID = $userID 
					AND groupName = '$groupName'");
		$call = $this -> nebulaDB -> query($search);
		if( mysql_num_rows($call) > 0 ){
			
			return new Response(500, "Group name already taken. Try another one");
		}
		
		$dat['status'] = $data['status'];
		
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
		
		$sql = "INSERT INTO n_nebulagroup ($fields) VALUES ($values)";
//echo $sql;
		//return new Response(200,$sql);
		$this -> nebulaDB -> query($sql);
		//retrieve the id inserted
		$retrieveID = $this -> nebulaDB -> query($search);
		$newAssoc = mysql_fetch_assoc($retrieveID);
		$newID = $newAssoc['id'];
		//substituted at the place of the line below, that can
		//lead to race condition
		$result = array('id' => $this->nebulaDB->id());

		return new Response($status, $result);
	}
	
	public function retrieveGroupMembers($data) {
	
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$status = 500;
		$result = "";
		//get the information about the group required as argument
		$groupID = $this -> request -> getID();
		/*$sql = "SELECT n_nebulauser.username, n_nebulauser.domain, 
		        n_nebulauser.fullName, n_nebulauser.status, n_nebulauser.phoneNumber, 
    	        n_nebulauser.address, n_nebulauser.email_address \n"
    			. " FROM n_nebulagroup INNER JOIN n_groupuser\n"
   				. " ON n_groupuser.groupID = n_nebulagroup.groupID\n"
				. " INNER JOIN n_nebulauser\n"
				. " ON n_nebulauser.id = n_groupuser.userID\n"
				. " WHERE n_nebulagroup.groupID='$groupID' ";*/

		$sql = "SELECT n_nebulauser.username, n_nebulauser.domain, 
		        n_nebulauser.fullName, n_nebulauser.status, n_nebulauser.phoneNumber, 
    	        n_nebulauser.address, n_nebulauser.email_address \n"
    			. " FROM n_nebulagroup INNER JOIN n_groupuser\n"
   				. " ON n_groupuser.groupID = n_nebulagroup.id\n"
   				. " INNER JOIN n_usertouser\n"
   				. " ON n_groupuser.userToUserID = n_usertouser.id\n"
				. " INNER JOIN n_nebulauser\n"
				. " ON n_nebulauser.id = n_groupuser.id\n"
				. " WHERE n_nebulagroup.id='$groupID' ";
				
		$query = $this -> nebulaDB -> query($sql);
		if( mysql_num_rows($query) > 0 ){			
			$status = 200;
			$result = array();
			//if the user owns the group the data are sent back,
			//otherwise we'll send an error message
			$checkUser = $this -> nebulaDB -> query("SELECT * from n_nebulagroup WHERE id='$groupID'");
			$fetch = mysql_fetch_assoc($checkUser);
			if($fetch['ownerUserID'] == $userID)
				while($ris = mysql_fetch_assoc($query)) 
					$result[] = $ris; 
			else{
				$status = 200;
				$result =$groupID;
			}
		}
		else{
			$status = 200;
			$result ="No rows selected2";
		}
	
		return new Response($status, $result);	
		
	}

	public function retrieveAllGroupsMembers($data) {
	
		// Get the ID of the user from the autentication data and return 
		//an error message if it's not authenticated
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$status = 200;
		$result = "";
		//retrive groups members information
		$sql = "SELECT n_nebulauser.id, n_nebulauser.username, n_nebulauser.domain, n_nebulauser.fullName, n_nebulauser.status, n_nebulauser.phoneNumber, n_nebulauser.address, n_nebulauser.email_address, n_nebulagroup.groupName FROM n_nebulagroup LEFT OUTER JOIN n_groupuser ON n_groupuser.groupID = n_nebulagroup.id LEFT OUTER JOIN n_usertouser ON n_groupuser.userToUserID = n_usertouser.id LEFT OUTER JOIN n_nebulauser ON n_nebulauser.id = n_usertouser.contactID WHERE n_nebulagroup.ownerUserID=$userID UNION ALL SELECT n_nebulauser.id, n_nebulauser.username, n_nebulauser.domain, n_nebulauser.fullName, n_nebulauser.status, n_nebulauser.phoneNumber, n_nebulauser.address, n_nebulauser.email_address, 'ungrouped' as groupName FROM n_nebulauser INNER JOIN n_usertouser ON n_nebulauser.id = n_usertouser.contactID where n_usertouser.ID not in (SELECT userToUserID from n_groupuser) and n_usertouser.ownerId=$userID";
		$query = $this -> nebulaDB -> query($sql);
		//retrieve group information
		$groupInfo = "SELECT n_nebulagroup.id, n_nebulagroup.groupName, n_nebulagroup.status
				 	  FROM n_usertouser RIGHT OUTER JOIN n_groupuser
				 	  ON n_groupuser.userToUserID = n_usertouser.id
					  RIGHT OUTER JOIN n_nebulagroup
				 	  ON n_nebulagroup.id = n_groupuser.groupID
				 	  WHERE n_nebulagroup.ownerUserID =  $userID";

		$ask = $this -> nebulaDB -> query($groupInfo);


		$infos = array();
		while($info = mysql_fetch_assoc($ask)) {
			$infos[$info['groupName']] = array();
			$infos[$info['groupName']]['id'] = $info['id'];
			$infos[$info['groupName']]['status'] = $info['status'];
		} 	  

		while($ris = mysql_fetch_assoc($query)) 
		{
			if(!isset($ris['username']) || $ris['username']==null || $ris['username']=="")
				$result[$ris["groupName"]] = "";
			else
				$result[$ris["groupName"]][] = $ris;
			//$result[$ris["groupName"]]["id"] = $info["id"];
			//$result[$ris["groupName"]]["status"] = $info["status"];
			if($ris["groupName"] != 'ungrouped') {
				$result[$ris["groupName"]]["id"] = $infos[$ris["groupName"]]['id'];
				$result[$ris["groupName"]]["status"] = $infos[$ris["groupName"]]['status'];			
			}
			else {
				$result[$ris["groupName"]]["id"] = 0;
				$result[$ris["groupName"]]["status"] = 'Available';	
			}
		}
		return new Response($status, $result);	
		
	}
	
	
	

	public function find($id) {return '';}
	public function retrieveAll() {return '';}
	public function delete($id) {return '';}
	public function save($id='') {return '';}
}
?>
