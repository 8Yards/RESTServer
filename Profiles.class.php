<?php
class Profiles extends Element {
	function __construct($request) {
		parent::__construct($request);
		
		$this->dispatchArray['get'][false][null] = 'retrieveAll';
		$this->dispatchArray['get'][false]['return'] = 'reply';
		$this->dispatchArray['get'][true][null] = 'test';
		$this->dispatchArray['post'][true][null] = 'testpost';
	}
	
	public function testpost() {
		$status = 200;
		$result = $this->request->getData();
		$response = array('res' => $result->test1);
		//$response = array('1' => array('z'=>'2','3','4'), 6 => array('a','b','c'));
		return new Response($status, $response);
	}
	
	public function test() {
		$status = 200;
		$result = 'blabla';
		return new Response($status, $result);
	}
	
	public function retrieveAll() {
		$status = 200;
		$result = 'blabla';
		return new Response($status, $result);
	}
	
	public function reply($data) {
		$status = 200;
		$result = $data['param'];
		return new Response($status, $result);
	}
}
?>