<?php
class Db_Mysql {
    private $handle = null;
    private $dbname = null;
    public static $query_num;

    public function __construct($dbname, $username, $password ) {
	$this->handle = mysql_connect("127.0.0.1", $username, $password, false);
	mysql_query('SET character_set_connection=utf8,character_set_results=utf8,character_set_client=binary,sql_mode=\'\'', $this->handle);
	mysql_select_db($dbname, $this->handle);
    }

    public function query($sql) {
	if (!$source = mysql_query($sql, $this->handle)) $this->halt($sql, $this->handle);
	 ++self::$query_num;
	return $source;
    }

    public function escape($str) {
	return addslashes($str);
    }

    public function free($resource) {
	if (is_resource($resource)) {
	    mysql_free_result($resource);
	}
    }

    public function getOne($sql) {
	$resource = $this->query($sql);
	if ($resource) {
	    $row = mysql_fetch_array($resource, MYSQL_NUM);
	    $this->free($resource);
	    return $row[0];
	} else {
	    return null;
	}
    }

    public function server_info() {
	return mysql_get_server_info($this->handle);
    }

    public function fetchOne($sql) {
	$resource = $this->query($sql);
	if ($resource) {
	    $row = array();
	    $row = mysql_fetch_array($resource, MYSQL_ASSOC);
	    $this->free($resource);
	    return $row;
	} else {
	    return array();
	}
    }

    public function fetchAll($sql, $num=null, $start=0, $rid=null, $callback=null) {
	if ( $num ) {
	    $sql = $sql . ' LIMIT ' . intval($start) . ' , ' . $num;
	}
	if (null === $rid) {
	    $rid = 'id';
	}
	$resource = $this->query($sql, false);
	if ( $resource ) {
	    $return = array();
	    while ($row = mysql_fetch_array($resource, MYSQL_ASSOC)) {
		if (is_callable($callback)) {
		    if ($rid && $row[$rid]) {
			$return[$row[$rid]] = call_user_func($callback, $row);
		    } else {
			$return[] = call_user_func($callback, $row);
		    }
		} else {
		    if ($rid && isset($row[$rid]) && $row[$rid]) {
			$return[$row[$rid]] = $row;
		    } else {
			$return[] = $row;
		    }
		}
	    }
	    $this->free($resource);
	    return $return;
	} else {
	    return array();
	}
    }

    public function fetchArray($resource, $return_num=MYSQL_ASSOC) {
	return mysql_fetch_array($resource, $return_num);
    }

    public function insertId($bigint = false) {
	if ($bigint) {
	    $r = mysql_query('Select LAST_INSERT_ID()', $this->handle);
	    $row = mysql_fetch_array($r, MYSQL_NUM);
	    return $row[0]; //bigint 列
	} else {
	    return sprintf("%u", mysql_insert_id($this->handle));
	}
    }

    public function update($table, $where, $array, $safe=array(), $unset=array()) {
	$set = $this->createset($array, $safe, $unset);
	$sql = "Update $table Set $set Where $where";
	return $this->query($sql, true);
    }

    public function replace($table, $array, $safe=array()) {
	$set = $this->createset($array, $safe);
	$sql = "Replace Into $table Set $set";
	if ($resource = $this->query($sql, true)) {
	    return ($id = $this->insertId()) ? $id : true;
	}
	return false;
    }

    public function insert($table, $array, $safe=array(), $unset=array()) {
	$set = $this->createset($array, $safe, $unset);
	$sql = "Insert Into $table Set $set";
	if ($resource = $this->query($sql, true)) {
	    return ($id = $this->insertId()) ? $id : true;
	}
	return false;
    }

    public function createset($array, $safe=array(), $unset=array()) {
	$_res = array();
	foreach ((array) $array as $_key => $_val) {
	    if ($safe && !in_array($_key, $safe)) {
		continue;
	    } else {
		if ($unset && in_array($_key, $unset)) {
		    $_res[$_key] = "`$_key`=$_val";
		} else {
		    $_val = $this->escape($_val);
		    $_res[$_key] = "`$_key`='$_val'";
		}
	    }
	}
	return implode(',', $_res);
    }

    function num_rows($query){
	return mysql_num_rows($query);
    }

    protected function halt($sql) {
	throw new Exception('MySQL Query Error : ' . mysql_error($this->handle) . '<br /> SQL:' . $sql, mysql_errno($this->handle));
    }
}
