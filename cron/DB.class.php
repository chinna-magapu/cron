<?php
date_default_timezone_set('America/New_York');

Class DB{
    public $mysqli;
	public $error;
	public $debug = false;

	function __construct(){
		$hostname = php_uname("n");
		$BA_MYSQL_HOST   = '166.78.78.122';
		$BA_MYSQL_USER   = 'bioappeng';
		$BA_MYSQL_PASS   = '801AQU102Cdowns!#';
		$BA_MYSQL_DB_NAME= 'bioappeng';
		$this->mysqli = new mysqli($BA_MYSQL_HOST, $BA_MYSQL_USER, $BA_MYSQL_PASS, $BA_MYSQL_DB_NAME);
		if (mysqli_connect_errno()) die("Connect failed: <br />".mysqli_connect_error());
		$this->mysqli->query("SET NAMES 'utf8'");
	}

	function __destruct(){
		@$this->mysqli->close();
		$this->mysqli = null;
	}

	public function query($sql, $single_row = false, $keyby = "", $result_type = MYSQLI_ASSOC) {
    //does not use prepared statements - not safe with user input
		$this->error = '';
		if ($this->debug) echo "\nQuery\n$sql\n";
	    $result = mysqli_query($this->mysqli,$sql);
		if (mysqli_errno($this->mysqli) != 0){
			$this->error = $this->mysqli->error;
			return false;
		}
		if ($single_row){
	    	$row = $result->fetch_array($result_type);
			$result->free();
			return $row;
		} else {
		    $rows = array();
		    while ($row = $result->fetch_array($result_type)) {
		    	if ($keyby != ''){
		    		$rows[$row[$keyby]] = $row;
		    	} else {
		    		$rows[] = $row;
		    	}
		    }
	    	$result->free();
		    return $rows;
		}
	}
	public function querySimpleArray($sql, $col0, $col1) {
		// return a simple array of col1 indexed by col0
		//does not use prepared statements - not safe with user input
		$this->error = '';
		if ($this->debug) echo "\nQuery\n$sql\n";
	    $result = mysqli_query($this->mysqli,$sql);
		if (mysqli_errno($this->mysqli) != 0){
			$this->error = $this->mysqli->error;
			return false;
		}
	    $rows = array();
	    while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
    		$rows[$row[$col0]] = $row[$col1];
	    }
    	$result->free();
	    return $rows;
	}

	public function getScalar($sql) {
	    // does not use prepared statements - not safe with user input
		// returns $row[0]
		$this->error = '';
	    $result = mysqli_query($this->mysqli,$sql);
		if (mysqli_errno($this->mysqli) != 0){
			$this->error = $this->mysqli->error;
			return false;
		}
    	$row = $result->fetch_row();
    	$result->free();
	    return $row[0];
	}

	public function exec($sql) {
	    //does not use prepared statements - not safe with user input
		$this->error = '';
	    $ok = $this->mysqli->query($sql);
		if (!$ok){
			$this->error = $this->mysqli->error;
			return false;
		} else {
			return $this->mysqli->affected_rows;
		}
	}
	public function exec_multi($sql) {
	    //does not use prepared statements - not safe with user input
		$this->error = '';
	    $ok = $this->mysqli->multi_query($sql);
		if (!$ok){
			$this->error = $this->mysqli->error;
			return 0;
		} else {
			$rows_affected = 0;
			while ($this->mysqli->more_results()) {
				@$rows_affected += ($this->mysqli->next_result());
			}
			return $rows_affected;
		}
	}

	private function getRefs($arr){
		// returns an array of references to input array elements
		$refs = array();
		foreach($arr as $key => $value)
			$refs[$key] = &$arr[$key];
		return $refs;
    }

	public function fetch_prepared($sql, $params, $keyby = "", $assoc = MYSQLI_ASSOC){
		// params is an array; elt 0 has type string, remaining elements are values e.g. Array('ss', $id, $name)
		// accepts  MYSQLI_ASSOC, MYSQLI_NUM, or MYSQLI_BOTH.
		// if keyby is set result will be indexed by that field
		if ($this->debug) echo "\nQuery\n$sql\n";
		$keyby = $assoc == MYSQLI_NUM ? '' : $keyby;
		$this->error = '';
		if (!$stmt = $this->mysqli->prepare($sql)){
			$this->error = $this->mysqli->error;
			return false;
		}
		call_user_func_array(array($stmt, 'bind_param'), $this->getRefs($params));
		if (!$stmt->execute()){
			$this->error = $this->mysqli->error;
			@$stmt->close();
			return false;
		}
		// fetch metadata for result set and create an array of references
		$meta = $stmt->result_metadata();
		$row = $bindParams = array();
		while ($field = $meta->fetch_field()) {
			$row[$field->name] = '';
			$bindParams[] = &$row[$field->name];
		}
		// bind statement results to each field in $row
		call_user_func_array(array($stmt, 'bind_result'), $bindParams);
		$results = array();
		while ($stmt->fetch()) {
			// now $row has the data, but we need to copy it into a new row because next fetch overwrites
			$x = array();
			if ($assoc ==  MYSQLI_ASSOC) {
				foreach($row as $key=>$val) {
					$x[$key] = $val;
				}
			} elseif ($assoc == MYSQLI_BOTH){
				$i = 0;
				foreach($row as $key=>$val) {
					$x[$key] = $val;
					$x[$i++] = $val;
				}
			}  else {
				// MYSQLI__NUM
				$i = 0;
				foreach($row as $key=>$val) {
					$x[$i++] = $val;
				}
			}
			if ($keyby == ""){
			$results[] = $x;
			} else {
				$results[$x[$keyby]] = $x;
			}
		}
		$stmt->close();
		return  $results;
	}

	public function fetchrow_prepared($sql, $params, $assoc = MYSQLI_ASSOC){
		// returns a one-dimensional array with a single row
		// params is an array; elt 0 has type string, remaining elements are values e.g. Array('ss', $id, $name)
		// accepts  MYSQLI_ASSOC, MYSQLI_NUM, or MYSQLI_BOTH.
		if ($this->debug) echo "\nQuery\n$sql\n";
		$this->error = '';
		if (!$stmt = $this->mysqli->prepare($sql)){
			$this->error = $this->mysqli->error;
			return false;
		}
		call_user_func_array(array($stmt, 'bind_param'), $this->getRefs($params));
		if (!$stmt->execute()){
			$this->error = $this->mysqli->error;
			@$stmt->close();
			return false;
		}
		// fetch metadata for result set and create an array of references
		$meta = $stmt->result_metadata();
		$row = $bindParams = array();
		while ($field = $meta->fetch_field()) {
			$row[$field->name] = '';
			$bindParams[] = &$row[$field->name];
		}
		// bind statement results to each field in $row
		call_user_func_array(array($stmt, 'bind_result'), $bindParams);
		$x = null;
		if ($stmt->fetch()) {
			$x = array();
			if ($assoc ==  MYSQLI_ASSOC) {
				foreach($row as $key=>$val) {
					$x[$key] = $val;
				}
			} elseif ($assoc == MYSQLI_BOTH){
				$i = 0;
				foreach($row as $key=>$val) {
					$x[$key] = $val;
					$x[$i++] = $val;
				}
			}  else {
				// MYSQLI__NUM
				$i = 0;
				foreach($row as $key=>$val) {
					$x[$i++] = $val;
				}
			}
		}
		$stmt->close();
		return  $x;
	}

	public function getScalar_prepared($sql, $params){
		// returns first element in first fetch of result
		$this->error = '';
		if (!$stmt = $this->mysqli->prepare($sql)){
			$this->error = $this->mysqli->error;
			//echo "ERROR: ",$this->error;
			//echo "\n{$sql}\n";
			return false;
		}
		call_user_func_array(array($stmt, 'bind_param'), $this->getRefs($params));
		if (!$stmt->execute()){
			$this->error = $this->mysqli->error;
			//echo "ERROR: ",$this->error;
			@$stmt->close();
			return false;
		}
		// fetch metadata for result set and create an array of references
		$meta = $stmt->result_metadata();
		$row = $bindParams = array();
		while ($field = $meta->fetch_field()) {
			$row[$field->name] = '';
			$bindParams[] = &$row[$field->name];
		}
		// bind statement results to each field in $row
		call_user_func_array(array($stmt, 'bind_result'), $bindParams);
		$result = null;
		if ($stmt->fetch()) {
			$result = reset($row);
		}
		$stmt->close();
		return  $result;
	}

	public function exec_prepared($sql, $params){
		$this->error = '';
		if (!$stmt = $this->mysqli->prepare($sql)){
			$this->error = $this->mysqli->error;
			return false;
		}
		call_user_func_array(array($stmt, 'bind_param'), $this->getRefs($params));
		if (!$stmt->execute()){
			$this->error = $this->mysqli->error;
			@$stmt->close();
			return false;
		}
		$stmt->close();
		$result = $this->mysqli->affected_rows;
		return  $result;
	}

	private function bindVars($stmt,$params) {
		/* http://www.devmorgan.com/blog/2009/03/27/dydl-part-3-dynamic-binding-with-mysqli-php/ */
		if ($params != null) {
			$types = '';                        //initial sting with types
			foreach($params as $param) {        //for each element, determine type and add
				if(is_int($param)) {
					$types .= 'i';              //integer
				} elseif (is_float($param)) {
					$types .= 'd';              //double
				} elseif (is_string($param)) {
					$types .= 's';              //string
				} else {
					$types .= 'b';              //blob and unknown
				}
			}

			$bind_names[] = $types;             //first param needed is the type string
												// eg:  'issss'

			for ($i=0; $i<count($params);$i++) {//go through incoming params and added em to array
				$bind_name = 'bind' . $i;       //give them an arbitrary name
				$$bind_name = $params[$i];      //add the parameter to the variable variable
				$bind_names[] = &$$bind_name;   //now associate the variable as an element in an array
			}

												//call the function bind_param with dynamic params
			call_user_func_array(array($stmt,'bind_param'),$bind_names);
		}
		return $stmt;                           //return the bound statement
	}

    public function upsert($tbl, $cols, $keycols){
        /* performs an upsert (UPdate or inSERT) equivalent to REPLACE INTO
		   using INSERT INTO ON DUPLICATE KEY UPDATE
		   $tbl is the table name
		   $cols is an associative array $col=>$val
		   $keycols is an array of key field names */

		/* example code pk is id + seq
    	    	$tbl = 'stuff';
				$cols = array('id'=>$id, 'seq'=>$seq, 'item1'=>$item1, 'item2'=>$item2);
				$keycols = array('id','seq');
				$rows = $db->upsert($tbl, $cols, $keycols;
    		will produce and execute
			INSERT INTO stuff(id,seq,item1,item2) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE item1=?,item2=?
		*/

		$colnames  = array_keys($cols);
		$colvals   = array_values($cols);
		$sql = "INSERT INTO {$tbl} (".implode(',',$colnames).') VALUES ('
			.str_repeat('?,',count($cols)-1).'?) ON DUPLICATE KEY UPDATE ';
		foreach($cols as $colname=>$colval){
            if (!in_array($colname,$keycols)){
            	$sql .= $colname. "=?, ";
				$colvals[] = $colval;
            }
		}
		$sql = substr($sql,0,strlen($sql)-2);
		if (!$stmt = $this->mysqli->prepare($sql)){
			$this->error = $this->mysqli->error;
			return false;
		}
        $this->bindVars($stmt,$colvals);
		if (!$stmt->execute()){
			$this->error = $this->mysqli->error;
			@$stmt->close();
			return false;
		}
		$stmt->close();
		$result = $this->mysqli->affected_rows;
		return  $result;
    }
}
?>
