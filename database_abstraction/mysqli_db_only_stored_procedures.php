<?php
  
  /**
  *  @author Troy McQuinn
  *  This class facilitates database connections.  It is  meant to be easily modified to allow for multiple
  *  database types.  
  *
  *  NOTE:  All of the methods of this class are private; that is, they should only be accessed through a class that extends this one.
  */

  class mysqli_db_sp{
  
    var $conn = false;  // Database connection variable
    var $qcount = 0; // Counter for actual queries; does not include cached queries.
    
    /**
    * Instantiation
    */
    public function __construct(){
      global $cfg;
      $host = $cfg['db']['host'];
      $name = $cfg['db']['name'];
      $user = $cfg['db']['user'];
      $pass = $cfg['db']['pass'];
      $this->connect($host,$name,$user,$pass);
    }
  
    /**
    * This function creates a database connection based on the configuration file.
    * @param array $cfg = the configuration data, read in from the configuration ini file.
    * @return boolean $r = true if the connection was made, false if not or on error.
    */
    private function connect($host,$name,$user,$pass){
      // Confirm the host, username, and password are set and not empty
      if((!empty($host)) && (!empty($name)) && (!empty($user)) && (!empty($pass))){
        if(($this->conn = new mysqli($host,$user,$pass,$name)) !== false){
          $this->conn->set_charset('latin1'); // Need to explicitly set this in order to use mysqli_real_escape_string.
          return true;
        } else {
          return false;
        }
      } else {
        return false;
      }
    }
    
    private function rawQuery($q){
      $this->qcount++;
      return $this->conn->query($q);
    }
    
    /**
    * This function executes a read-only query, returning an associative array for each row.
    * Each row is an array containing an array of each field.
    * @param string $q = the query to execute.
    * @return array $r = the result set, as an associative array.
    */
    public function queryAll($q=false){
      if(($q === false) || ($this->conn === false)){
        return false;
      } else {
        $res = $this->rawQuery($q);
        if(($res !== false) && ($res->num_rows > 0)){
          $r = Array();
          $i = 0;  // Counter to keep track of the associative array's first dimension
          while($row = $res->fetch_assoc()){
            foreach($row as $field_name => $field_value){
              $r[$i][$field_name] = $this->SQLUnEscape($field_value);
            }
            $i++; // Increment the first dimension position of the associative array
          }
          return $r; // Returns the associative array
        } else {
          return false;
        }
      }
    }
    
    /**
    * This function executes a read-only query, returning a single row.
    * The database result is assumed to be either only one row or empty.
    * @param string $q = the query to execute.
    * @return array $row = the single row result, as a one-dimensional associative array.
    */
    public function queryOne($q=false){
      if(($q === false) || ($this->conn === false)){
        return false;
      } else {
        $res = $this->rawQuery($q);
        if(($res !== false) && ($res->num_rows > 0)){
          $r = Array();
          while($row = $res->fetch_assoc()){
            foreach($row as $field_name => $field_value){
              $r[$field_name] = $this->SQLUnEscape($field_value);
            }
          }        
          return $r;          
        } else {
          return false;
        }
      }
    }
    
    /**
    * This function executes a write-only query; it does NOT return a result set.
    * @param string $q = the query to execute.
    * @return boolean $r = true if the query executed successfully, false if not.
    */
    public function writeQuery($q=false){
      if(($q !== false) && ($this->conn !== false)){
        return $this->rawQuery($q);
      } else {
        return false;
      }
    }
    
    /**
    * Calls a prepared statement in the database.
    * This assembles the procedure call and formats the parameters.
    * @param $procedure = the name of the procedure, without parameters.
    * @param $data = an array of the parameters / inputs.  They must be in the proper order for the procedure call in the database.
    * @param $write_only = if true, the procedure is treated as a write operation and returns boolean for success / failure.  If false, it's treated as a data fetch.
    * @param boolean $all = if true, this function returns a two-dimensional array (rows and columns).  If false, a one-dimensional array is returned (first row only).
    * @return array - The array will be one-dimensional or two-dimensional depending on the $all parameter.
    */
    public function call($procedure,$data=Array(),$write_only=false,$all=true){
      if(!empty($data)){
        $parms = '';
        foreach($data as $v){
          $parms .= '\''.$this->SQLEscape($v).'\', ';
        }
        $parms = rtrim($parms,', ');
      }
      $q = (!empty($data)) ? 'CALL '.$procedure.'('.$parms.')' : 'CALL '.$procedure.'()';
      return (!$write_only && ($all) ? $this->queryAll($q) : $this->queryOne($q));
    }
    
    /**
    * Calls a function in the database.
    * This is NOT used to return data sets.
    * @param $function = the name of the function.
    * @param $data = an array of any parameters to be passed to the function.
    * @return (varies) - The result of the function.
    */
    public function func($function,$data=Array()){
      if(!empty($data)){
        $parms = '';
        foreach($data as $v){
          $parms .= '\''.$this->SQLEscape($v).'\', ';
        }
        $parms = rtrim($parms,', ');
      }
      $q = (!empty($data)) ? 'SELECT '.$function.'('.$parms.')' : 'CALL '.$function.'()';
      $r = $this->queryOne($q);
      return ($r === false) ? false : array_shift($r); // Return the first element of the result array.
    }
  
    /**
    * This function is for debugging and displays a formatted sql error message if something went wrong.
    */
    public function showErrors($r=false){
      if($r === false){
        echo $this->conn->errno.': '.$this->conn->error."\n";
      } else {
        return $this->conn->errno.': '.$this->conn->error;
      }
    }
    
    /**
    * This function escapes single-quotes.
    */
    private function SQLEscape($x){
      return $this->conn->real_escape_string($x);
    }
    
    /**
    * This function escapes all of the values in a 1-dimensional array.
    */
    private function SQLEscapeAll($d){
      if(empty($d)) return false;
      foreach($d as $n => $v){
        $d[$n] = $this->SQLEscape($v);
      }
      return $d;
    }
    
    /**
    * This function un-escapes single-quotes.
    */
    private function SQLUnEscape($x){
      return $x; // Do nothing right now.
    }
  
  
}
  
  
?>