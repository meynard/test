<?php

class Db {
  static $mysqli;
  static $num_rows;
  static $query_logs = array();
  function __construct() {
    if (!self::$mysqli) {
        $mysqli = new \mysqli("localhost", "mrwebstdev1206", "eawCEATT", "mrwebstdev1206");

        if ($mysqli->connect_errno) {
            self::$query_logs[] = "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
        }
    }
    self::$mysqli = $mysqli;
  }

  private static function prepare($sql, $params = null) {
    if (($stmt = self::$mysqli->prepare($sql))) {
        if (is_array($params)) {
            $a_params = array(str_repeat('s',count($params)));
            foreach($params as $index => $param) {
                $a_params[] = & $params[$index];
            }
            if (!($result = call_user_func_array(array($stmt, 'bind_param'), $a_params))) {
                $stmt = $result;
                self::$query_logs[] = "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
            }
        }
    } else {
        self::$query_logs[] = "Prepare failed: (" . self::$mysqli->errno . ") " . self::$mysqli->error;
    }
    return $stmt;
  }

  public static function execute($sql, $params = null) {
    if (($stmt = self::prepare($sql, $params))) {
        if (!$stmt->execute()) {
            self::$query_logs[] = "Execute failed: (" . $stmt->errno . ") " . $stmt->error . "\n>{$sql}";
        }
    }
    return $stmt;
  }

  public static function query($sql, $params = null) {
    $data = null;
    if (($stmt = self::execute($sql, $params))) {
        $md = $stmt->result_metadata();
        $fields = array();
        while($field = $md->fetch_field()) {
            $fields[$field->name] = &$result[$field->name];
        }        
        call_user_func_array(array($stmt, "bind_result"), $fields);        
        // returns a copy of a value
        $copy = create_function('$a', 'return $a;');
        while ($stmt->fetch()) {
            $data[] = array_map($copy, $fields);
        }
        $stmt->close();
    }
    return $data;
  }

  public static function num_rows($sql, $params = null) {
    $num_rows = 0;
    if (($stmt = self::execute($sql, $params))) {
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
    }
    return $num_rows;
  }
  
  public static function fetch_fields($table) {
    $fields = array();
    $sql = "SELECT * FROM {$table} LIMIT 0;";
    if (($stmt = self::execute($sql))) {
        $md = $stmt->result_metadata();
        $fields = array();
        while($field = $md->fetch_field()) {
            $fields[] = $field->name;
        }
        $stmt->close();
    }
    return $fields;
  }

  static function buildSql($sql, $params) {
    $mysqli = self::$mysqli;
    $func = function($value) use ($mysqli) {
      if (is_string($value))
        return $mysqli->real_escape_string($value);
      return $value;
    };

    $params = array_map($func, $params);
    array_unshift($params, str_replace('?', "'%s'", $sql));
    $_sql_ = call_user_func_array('sprintf', $params);
    self::$query_logs[] = "Executed sql: {$_sql_}";
    return $_sql_;
  }

  static function insert($table, $data) {
    $sql = "INSERT INTO {$table} SET ";
    $sql_set_fields = null;
    $a_params = null;
    $fields = self::fetch_fields($table);
    foreach($data as $field_name => $field_value) {
      if (!in_array($field_name, $fields)) continue;
      $a_params[] = $data[$field_name];
      $sql_set_fields[] = "$field_name = ?";
    }
    $sql .= implode(", ", $sql_set_fields);

    self::execute($sql, $a_params);

    array_shift($a_params);
    $_sql_ = self::buildSql($sql, $a_params);
    
    return self::$mysqli->insert_id;
  }

  static function update($table, $pkey, $data) {
    $sql = "UPDATE {$table} SET ";
    $sql_set_fields = null;
    $a_params = null;
    $fields = self::fetch_fields($table);
    foreach($data as $field_name => $field_value) {
      if (!in_array($field_name, $fields)) continue;
      $a_params[] = $data[$field_name];
      $sql_set_fields[] = "$field_name = ?";
    }
    $sql .= implode(", ", $sql_set_fields);

    $sql .= " WHERE {$pkey} = ?";
    $a_params[] = $data[$pkey];

    self::execute($sql, $a_params);

    array_shift($a_params);
    $_sql_ = self::buildSql($sql, $a_params);
  }

  static function delete($table, $pkey, $data) {
    $sql = "DELETE FROM {$table} ";
    $a_params = null;

    $sql .= " WHERE {$pkey} = ?";
    $a_params[] = $data[$pkey];

    self::execute($sql, $a_params);

    array_shift($a_params);
    $_sql_ = self::buildSql($sql, $a_params);
  }
  
  static function fetch($table, $data = null, $offset = 0, $limit = 5, $num_rows = 0) {
    $sql = "SELECT * FROM {$table} ";
    
    $sql_where_fields = array();
    $a_params = null;
    
    if (is_array($data)) {
        foreach($data as $field_name => $field_value) {
          $a_params[] = $data[$field_name];
          $sql_where_fields[] = "$field_name = ?";
        }
        if (count($sql_where_fields)) {
            $sql .= " WHERE " . implode(" AND ", $sql_where_fields);
        }
    }    
    
    $num_rows = self::num_rows($sql, $a_params);
    return;
    $sql .= " LIMIT {$offset}, {$limit}";
    
    $data = self::query($sql, $a_params);
    
    return $data;
  }

  static function run() {
    $mysqli = self::$mysqli;

    /* Non-prepared statement */
    if (!$mysqli->query("DROP TABLE IF EXISTS Customers") || !$mysqli->query("CREATE TABLE Customers(CustomerID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, FirstName VARCHAR(50), LastName VARCHAR(50))")) {
        self::$query_logs[] = "Table creation failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }
    /* Non-prepared statement */
    if (!$mysqli->query("DROP TABLE IF EXISTS users") || !$mysqli->query("CREATE TABLE users(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50), password VARCHAR(50))")) {
        self::$query_logs[] = "Table creation failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    /* Prepared statement, stage 1: prepare */
    if (!($stmt = $mysqli->prepare("INSERT INTO users(id, username, password) VALUES (?, ?, ?)"))) {
        self::$query_logs[] = "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    $id = 1;
    $username = 'root';
    $password = 'secret';

    if (!$stmt->bind_param("iss", $id, $username, $password)) {
        self::$query_logs[] = "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    if (!$stmt->execute()) {
      self::$query_logs[] = "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
    }
  }
}

return new Db();


/*

$db = new Db(array());

Db::run();

$id = Db::insert('users', [
  'username' => "xem's",
  'password' => 'secret',
]);
$id = Db::insert('users', [
  'username' => "mex",
  'password' => 'lotus',
]);
Db::update('users', 'id', [
  'id' => $id,
  //'username' => "xem's",
  'password' => 'p@$$',
]);
Db::delete('users', 'id', [
  'id' => 1,
  //'username' => "xem's",
  'password' => 'p@$$',
]);

*/
