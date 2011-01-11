<?php
class Conversations extends Element {
	function __construct($request) {
		parent::__construct($request);

		$this->dispatchArray['post'][false]['insert'] = 'insert';
		$this->dispatchArray['post'][false]['createSpatialConversation'] = 'createSpatialConversation';

		$this->dispatchArray['get'][false]['retrieveAll'] = 'retrieveAll';
//		$this->dispatchArray['get'][true]['updateTime'] = 'updateTime';
//		$this->dispatchArray['get'][false]['insert'] = 'insert';
		$this->dispatchArray['get'][false]['createSpatialConversation'] = 'createSpatialConversation';
		$this->dispatchArray['get'][false]['updatePosition'] = 'updatePosition';
		$this->dispatchArray['get'][false]['distanceFromContact'] = 'distanceFromContact';

		$this->dispatchArray['put'][true]['updateTime'] = 'updateTime';
		$this->dispatchArray['put'][false]['updatePosition'] = 'updatePosition';

		$this -> nebulaDB = new DB();
	}
	public function retrieveAll($data) {
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");

		$retrieveAll = "SELECT c.*
				FROM n_conversationuser cu INNER JOIN n_conversation c
				ON cu.conversationID = c.id
				WHERE userID = $userID
				ORDER BY date Desc
				LIMIT 3";


		$execAll = $this -> nebulaDB -> query($retrieveAll);
		if(mysql_num_rows($execAll)<=0)
		    return new Response(201, "No rows for this user");	
		$infos = array();
		while($ind = mysql_fetch_assoc($execAll)){
		    if(!isset($infos[$ind['thread']]))
			$infos[$ind['thread']]=array();
			$infos[$ind['thread']][$ind['conversation']]['id'] = $ind['id'];
			$infos[$ind['thread']][$ind['conversation']]['date'] = $ind['date'];
		    $currentID = $ind['id'];
		    $retrieveUsernames = "SELECT username
					 FROM n_conversationuser cu INNER JOIN n_nebulauser nu
					 ON cu.userID = nu.id
					 WHERE cu.conversationID = $currentID";

		    $execUsernames = $this -> nebulaDB -> query($retrieveUsernames);

		    while($fetchUsernames = mysql_fetch_assoc($execUsernames))
			$infos[$ind['thread']][$ind['conversation']]['callees'][] = $fetchUsernames;
		}
        return new Response(200, $infos);	
	}

	public function updateTime($data){
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$OK = 201;
		$error = 500;
		$conversationID = $this -> request -> getID();
		$update = $this -> nebulaDB -> query("UPDATE n_conversation 
						      SET date = NOW()
						      WHERE id = $conversationID");
		if(mysql_affected_rows()<=0)
		    return new Response($error, "Unable to update time");
		else{
		    $retrieve = "SELECT date
				 FROM n_conversation
				 WHERE id = $conversationID";
		    $execRetrieve = $this -> nebulaDB -> query($retrieve);
		    $fetchExec = mysql_fetch_assoc($execRetrieve);
		    $vector =array();
		    $vector['date'] = $fetchExec['date'];
		    return new Response($OK, $vector);
		}
		return new Response ($error, "Impossible to update time");
	}


	public function createSpatialConversation($data){
	    $userID = RestUtils::authentication();
	    if(!$userID)
    		RestUtils::error(401, "Authentication Error");
	    $OK = 201;
	    $ERROR = 201;
	    if(!isset($data['thread']) || !isset($data['conversation']) || !isset($data['distance']))
		return new Response ($ERROR, "Wrong parameters");
	    $distance = round($data['distance'],2);
	    $errorMessage = "Not valid value";
	    
	    if(($distance!=0.02)&&($distance!=0.05)&&($distance!=0.1)&&($distance!=0.25)&&($distance!=0.5)&&($distance!=1)&&($distance!=2)&&($distance!=5)&&($distance!=10)&&($distance!=20)&&($distance!=50)&&($distance!=100)&&($distance!=250)&&($distance!=500)){
		    return new Response($ERROR,$distance . " is not a ". $errorMessage);
	    }
	    
	    $query = "SELECT username, distance
		      FROM n_usertouser INNER JOIN n_nebulauser
		      WHERE (n_usertouser.contactID = n_nebulauser.id
		      AND ownerID = $userID)";
	    $execQuery = $this -> nebulaDB -> query($query);
	    if(mysql_num_rows($execQuery)<0)
		return new Response($ERROR, "Impossible to retrieve contacts distance");
	    if(mysql_num_rows($execQuery)==0)
		return new Response($OK, "No contacts to retrieve distance from");
	    $result = array();
	    $i = 1;
	    while($ind = mysql_fetch_assoc($execQuery)){
		if($ind['distance']<$distance){
		    $result['callee'.$i.'username'] = $ind['username'];
		    $i++;
		}
		
	}

	$result['calleeNumber'] = ($i-1);
	$result['thread'] = $data['thread'];
	$result['conversation'] = $data['conversation'];
	$this -> insert($result);
	$conversation = $data['conversation'];
	$thread = $data['thread'];
	$retrieve = "SELECT n_conversation.*, n_nebulauser.username FROM n_conversation
		     INNER JOIN n_conversationuser
		     ON n_conversation.id = n_conversationuser.conversationID
		     INNER JOIN n_nebulauser
		     ON n_nebulauser.id = n_conversationuser.userID
		     WHERE conversation = '$conversation' AND thread = '$thread'";
	$retrieveExec = $this -> nebulaDB -> query($retrieve);
	$i = 1;
	while($ind = mysql_fetch_assoc($retrieveExec)){
	    $return['callees'][] = $ind['username'];
	    $return['id'] = $ind['id'];
	    $return['date'] = $ind['date'];
	}
	$return['thread'] = $data['thread'];
	$return['conversation'] = $data['conversation'];
	return new Response($OK, $return);	
    }








	public function insert($data) {
		$userID = RestUtils::authentication();
		if(!$userID)
			RestUtils::error(401, "Authentication Error");
		$OK = 201;
		$error = 500;
		if((!isset($data['thread']))||
			(!isset($data['conversation']))||
			(!isset($data['calleeNumber'])))
		    return new Response($error, "The request is not providing all the parameters needed");	

		$thread = $data['thread'];
		$conversation = $data['conversation'];
		$insert = "INSERT INTO n_conversation  VALUES('',
								'$thread',
								'$conversation',
								NOW())";
		$this -> nebulaDB -> query($insert);
		$check = "SELECT id, date
			  FROM n_conversation
			  WHERE thread = '$thread'
			  AND conversation = '$conversation'";
		$execCheck = $this -> nebulaDB -> query($check);
		if(mysql_num_rows($execCheck)<=0)
		    return new Response($error, "Impossible to add the new conversation");	
		$fetchCheck = mysql_fetch_assoc($execCheck);
		$id = $fetchCheck['id'];
		$count = 0;
		$condition = "(";
		for($i = 0; $i < $data['calleeNumber']; $i++){
		    if(!isset($data['callee'.($i+1).'username']))
			return new Response($error, "Not enough callees passed despite the one announced");	
		    $callee = $data['callee'.($i+1).'username'];
		    $condition  .= "'".$callee."',"; 
		}
		$condition = substr($condition, 0, -1);
		$condition .= ")";
		$retrieveUsername = "SELECT id
				     FROM n_nebulauser
				     WHERE username IN $condition";
		$execRetrieve = $this -> nebulaDB -> query($retrieveUsername);
		//$count += mysql_num_rows($execRetrieve);
		while($ind = mysql_fetch_assoc($execRetrieve)){
		    $calleeID =  $ind['id'];
		    $insert = "INSERT INTO n_conversationuser VALUES ($id,
								       $calleeID)";
		    $execInsert = $this -> nebulaDB -> query($insert);
		    $count += mysql_affected_rows();
		}
		$check = "SELECT * FROM n_conversationuser
			  WHERE conversationID = $id
			  AND userID=$userID";
		$execCheck = $this -> nebulaDB -> query($check);
		if(mysql_num_rows($execCheck)==0){
		    $insertMe = "INSERT INTO n_conversationuser VALUES ($id,
		         						$userID)";
		    $execInsertMe = $this -> nebulaDB -> query($insertMe);
		}
		if($count == $data['calleeNumber']){
		    $vector = array();
		    $vector['id'] = $id;
		    $vector['date'] = $fetchCheck['date'];
		    return new Response($OK, $vector);	
		}
		return new Response($error, "inserted ". $count ." out of " .$data['calleeNumber']);	
	}



	function updateDistance($userID,$contactID){
	    //echo('$owner = ' .$userID. ' $contactID = '. $contactID. '\n');
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
		$a = mysql_affected_rows();
		return $a;
	}
	//update the GPS position with new coordinates
	public function updatePosition($data){
	    $userID = RestUtils::authentication();
	    if(!$userID)
    		RestUtils::error(401, "Authentication Error");
	    $OK = 201;
	    $ERROR = 500;
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
	    //if(mysql_affected_rows()<=0)
	//	return new Response($ERROR, "Unable to modify the position");
	    $execUpdate = $this-> nebulaDB -> query($updatePos);
	    $selectContact = "SELECT u.*
			      FROM n_usertouser u INNER JOIN n_nebulauser n
			      ON u.contactID =n.id
			      WHERE u.ownerID = $userID
			      AND n.x !=0
			      AND n.y !=0
			      AND n.z !=0";
	    $execSelect = $this -> nebulaDB -> query($selectContact);
	    $selectedRows = mysql_num_rows($execSelect);
	    $modifiedRows = 0;
	    while($ind = mysql_fetch_assoc($execSelect)){
		$contactID = $ind['contactID'];
		$modifiedRows += $this -> updateDistance($userID,$contactID);
	    }
	    $selectContact2 = "SELECT u.*
			      FROM n_usertouser u INNER JOIN n_nebulauser n
			      ON u.ownerID =n.id
			      WHERE u.contactID = $userID
			      AND n.x !=0
			      AND n.y !=0
			      AND n.z !=0";
	    $execSelect2 = $this -> nebulaDB -> query($selectContact2);
	    $selectedRows += mysql_num_rows($execSelect2);
	    while($ind = mysql_fetch_assoc($execSelect2)){
		$ownerID = $ind['ownerID'];
		$modifiedRows += $this -> updateDistance($ownerID,$userID);
	    }
		return new Response($OK, "Position modifed");
		//return new Response($ERROR, "Unable to update all the distance" . $modifiedRows ." out " .$selectedRows);
	}
	

	/*public function distanceFromContact(){
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
		if($ind['distance']<0.010)
		    $result['0.010'][] = $ind['username'];
		else if($ind['distance']<0.020)
		    $result['0.020'][] = $ind['username'];
		else if($ind['distance']<0.050)
		    $result['0.050'][] = $ind['username'];
		else if($ind['distance']<0.100)
		    $result['0.100'][] = $ind['username'];
		else if($ind['distance']<0.250)
		    $result['0.250'][] = $ind['username'];
		else if($ind['distance']<0.500)
		    $result['0.500'][] = $ind['username'];
		else if($ind['distance']<1)
		    $result['1'][] = $ind['username'];
		else if($ind['distance']<2)
		    $result['2'][] = $ind['username'];
		else if($ind['distance']<5)
		    $result['5'][] = $ind['username'];
		else if($ind['distance']<10)
		    $result['10'][] = $ind['username'];
		else if($ind['distance']<20)
		    $result['20'][] = $ind['username'];
		else if($ind['distance']<50)
		    $result['50'][] = $ind['username'];
		else if($ind['distance']<100)
		    $result['100'][] = $ind['username'];
		else if($ind['distance']<200)
		    $result['200'][] = $ind['username'];
		else if($ind['distance']<500)
		    $result['500'][] = $ind['username'];
	    }
	    return new Response(200, $result);
	}*/

	public function distanceFromContact(){
	    $userID = RestUtils::authentication();
	    if(!$userID)
    		RestUtils::error(401, "Authentication Error");
	    $OK = 200;
	    $EMPT = 201;
	    $ERRO = 500;
	    $query = "SELECT distance, username
		      FROM n_usertouser INNER JOIN n_nebulauser
		      WHERE ((n_usertouser.contactID = n_nebulauser.id
		      AND ownerID = $userID))
		      AND distance !=0";
	    $execQuery = $this -> nebulaDB -> query($query);
	    if(mysql_num_rows($execQuery)<0)
		return new Response($ERROR, "Impossible to retrieve contacts distance");
	    if(mysql_num_rows($execQuery)==0)
		return new Response($EMPT, "No contacts to retrieve distance from");
	    $result = array();
	    while($ind = mysql_fetch_assoc($execQuery)){
	/*	if($ind['distance']<0.010){
		    if(!isset($result['0.010']))
			$result['0.010'] = 0;
		    $result['0.010'] += 1;
		}
		else 
		*/
		if($ind['distance']<0.020){
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
		else if($ind['distance']<0.25){
		    if(!isset($result['0.250']))
			$result['0.250'] = 0;
		    $result['0.250'] += 1;
		}
		else if($ind['distance']<0.5){
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



}
?>
