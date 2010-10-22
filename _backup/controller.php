<?php
include('rest.class.php');

interface Element {
	public function find($id); //GET ?id=
	public function retrieveAll(); //GET
	public function delete($id); //DELETE ?id=
	public function update($id); //Update: POST/PUT ?id=
	public function save(); //Create: POST/PUT
}

include('Profiles.class.php');
include('Contacts.class.php');
include('Groups.class.php');

$request = RestUtils::processRequest();

//map http method to function
$methodsWithId = array(
	'get' => 'find',
	'delete' => 'delete',
	'post' => 'update',
	'put' => 'update'
);
$methodsWithoutId = array(
	'get' => 'retrieveAll',
	'post' => 'save',
	'put' => 'save'
);

if($request->getID() != '')
	$res = call_user_func( array($request->getElement(), $methodsWithId[$request->getMethod()]), $request->getID() );
else
	$res = call_user_func( array($request->getElement(), $methodsWithoutId[$request->getMethod()]) );
	
echo $res;
?>