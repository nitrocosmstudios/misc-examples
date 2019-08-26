<?php
  
/**
*  @author Troy McQuinn
*  This class facilitates database connections and database procedure calls.
*  It should be noted that this class expects database calls to be performed using prepared statements and functions.
*  This class is not intended for use in making general database queries.
*/

class mysql_db_pdo{

  var $dbo = false;  // Database connection object
  var $qcount = 0; // Counter for database operations.
  
  /**
  * Instantiation
  */
  public function __construct(){
    global $cfg;
    $this->connect($cfg['db']['host'],$cfg['db']['name'],$cfg['db']['user'],$cfg['db']['pass']);
  }

  /**
  * This function creates a database connection based on the configuration file.
  * @param array $cfg = the configuration data, read in from the configuration ini file.
  * @return boolean $r = true if the connection was made, false if not or on error.
  */
  private function connect($host,$name,$user,$pass){
    // Confirm the host, username, and password are set and not empty
    if((!empty($host)) && (!empty($name)) && (!empty($user)) && (!empty($pass))){
      if(($this->dbo = new PDO("mysql:host=$host;dbname=$name",$user,$pass)) !== false){
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }
  
  /**
  * Calls a prepared statement in the database.
  * This assembles the procedure call and formats the parameters.
  * @param $procedure = the name of the procedure, without parameters.
  * @param $data = an array of the parameters / inputs.  They must be in the proper order for the procedure call in the database.  The array is non-associative (is numeric).
  * @param $write_only = if true, the procedure is treated as a write operation and returns boolean for success / failure.  If false, it's treated as a data fetch.
  * @param boolean $all = if true, this function returns a two-dimensional array (rows and columns).  If false, a one-dimensional array is returned (first row only).
  * @return array - The array will be one-dimensional or two-dimensional depending on the $all parameter.
  */
  public function call($procedure,$data=Array(),$write_only=false,$all=true){
    $this->qcount++;
    $sql = (!empty($data)) ? 'CALL '.$procedure.'('.rtrim(str_repeat('?,',count($data)),', ').')' : 'CALL '.$procedure.'()';
    $sth = $this->dbo->prepare($sql);
    $sth->setFetchMode(PDO::FETCH_ASSOC);
    $r = $sth->execute($data);
    return ($write_only || ($r === false)) ? $r : (($all) ? $sth->fetchAll() : $sth->fetch());  
  }
  
  /**
  * Calls a function in the database.
  * This is NOT used to return data sets.
  * @param $function = the name of the function.
  * @param $data = a non-associative array of any parameters to be passed to the function.
  * @return (varies) - The result of the function.
  */
  public function func($function,$data=Array()){
    $this->qcount++;
    $sql = (!empty($data)) ? 'SELECT '.$function.'('.rtrim(str_repeat('?,',count($data)),', ').')' : 'CALL '.$function.'()';
    $sth = $this->dbo->prepare($sql);
    $sth->setFetchMode(PDO::FETCH_NUM);
    $r = $sth->execute($data);
    $f = $sth->fetch();
    return ($r === false) ? false : $f[0];
  }
  
}
  
  
?>