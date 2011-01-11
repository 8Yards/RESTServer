<?php
//print_r($_GET);
//exit();

include('rest.class.php');

ini_set('error_reporting', E_ALL);
set_error_handler('myErrorHandler');

function myErrorHandler($errno, $errstr, $errfile, $errline) {
	$report = "Error Number: $errno<br/>
	Error: $errstr<br/>
	File: $errfile<br/>
	Line: $errline";

	RestUtils::error(500, $report);
}

include('db.class.php');
include('Resource.class.php');
include('System.class.php');
include('Profiles.class.php');
include('Contacts.class.php');
include('Groups.class.php');
include('Conversations.class.php');
$request = RestUtils::processRequest();
switch( strtolower( $request->getElement() ) ) {
	case 'restsystem':
		$element = new System($request);
		break;
	case 'restprofiles':
		$element = new Profiles($request);
		break;
	case 'restgroups':
		$element = new Groups($request);
		break;
	case 'restcontacts':
		$element = new Contacts($request);
		break;
	case 'restconversations':
		$element = new Conversations($request);
		break;
}

$response = $element->dispatcher($request);
if( $response === false )
	RestUtils::error(501);

if( strpos($request->getHttpAccept(), 'xml') )
	$type = 'xml';
else
	$type = 'json';
RestUtils::sendResponse( $response->getStatus(), $response->getBody(), $type );
?>
