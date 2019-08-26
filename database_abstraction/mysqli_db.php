<?php
  
  /**
  *  @author Troy McQuinn
  *  This class facilitates database connections.  It is  meant to be easily modified to allow for multiple
  *  database types.  
  *
  *  NOTE:  All of the methods of this class are private; that is, they should only be accessed through a class that extends this one.
  */

  class mysqli_db{
  
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
    * This function executes a read-only query, returning a single value.
    * The database result is assumed to be either one row with one column or empty.
    * @param string $q = the query to execute.
    * @return array $row = the single value result.
    */
    public function querySingle($q=false){
      if(($q === false) || ($this->conn === false)){
        return false;
      } else {
        $res = $this->rawQuery($q);
        if(($res !== false) && ($res->num_rows > 0)){
          $r = $res->fetch_row();
          return $r[0];
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
    * This function builds an update query.
    * @param string $table = the table to update.  If omitted (or false), short syntax is used (for appending ON DUPLICATE KEY insert queries).
    * @param array $data = the name => value pairs to update in the table.
    * @param array $where = the where conditions, listed in name => value pairs.  This can be ommitted (for appending ON DUPLICATE KEY insert queries).
    * @return $q = the generated query.
    */
    private function createUpdateQuery($table=false,$data=false,$where=false){
      // Reality checks
      if(($data === false) || (!is_array($data))) return false;
      $q = ($table === false) ? "UPDATE " : "UPDATE `".$table."` SET ";
      $i = 0;
      $k = count($data) - 1;
      foreach($data as $n => $v){
        $q .= $n." = '".$v."'";
        $q .= ($i == $k) ? ' ' : ', ';
        $i++;
      }
      if($where !== false){
        $q .= "WHERE ";
        $i = 0;
        $k = count($where) - 1;
        foreach($where as $n => $v){
          $q .= $n." = '".$v."'";
          $q .= ($i == $k) ? ' ' : ' AND ';
          $i++;   
        }    
      }
      return $q;  
    }
    
    // Wrapper function that sanitizes data and executes the query for createUpdateQuery().
    public function update($table=false,$data=false,$where=false){
      // Reality checks
      if(($table === false) || ($data === false) || (!is_array($data)) || ($where === false) || (!is_array($where))) return false;
      $data = $this->SQLEscapeAll($data);
      $where = $this->SQLEscapeAll($where);
      $q = $this->createUpdateQuery($table,$data,$where);
      return $this->writeQuery($q);
    }
    
    /**
    * This function builds an insert query.
    * @param string $table = the table in which to insert.
    * @param array $data = the name => value pairs to insert into the table.
    * @return $q = the generated query.
    */
    private function createInsertQuery($table=false,$data=false,$replace_flag=false,$dont_quote_these_fields=Array()){
      // Reality checks
      if(($table === false) || ($data === false) || (!is_array($data)) || (count($data) == 0)) return false;
      $c = ''; // Holder for COLUMNS portion of array
      $v = ''; // Holder for VALUES portion of array
      $i = 0;
      $k = count($data) - 1;
      foreach($data as $n => $x) {
        $c .= $n.(($i != $k) ? ', ' : '');
        if(array_search($n, $dont_quote_these_fields) === false) {
          $v .= "'" . $x . "'";
        } else {
          $v .= $x;
        }
        $v .= (($i != $k) ? ', ' : '');
        $i++;
      }    
      // Assemble
      $q = (($replace_flag) ? 'REPLACE' : 'INSERT').' INTO `'.$table.'` ('.$c.') VALUES ('.$v.')';
      // Output
      return $q;
    }
    
    // Wrapper function that sanitizes data and executes the query for createInsertQuery().
    public function insert($table=false,$data=false,$replace_flag=false,$dont_quote_these_fields=Array()){
      // Reality checks
      if(($table === false) || ($data === false) || (!is_array($data))) return false;
      $data = $this->SQLEscapeAll($data);
      $q = $this->createInsertQuery($table,$data,$replace_flag,$dont_quote_these_fields);
      return $this->writeQuery($q);
    }
    
    // Function to insert data with "on duplicate" update alternatives.
    public function insertAndUpdateDuplicates($table=false,$data=false,$where=false,$dont_quote_these_fields=Array()){
      // Reality checks
      if(($table === false) || ($data === false) || (!is_array($data)) || empty($data) || empty($where)) return false;
      $data = $this->SQLEscapeAll($data);
      $where = $this->SQLEscapeAll($where);
      $q  = $this->createInsertQuery($table,$data,false,$dont_quote_these_fields);
      $q .= ' ON DUPLICATE KEY ';
      $q .= $this->createUpdateQuery(false,$data,false);
      return $this->writeQuery($q);
    }
    
    /**
    * This function build a multiple-record insert query.
    * @param string $table = the table in which to insert.
    * @param array $data = the name => value pairs to insert into the table.
    * @return $q = the generated query.
    */
    private function createMultiInsertQuery($table=false,$data=false,$replace_flag=false){
      // Reality checks
      if(($table === false) || ($data === false) || (!is_array($data)) || (count($data) == 0) || (count($data[0]) == 0)) return false;
      $c = ''; // Holder for COLUMNS portion of array
      $v = ''; // Holder for VALUES portion of array
      // Get COLUMNS portion
      $i = 0;
      $k = count($data[0]) - 1;
      foreach($data[0] as $n => $x){
        $c .= $n.(($i != $k) ? ', ' : '');
        $i++;    
      }    
      $i = 0;
      $k = count($data) - 1;
      // Get VALUES portion
      foreach($data as $row){
        $a = 0;
        $j = count($row) - 1;
        $v .= "(";
        foreach($row as $n => $x){
          $v .= "'".$x."'".(($a != $j) ? ', ' : '');
          $a++;
        }
        $v .= ")".(($i != $k) ? ', ' : '');
        $i++;
      }    
      // Assemble
      $q = (($replace_flag) ? 'REPLACE' : 'INSERT').' INTO `'.$table.'` ('.$c.') VALUES '.$v;
      // Output
      return $q; 
    }
    
    // Wrapper function that sanitizes data and executes the query for createMultiInsertQuery().
    public function multiInsert($table=false,$data=false,$replace_flag=false){
      // Reality checks
      if(($table === false) || ($data === false) || (!is_array($data))) return false;
      foreach($data as $row_id => $row){
        $data[$row_id] = $this->SQLEscapeAll($row);
      }
      $q = $this->createMultiInsertQuery($table,$data,$replace_flag);
      return $this->writeQuery($q);
    }
    
    /**
    * This function builds a delete query.
    * @param string $table = the table to update.
    * @param array $where = the where conditions, listed in name => value pairs.
    * @return $q = the generated query.
    */
    private function createDeleteQuery($table=false,$where=false){
      // Reality checks
      if(($table === false) || ($where === false) || (!is_array($where))) return false;
      $q = "DELETE FROM `".$table."` ";
      if($where !== false){
        $q .= "WHERE ";
        $i = 0;
        $k = count($where) - 1;
        foreach($where as $n => $v){
          $q .= $n." = '".$v."'";
          $q .= ($i == $k) ? ' ' : ' AND ';
          $i++;   
        }    
      }
      return $q;  
    }
    
    // Wrapper function that sanitizes data and executes the query for createUpdateQuery().
    public function delete($table=false,$where=false){
      // Reality checks
      if(($table === false) || ($where === false) || (!is_array($where))) return false;
      $where = $this->SQLEscapeAll($where);
      $q = $this->createDeleteQuery($table,$where);
      return $this->writeQuery($q);
    }
    
    /**
    * This function escapes single-quotes.
    */
    public function SQLEscape($x){
      return trim($this->conn->real_escape_string($x));
    }
    
    /**
    * This function escapes all of the values in a 1-dimensional array.
    */
    public function SQLEscapeAll($d){
      if(empty($d)) return false;
      foreach($d as $n => $v){
        $d[$n] = $this->SQLEscape($v);
      }
      return $d;
    }
    
    /**
    * This function un-escapes single-quotes.
    */
    public function SQLUnEscape($x){
      return $x; // Do nothing right now.
    }
    
    /**
     * This function builds a pagination LIMIT clause.
     */
    public function createPaginationClause($page=false,$pg_size=false){
      $page = (($page === false) || ($page < 1)) ? 1 : intval($page);
      $pg_size = (($pg_size === false) || ($pg_size < 1)) ? 5 : intval($pg_size);
      $pg_start = ($pg_size * ($page - 1));
      return ' LIMIT '.$pg_start.', '.$pg_size.' ';
    }
    
    /**
    * Quickly shows the list of columns for a table.
    */
    public function listColumns($table){
      $r = $this->queryAll('SHOW COLUMNS FROM `'.$this->SQLEscape($table).'`');
      $d = Array();
      if($r === false) return false;
      foreach($r as $row){
        $d[] = $row['Field'];
      }
      return $d;
    }
    
    /**
    * Gets a simple list of values from a table.
    * Accepts the names of the id and 'name' fields.  Returns a basic array.
    */
    public function getSimpleList($id_field, $name_field, $table_name, $where_clause = null, $orderByField = null){
      $id_field = $this->SQLEscape($id_field);
      $name_field = $this->SQLEscape($name_field);
      $table_name = $this->SQLEscape($table_name);
      $r = $this->queryAll('SELECT `'.$id_field.'`, `'.$name_field.'` FROM `'.$table_name. '`' . (!empty($where_clause) ? ' WHERE ' . $where_clause : '') . ' ORDER BY `'. (isset($orderByField) ? $orderByField : $id_field) . '` ASC');
      if($r === false) return false;
      $d = Array();
      foreach($r as $row){
        $d[$row[$id_field]] = $row[$name_field];
      }
      return $d;
    }
  

  
  
}
  
  
?>