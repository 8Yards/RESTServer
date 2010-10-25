<?php
abstract class Contacts extends Element {
	function __construct($request) {
		parent::__construct($request);
	}
	
	public function find($id) {return '';}
	public function retrieveAll() {return '';}
	public function delete($id) {return '';}
	public function save($id='') {return '';}
}
?>