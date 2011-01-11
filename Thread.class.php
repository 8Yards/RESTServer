<?php
//http://www.alternateinterior.com/2007/05/multi-threading-strategies-in-php.html
class Thread {
    var $pref ; // process reference
    var $pipes; // stdio
    var $buffer; // output buffer

    /* private */ 
    function Thread() {
	$this->pref = 0;
	$this->buffer = "";
	$this->pipes = (array)NULL;
    }
    
    /* public static */ 
    function Create ($file) {
	$t = new Thread;
	$descriptor = array (0 => array ("pipe", "r"), 1 => array ("pipe", "w"), 2 => array ("pipe", "w"));
	$t->pref = proc_open ("php -q $file ", $descriptor, $t->pipes);
	stream_set_blocking ($t->pipes[1], 0);
	return $t;
    }
    
    /* public instance */ 
    function isActive () {
	$this->buffer .= $this->listen();
	$f = stream_get_meta_data ($this->pipes[1]);
	return !$f["eof"];
    }
    
    /* public instance */ 
    function close () {
	$r = proc_close ($this->pref);
	$this->pref = NULL;
	return $r;
    }
    
    /* public instance */ 
    function tell ($thought) {
	fwrite ($this->pipes[0], $thought);
    }
    
    /* public instance */ 
    function listen () {
	$buffer = $this->buffer;
	$this->buffer = "";
	while ($r = fgets ($this->pipes[1], 1024)) {
	    $buffer .= $r;
	}
	return $buffer;
    }

    /* public instance */ 
    function getError () {
	$buffer = "";
	while ($r = fgets ($this->pipes[2], 1024)) {
	    $buffer .= $r;
	}
	return $buffer;
    }
}
?>
