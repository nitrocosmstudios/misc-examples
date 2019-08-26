<?php

/* PDO */

/**
* This is a database abstraction class for mysql using PDO.
* For application-specific functionality, extend it to another class.
*/

class mysql_db{  // PDO Abstraction layer
	
	var $pdo; // Connection
	var $qc = 0; // Query count.
	var $qt = Array(); // Query execution times.
	var $show_errors = true;
	var $caching = false; // whether or not to enable caching.
	var $cache_ttl = 60; // Maximum age of query cache files.
	
	// Constructor.  Establishes the connection.
	public function __construct($h,$n,$u,$p,$cs='utf8'){		
		$s = "mysql:host=$h;dbname=$n;charset=$cs";
		$o = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => false,
		];
		$this->pdo = new PDO($s,$u,$p,$o);
	}
	
	// Executes a raw query.
	// $q = query, $r = whether or not a return value is expected, and $p = an associative array of any parameters.
	private function queryRaw($q,$r=false,$p=null){
		if((!empty($r)) && $this->caching){
			$t = $this->getCache($q,$p);
			if(!empty($t)) return $t;
		}
		try{
			$start = microtime(true);
			$s = $this->pdo->prepare($q);
			if(!empty($p)){
				$s = $this->bind($s,$p);
			}
			$t = $s->execute();
			$this->qc++;
			$this->qt[] = (microtime(true) - $start);
			if(!empty($r)){
				$d = $s->fetchAll(PDO::FETCH_ASSOC);
				if((!empty($d)) && $this->caching){
					$this->putCache($q,$p,$d);
				}
				return $d;
			} else {
				return $t;
			}
		} catch (PDOException $e) {
			$this->errorMsg($q,$e->getMessage());
			return false;
		}
	}
	
	// Builds the input parameters for a query.
	private function bind($s,$p){
		if((is_array($p)) && (!empty($p))){
			foreach($p as $n => $v){
				$s->bindValue(':'.$n,$v);
			}
		}
		return $s;
	}
	
	// Fires off an error message.
	private function errorMsg($q,$s){
		if($this->show_errors){
			echo "Database error: ".$s." in query:<br />\n";
			echo $q."<br />\n";
		}
	}
	
	// Performs data caching.
	private function getCache($q,$p){
		global $use_memcache;
		$start = microtime(true);
		if($use_memcache){
			global $memcache;
			$cache = $memcache->get(md5($q.serialize($p)).'.sql.dat');
			if(!empty($cache)){
				$this->qc++;
				$this->qt[] = microtime(true) - $start;
				return $cache;
			} else {
				return false;
			}
		} else {
			$cache_file = '../dat/cache/'.md5($q.serialize($p)).'.sql.dat';
			if((file_exists($cache_file)) && ((time() - filemtime($cache_file)) < $this->cache_ttl)){
				$this->qc++;
				$this->qt[] = microtime(true) - $start;
				return unserialize(file_get_contents($cache_file));
			}
		}
		return false;
	}
	
	private function putCache($q,$p,$d){
		global $use_memcache;
		if($use_memcache){
			global $memcache;
			$memcache->set(md5($q.serialize($p)).'.sql.dat',$d);
		} else {
			$cache_file = '../dat/cache/'.md5($q.serialize($p)).'.sql.dat';
			file_put_contents($cache_file,serialize($d));
		}
	}
	
	// Prepares long lists of fields for stored procedure parameters.
	public function prepFields($a){
		if(empty($a)) return false;
		$d = Array();
		foreach($a as $f){
			$d[] = ':'.$f;
		}
		return implode(',',$d);
	}
	
	// Removes any paramters in the data array that aren't specified in the fields for a stored procedure.
	public function dropNonMatchingParameters($d,$fields){
		if(empty($d) || empty($fields)) return $d;
		foreach($d as $n => $v){
			if(!in_array($n,$fields)) unset($d[$n]);
		}
		return $d;
	}
	
	// Returns the query execution time log.
	public function getExecutionTimes(){
		return $this->qt;
	}
	
	// Executes a query that writes data to the database.
	public function writeQuery($q,$p=null){
		return $this->queryRaw($q,false,$p);
	}
	
	// Executes a query that retrieves a single field from the database.  Returns a single field.
	public function querySingle($q,$p=null){
		$r = $this->queryRaw($q,true,$p);
		if(empty($r[0])) return false;
		$r = array_values($r[0]);
		return $r[0];
	}
	
	// Executes a query that returns a single row.
	public function queryOne($q,$p=null){
		$r = $this->queryRaw($q,true,$p);
		if(empty($r)) return false;
		return $r[0];
	}
	
	// Executes a query that returns multiple rows.
	public function queryAll($q,$p=null){
		return $this->queryRaw($q,true,$p);
	}
	
	// Creates and performs an insert query.
	public function insert($t,$d){
		$c = implode(',',array_keys($d));
		$v = ':'.implode(',:',array_keys($d));
		$q = "INSERT INTO `$t` ($c) VALUES ($v)";
		return $this->writeQuery($q,$d);
	}
	
	// Creates and performs a replace query.
	public function replace($t,$d){
		$c = implode(',',array_keys($d));
		$v = ':'.implode(',:',array_keys($d));
		$q = "REPLACE INTO `$t` ($c) VALUES ($v)";
		return $this->writeQuery($q,$d);
	}
	
	// Creates and performs an update query.
	public function update($t,$d,$w=false){
		$c = '';
		$wc = '';
		foreach($d as $n => $v){
			$c .= $n.' = :'.$n.',';
		}
		$c = rtrim($c,',');
		foreach($w as $n => $v){
			$wc .= $n.' = :'.$n.' AND ';
			$d[$n] = $v; // Add to parameters for binding
		}
		$wc = rtrim($wc,' AND ');
		$q = "UPDATE `$t` SET $c WHERE $wc";
		return $this->writeQuery($q,$d);
	}
	
	// Creates and performs a delete query.
	public function delete($t,$w=false){
		$wc = '';
		$d = Array();
		foreach($w as $n => $v){
			$wc .= $n.' = :'.$n.' AND ';
			$d[$n] = $v; // Add to parameters for binding
		}
		$wc = rtrim($wc,' AND ');
		$q = "DELETE FROM `$t` WHERE $wc";
		return $this->writeQuery($q,$d);		
	}
	
	public function calculatePageStart($page,$pg_size){
		return ($pg_size * ($page - 1));
	}
	
	// Gets the last insert ID.
	public function getLastInsertID(){
		return $this->pdo->lastInsertID();
	}
	
	// Gets the list of columns from a table.
	public function getColumns($table=false){
		$r = $this->queryAll('CALL getColumns(:table)',Array('table'=>$table));
		if(empty($r)) return false;
		$d = Array();
		foreach($r as $row){
			$d[] = $row['Field'];
		}
		return $d;
	}
	
	// Gets a simple list.
	public function getSimpleList($table,$id_col,$name_col){
		$r = $this->queryAll('CALL getSimpleList(:table,:id_col,:name_col)',Array('table'=>$table,'id_col'=>$id_col,'name_col'=>$name_col));
		if(empty($r)) return false;
		$d = Array();
		foreach($r as $row){
			$d[$row[$id_col]] = $row[$name_col];
		}
		return $d;
	}
	
}


?>