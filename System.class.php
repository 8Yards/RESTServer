<?php
class System extends Element {
	function __construct($request) {
		parent::__construct($request);

		$this->dispatchArray['get'][false]['reflectorName'] = 'reflectorName';
		
		$this -> nebulaDB = new DB();
	}

	public function reflectorName($data) {
		$status = 200;
		
		$sql = "SELECT `value` FROM n_nebulaconfig WHERE `key`='reflectorName'";
		$query = $this -> nebulaDB -> query($sql);
		$result = mysql_fetch_assoc($query);
		$result = $result['value'];

		return new Response($status, $result);
	}
}
?>
