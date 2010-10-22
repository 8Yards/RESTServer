<?php
class RestUtils {

	public static function authentication() {
		function http_digest_parse($txt)
		{
			// protect against missing data
			$needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
			$data = array();
			$keys = implode('|', array_keys($needed_parts));

			preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);

			foreach ($matches as $m) {
				$data[$m[1]] = $m[3] ? $m[3] : $m[4];
				unset($needed_parts[$m[1]]);
			}

			return $needed_parts ? false : $data;
		}
		//return true;
		
		//TODO authentication
		$realm = 'NebulaREST';
		
		$users = array('nebula' => 'nebula');

		if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
			header('HTTP/1.1 401 Unauthorized');
			header('WWW-Authenticate: Digest realm="'.$realm.'",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');

			return false;
		}

		// analyze the PHP_AUTH_DIGEST variable
		if (!($data = http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) ||
			!isset($users[$data['username']]))
			//Database connection, retrieve row for $data['username']
			return false;

		// generate the valid response
		
		$A1 = md5($data['username'] . ':' . $realm . ':' . $users[$data['username']]);
		$A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
		$valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

		if ($data['response'] != $valid_response)
			return false;
			
		return true;
	}

	public static function processRequest() {
		if( !RestUtils::authentication() )
			RestUtils::error(401);
	
		// get our verb
		$request_method = strtolower($_SERVER['REQUEST_METHOD']);
		$element = $_GET['REST_element'];
		$id = null;
		$operation = null;
		if(isset($_GET['REST_id']))
			$id = $_GET['REST_id'];
			
		if(isset($_GET['REST_operation']))
			$operation = $_GET['REST_operation'];
			
		if(isset($_GET['REST_format']))
			$operation = $_GET['REST_format'];
			
		$return_obj		= new RestRequest();
		// we'll store our data here
		$data			= array();

		//TO recode
		switch ($request_method) {
			// gets are easy...
			//already got element and id
			case 'get':
				$data = $_GET;
				unset($data['REST_element']);
				unset($data['REST_id']);
				unset($data['REST_operation']);
				unset($data['REST_format']);
				break;
			// so are posts
			case 'post':
				$data = $_POST;
				break;
			// here's the tricky bit...
			case 'put':
				// basically, we read a string from PHP's special input location,
				// and then parse it out into an array via parse_str... per the PHP docs:
				// Parses str  as if it were the query string passed via a URL and sets
				// variables in the current scope.
				parse_str(file_get_contents('php://input'), $put_vars);
				$data = $put_vars;
				break;
		}

		// store the data
		$return_obj->setData($data);

		// store the method
		$return_obj->setMethod($request_method);

		// store the method
		$return_obj->setOperation($operation);

		// store the element
		$return_obj->setElement($element);

		// store the id
		$return_obj->setID($id);

		// set the raw data, so we can access it if needed (there may be
		// other pieces to your requests)
		$return_obj->setRequestVars($data);

		if(isset($data['data'])) {
			// translate the JSON to an Object for use however you want
			$return_obj->setData(json_decode($data['data']));
		}
		return $return_obj;
	}

	public static function getStatusCodeMessage($status) {
		// these could be stored in a .ini file and loaded
		// via parse_ini_file()... however, this will suffice
		// for an example
		$codes = Array(
		    100 => 'Continue',
		    101 => 'Switching Protocols',
		    200 => 'OK',
		    201 => 'Created',
		    202 => 'Accepted',
		    203 => 'Non-Authoritative Information',
		    204 => 'No Content',
		    205 => 'Reset Content',
		    206 => 'Partial Content',
		    300 => 'Multiple Choices',
		    301 => 'Moved Permanently',
		    302 => 'Found',
		    303 => 'See Other',
		    304 => 'Not Modified',
		    305 => 'Use Proxy',
		    306 => '(Unused)',
		    307 => 'Temporary Redirect',
		    400 => 'Bad Request',
		    401 => 'Unauthorized',
		    402 => 'Payment Required',
		    403 => 'Forbidden',
		    404 => 'Not Found',
		    405 => 'Method Not Allowed',
		    406 => 'Not Acceptable',
		    407 => 'Proxy Authentication Required',
		    408 => 'Request Timeout',
		    409 => 'Conflict',
		    410 => 'Gone',
		    411 => 'Length Required',
		    412 => 'Precondition Failed',
		    413 => 'Request Entity Too Large',
		    414 => 'Request-URI Too Long',
		    415 => 'Unsupported Media Type',
		    416 => 'Requested Range Not Satisfiable',
		    417 => 'Expectation Failed',
		    500 => 'Internal Server Error',
		    501 => 'Not Implemented',
		    502 => 'Bad Gateway',
		    503 => 'Service Unavailable',
		    504 => 'Gateway Timeout',
		    505 => 'HTTP Version Not Supported'
		);

		return (isset($codes[$status])) ? $codes[$status] : '';
	}

	public static function sendResponse($status = 200, $body = '', $type = 'application/json') {
		$status_header = 'HTTP/1.1 ' . $status . ' ' . RestUtils::getStatusCodeMessage($status);
		// set the status
		header($status_header);
		// set the content type
		header('Content-type: '.$type);

		// pages with body are easy
		if($body != '') {
			// send the body
			if($type == 'application/json')
				$body = json_encode($body);
		}
		// we need to create the body if none is passed
		else {
			// create some body messages
			$body = '';

			// this is purely optional, but makes the pages a little nicer to read
			// for your users.  Since you won't likely send a lot of different status codes,
			// this also shouldn't be too ponderous to maintain
			switch($status) {
				case 401:
					$body = 'You must be authorized to view this page.';
					break;
				case 404:
					$body = 'The requested URL ' . $_SERVER['REQUEST_URI'] . ' was not found.';
					break;
				case 500:
					$body = 'The server encountered an error processing your request.';
					break;
				case 501:
					$body = 'The requested method is not implemented.';
					break;
				//TODO., more status? default?
				default:
					RestUtils::error();
			}
		}
		
		echo $body;
	}
	
	public static function error($status='', $body='') {
		if($status == '')
			$status = 500;
		
		//DEBUG Mode
		RestUtils::sendResponse($status, $body, $type='text/html');
		//Production Mode
		//RestUtils::sendResponse(500);
		
		exit();
	}
}

class RestRequest {
	private $request_vars;
	private $data;
	private $http_accept;
	private $method;
	private $element;
	private $id;

	public function __construct() {
		$this->request_vars		= array();
		$this->data			= '';
		$this->element			= '';
		$this->id				= '';
		$this->http_accept		= 'json';//(strpos($_SERVER['HTTP_ACCEPT'], 'json')) ? 'json' : 'xml';
		$this->method			= 'get';
	}

	//Setters
	public function setData($data) { $this->data = $data; }
	public function setMethod($method) { $this->method = $method; }
	public function setOperation($operation) { $this->operation = $operation; }
	public function setElement($element) { $this->element = $element; }
	public function setID($id) { $this->id = $id; }
	public function setRequestVars($request_vars) { $this->request_vars = $request_vars; }
	
	//Getters
	public function getData() { return $this->data; }
	public function getElement() { return $this->element; }
	public function getID() { return $this->id; }
	public function getMethod() { return $this->method; }
	public function getOperation() { return $this->operation; }
	public function getHttpAccept() { return $this->http_accept; }
	public function getRequestVars() { return $this->request_vars; }
	
	public function __toString() {
		$res = 'Method: '.$this->getMethod()."\n".
		'Type: '.$this->getHttpAccept()."\n".
		'Variables: '."\n";
		
		foreach($this->getRequestVars() as $k=>$v)
			$res .= $k.'='.$v."\n";
		
		$res .= 'Data: '.$this->getData();
		
		return $res;
	}
}

interface Element {
	function dispatcher($request);
	function getCalledMethod($request);

	public function retrieveAll(); //GET
	/*public function find($id); //GET ?id=
	public function delete($id); //DELETE ?id=
	public function update($id); //Update: POST/PUT ?id=
	public function save(); //Create: POST/PUT*/
}

class Response {
	private $status;
	private $body;
	
	function __construct($status, $body='') {
		$this->status = $status;
		$this->body = $body;
	}
	
	function getStatus() { return $this->status; }
	function getBody() { return $this->body; }
	function setStatus($status) { $this->status = $status; }
	function setBody($body) { $this->body = $body; }
}
?>