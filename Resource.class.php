<?php
class Element {
	protected $request;
	protected $dispatchArray = array();
	
	/*public function find($id); //GET ?id=
	public function delete($id); //DELETE ?id=
	public function update($id); //Update: POST/PUT ?id=
	public function save(); //Create: POST/PUT*/
	
	function __construct($request) {
		$this->request = $request;
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
}
?>