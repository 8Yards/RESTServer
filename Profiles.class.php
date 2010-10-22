<?php
class Profiles implements Element {
	private $request;
	private $dispatchArray = array();
	
	function __construct($request) {
		$this->request = $request;
		$this->dispatchArray['get'][false][null] = 'retrieveAll';
		$this->dispatchArray['get'][false]['return'] = 'reply';
		$this->dispatchArray['get'][true][null] = 'test';
	}
	
	function getCalledMethod($request) {
		if( !isset( $this->dispatchArray[ $request->getMethod() ][ ($request->getID() != null ? true:false) ][ $request->getOperation() ] ) )
			return '';
		
		return $this->dispatchArray[ $request->getMethod() ][ ($request->getID() != null ? true:false) ][ $request->getOperation() ];
	}
	
	function dispatcher($request) {
		$call = $this->getCalledMethod($request);
	
		if(!is_callable( array( $this, $call ) ))
			return false;
		
		return call_user_func( array( $this, $call ), $request->getData() );
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