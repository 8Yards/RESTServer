<?php

ini_set('error_reporting', E_ALL);
set_error_handler("myErrorHandler");

function report($report='') {
	RestUtils::sendResponse(500, $report);
	exit();
}

function myErrorHandler($errno, $errstr, $errfile, $errline) {
	$report = "Error Number: $errno<br/>
	Error: $errstr<br/>
	File: $errfile<br/>
	Line: $errline";

	report($report);
}
?>