<?php
class RestUtils {

	public static function authentication() {
//		print_r($_SERVER);
//		exit();
		if (!isset($_SERVER['PHP_AUTH_USER'])) {
		    header('WWW-Authenticate: Basic realm="My Realm"');
		    header('HTTP/1.0 401 Unauthorized');
		    echo 'Text to send if user hits Cancel button';
		    exit;
		} else {	
			$db = new DB();
			$username = mysql_real_escape_string($_SERVER['PHP_AUTH_USER']);
			$password = mysql_real_escape_string($_SERVER['PHP_AUTH_PW']);
			$domain = 'nebula.com';

			$hash = md5($username .':'. $domain .':'. $password);
			
			$sql = "SELECT id from n_nebulauser WHERE username='$username' AND ha1='$hash'";

			$q = $db->query($sql);
			if( mysql_num_rows( $q ) ){
				$fetch = mysql_fetch_assoc($q);
				return $fetch['id'];
			}
			else
				return null;
		}

		return false;
	}

	public static function processRequest() {
		// get our verb
		$request_method = strtolower($_SERVER['REQUEST_METHOD']);
		$element = $_GET['REST_element'];
		$id = null;
		$operation = null;
		if(isset($_GET['REST_id']))
			$id = $_GET['REST_id'];
			
		if(isset($_GET['REST_operation']))
			$operation = $_GET['REST_operation'];
			
		
			
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
			/*case 'post':
				$data = $_POST;
				break;*/
			// here's the tricky bit...
			case 'put':
			case 'post':
				// basically, we read a string from PHP's special input location,
				// and then parse it out into an array via parse_str... per the PHP docs:
				// Parses str  as if it were the query string passed via a URL and sets
				// variables in the current scope.

				$contents = file_get_contents('php://input');

				//die('-'.$contents.'-');
				$data = RestUtils::data_decode( $contents, $_SERVER['CONTENT_TYPE'] );
				//$data = $contents;
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

		return $return_obj;
	}

	/**
	 * Simple recursive function to build an XML response.
	 */
	public static function xml_encode($k, $v) {
		if (is_object ($v) && strtolower (get_class ($v)) == 'simplexmlelement') {
			return preg_replace ('/<\?xml(.*?)\?>/', '', $v->asXML ());
		}
		$res = '';
		$attrs = '';
		if (! is_numeric ($k)) {
			$res = '<' . $k . '{{attributes}}>';
		}
		if (is_array ($v)) {
			foreach ($v as $key => $value) {
				if (strpos ($key, '@') === 0) {
					$attrs .= ' ' . substr ($key, 1) . '="' . $this->_xml_entities ($value) . '"';
					continue;
				}
				$res .= RestUtils::xml_encode($key, $value);
				$keys = array_keys ($v);
				if (is_numeric ($key) && $key != array_pop ($keys)) {
					$res .= '</' . $k . ">\n<" . $k . '>';
				}
			}
		} else {
			$res .= RestUtils::_xml_entities ($v);
		}
		if (! is_numeric ($k)) {
			$res .= '</' . $k . ">\n";
		}
		$res = str_replace ('<' . $k . '{{attributes}}>', '<' . $k . $attrs . '>', $res);
		return $res;
	}

	/**
	 * Converts entities to unicode entities (ie. < becomes &#60;).
	 * From php.net/htmlentities comments, user "webwurst at web dot de"
	 */
	public static function _xml_entities ($string) {
		$trans = get_html_translation_table (HTML_ENTITIES);

		foreach ($trans as $key => $value) {
			$trans[$key] = '&#' . ord ($key) . ';';
		}

		return strtr ($string, $trans);
	}
	
	public static function xml_decode($contents) {
		$xml = new SimpleXMLElement ($contents);

		//if ($xml->getName () == 'nebula') {
			// multiple
			
			/*$res = array ();
			//$cls = get_class ($this);
			//$cls = $xml->getName();
			foreach ($xml->children () as $child) {
				$obj = new stdClass();
				foreach ((array) $child as $k => $v) {
					$k = str_replace ('-', '_', $k);
					if (isset ($v['nil']) && $v['nil'] == 'true') {
						continue;
					} else {
						$obj->_data[$k] = $v;
					}
				}
				$res[] = $obj;
			}*/
			
			$res = array ();
			//$cls = get_class ($this);
			//$cls = $xml->getName();
			foreach ($xml->children () as $child) {
				$obj = new stdClass();
				foreach ((array) $child as $k => $v) {
					$k = str_replace ('-', '_', $k);
					if (isset ($v['nil']) && $v['nil'] == 'true') {
						continue;
					} else {
						$obj->_data[$k] = $v;
					}
				}
				$res[] = $obj;
			}
			
			echo $res;
			return $res;
		/*} elseif ($xml->getName () == 'errors') {
			// parse error message
			$this->error = $xml->error;
			$this->errno = $this->response_code;
			return false;
		}*/
	}
	
	public static function data_encode($contents, $type='json') {
		switch($type) {
			case 'json':
				return json_encode($contents, JSON_FORCE_OBJECT);
			case 'xml':
				return RestUtils::xml_encode('nebula', $contents);
		}
	}
	
	public static function data_decode($contents, $type='json') {
		if( strpos($type, 'json') )
			return json_decode($contents, true);
		else
			//return RestUtils::xml_decode($contents);
			$xml = simplexml_load_string($contents);
			return RestUtils::objectsIntoArray($xml);	}

function objectsIntoArray($arrObjData, $arrSkipIndices = array())
{
    $arrData = array();
    
    // if input is object, convert into array
    if (is_object($arrObjData)) {
        $arrObjData = get_object_vars($arrObjData);
    }
    
    if (is_array($arrObjData)) {
        foreach ($arrObjData as $index => $value) {
            if (is_object($value) || is_array($value)) {
                $value = objectsIntoArray($value, $arrSkipIndices); // recursive call
            }
            if (in_array($index, $arrSkipIndices)) {
                continue;
            }
            $arrData[$index] = $value;
        }
    }
    return $arrData;
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

	public static function sendResponse($status = 200, $body = '', $type = 'json') {
		if($body == '')
			$body = array();
		elseif(is_string($body) && ($type=='json' || $type=='xml'))
			$body = array('result' => $body);
			
		$status_header = 'HTTP/1.1 ' . $status . ' ' . RestUtils::getStatusCodeMessage($status);
		// set the status
		header($status_header);
		// set the content type
		if($type == 'json')
			header('Content-type: application/json');
		else if($type == 'xml')
			header('Content-type: application/xml');
		else
			header('Content-type: text/html');
		

		// pages with body are easy
		if($body != '') {
			// send the body
			if($type == 'json')
				$body = RestUtils::data_encode($body, 'json');
			else if($type == 'xml')
				$body = RestUtils::data_encode($body, 'xml');
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
				/*default:
					RestUtils::error();*/
			}
		}
		
		echo $body;
		exit();
	}
	
	public static function error($status='500', $body='') {
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
		$this->http_accept		= (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'xml')) ? 'xml' : 'json';
		$this->method			= 'get';
	}

	//Setters
	public function setData($data) { $this->data = $data; }
	public function setElement($element) { $this->element = $element; }
	public function setID($id) { $this->id = $id; }
	public function setMethod($method) { $this->method = $method; }
	public function setOperation($operation) { $this->operation = $operation; }
	public function setHttpAccept($http_accept) { $this->http_accept = $http_accept; }
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

class Response {
	private $status;
	private $body;
	
	function __construct($status, $body='', $type='') {
		$this->status = $status;
		$this->body = $body;
		$this->type = $type;
	}
	
	function getStatus() { return $this->status; }
	function getBody() { return $this->body; }
	function getType() { return $this->type; }
	function setStatus($status) { $this->status = $status; }
	function setBody($body) { $this->body = $body; }
	function setType($type) { $this->type = $type; }
}
?>
