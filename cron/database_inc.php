<?php
	$BA_MYSQL_HOST   = '166.78.78.122';
	$BA_MYSQL_USER   = 'bioappeng';
	$BA_MYSQL_PASS   = '801AQU102Cdowns!#';
	$BA_MYSQL_DB_NAME= 'bioappeng';
	date_default_timezone_set('America/New_York');

  $BA_MYSQL_HOST   = 'localhost';
  $BA_MYSQL_USER   = 'root';
  $BA_MYSQL_PASS   = '';
  $BA_MYSQL_DB_NAME= 'bioappeng';

function OpenMySQLi(){
    global $mysqli;
    global $BA_MYSQL_HOST,$BA_MYSQL_USER, $BA_MYSQL_PASS,$BA_MYSQL_DB_NAME;
//    echo "opening...";
    $mysqli = new mysqli($BA_MYSQL_HOST, $BA_MYSQL_USER, $BA_MYSQL_PASS, $BA_MYSQL_DB_NAME);
        if (mysqli_connect_errno()) die("Connect failed: <br />".mysqli_connect_error());
	$mysqli->set_charset("utf8");
}

function MySQLiScalar($sql) {
    // $this->print_r_XML_comment($sql);
    global $mysqli;
	$scalar = null;
    $result = $mysqli->query($sql);
	if ($result){
		if ($row = mysqli_fetch_array($result, MYSQLI_NUM)){
			$scalar = $row[0];
			$result->free();
		}
	}
    return $scalar;
}
function MySQLiSingleRow($sql) {
    // $this->print_r_XML_comment($sql);
    global $mysqli;
	$row = null;
    $result = $mysqli->query($sql);
	if ($result){
		$row = mysqli_fetch_assoc($result);
	}
    return $row;
}

function DoMySQLiQuery($sql, $result_type = MYSQL_BOTH) {
    // $this->print_r_XML_comment($sql);
    global $mysqli;
    $result = mysqli_query($sql, $mysqli);
    if(mysqli_errno($this->mLink))
    	die(mysqli_error($mysqli));
    $phpResult = array();
    while ($row = mysqli_fetch_array($result, $result_type)) {
	    // $this->print_r_XML_comment($row);
    	$phpResult[] = $row;
    }
    mysqli_free_result($result);
    if(mysqli_errno($mysqli))
	    die(mysqli_error($mysqli));
    return $phpResult;
}

function print_r_XML_comment($expression, $return=false) {
    if ($return) {
	    return "<!--\n".print_r($expression,true)."\n-->\n";
    } else {
	    echo "<!--\n";
    	print_r($expression,false);
	    echo "\n-->\n";
    }
}

function is_date( $str ) {
  $stamp = strtotime( $str );
  if (!is_numeric($stamp))  {
     return FALSE;
  }
  $month = date( 'm', $stamp );
  $day   = date( 'd', $stamp );
  $year  = date( 'Y', $stamp );
  if (checkdate($month, $day, $year))   {
     return TRUE;
  }
  return FALSE;
}

?>