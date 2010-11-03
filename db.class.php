<?php
class DB {

	//make connection
	function __construct() {
		$host = "localhost";
		$username = "root";
		$prefix = 'n_';
		$password = "safesql";
		$database = "nebula";
		$this->connect($host, $username, $password, $database);
	}
	
	//connection..
	function connect($host, $username, $password, $database) {
		mysql_connect($host, $username, $password);
		mysql_select_db($database);
	}
	
	//close
	function close() {
		mysql_close();
	}
	
	//query
	function query($sql) {
		$q = mysql_query($sql);//die('SQL: '.$sql.'<br/>Error: '.mysql_error());
		if(!$q)
			throw new Exception('SQL: '.$sql.'<br/>Error: '.mysql_error());
		return $q;
	}
    
    function fetch($sqlCommand)
    {
        $result;
        $ris = $this -> query($sqlCommand);
        $control = $this -> controlQueryResult($sqlCommand);
        // $control=1 when everything went fine, if that's not the case return the appropriate err message
        if ($control!=1)
            return $control;
	$j=0;
        while($ind = mysql_fetch_assoc($ris))
			$result[$j++]=$ind;
        return $result;
    }
    
    function delete($table, $idFieldName, $idValue)
    {
        $sqlCommand = "DELETE FROM $prefix.$table WHERE $idFieldName = $idValue";
        $this -> query(sqlCommand);
            return controlQueryResult();
    }
    
	//array(id=>'12', column1 => 'vafdsfds')
	function insertArray($array, $table) {
		$sql = 'INSERT into '.$prefix.$table.' (';
		$i = 1;
		foreach($array as $k=>$v)
			$sql .= '`'.mysql_real_escape_string($k).'`'.($i++!=count($array) ? ', ':'');
		$sql .= ') VALUES (';
		$i = 1;
		foreach($array as $v)
			$sql .= '"'.mysql_real_escape_string($v).'"'.($i++!=count($array) ? ', ':'');//.'", ';
		$sql .= ')';
		$this->query($sql);
	}
	
	function updateArray($array, $colId, $id, $table) {
		$sql = 'UPDATE '.$prefix.$table.' Set ';
		$i = 1;
		foreach($array as $k=>$v)
			$sql .= '`'.mysql_real_escape_string($k).'`=\''.mysql_real_escape_string($v).'\''.($i++!=count($array) ? ', ':'');
		$sql .= ' where `'.$colId.'`=\''.$id.'\'';
		$this->query($sql);
	}
	function id() {
	 return mysql_insert_id();
	}
	function num($q) {
	 return mysql_num_rows($q);
	}
}
?>
