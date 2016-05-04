<?php

class Db {
  private static $mysqli;
  function __construct($config) {
    $mysqli = new mysqli("localhost", "homestead", "secret", "homestead");

    if ($mysqli->connect_errno) {
        echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    }
    self::$mysqli = $mysqli;
  }

  private static function mysqli_prepare($sql) {
    if (!($stmt = self::$mysqli->prepare($sql))) {
        echo "Prepare failed: (" . self::$mysqli->errno . ") " . self::$mysqli->error;
    }
    return $stmt;
  }

  private static function mysqli_bind_param($stmt, $a_params) {
    if (!($result = call_user_func_array([$stmt, 'bind_param'], $a_params))) {
        echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
    }
    return $result;
  }

  private static function mysqli_execute($sql, $params) {
    $stmt = self::mysqli_prepare($sql);
    if ($stmt) {
      if (self::mysqli_bind_param($stmt, $params)) {
        if (!$stmt->execute()) {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }
      }
    }
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
    echo "\nExecuted sql: {$_sql_}";
    return $_sql_;
  }

  static function insert($table, $data) {
    $mysqli = self::$mysqli;

    $sql = "INSERT INTO {$table} SET ";
    $sql_set_fields = null;
    $a_params = [''];
    foreach($data as $field_name => $field_value) {
      $a_params[0] .= 's';
      $a_params[] = & $data[$field_name];
      $sql_set_fields[] = "$field_name = ?";
    }
    $sql .= implode(", ", $sql_set_fields);

    self::mysqli_execute($sql, $a_params);

    array_shift($a_params);
    $_sql_ = self::buildSql($sql, $a_params);

    return $mysqli->insert_id;
  }

  static function update($table, $pkey, $data) {
    $mysqli = self::$mysqli;

    $sql = "UPDATE {$table} SET ";
    $sql_set_fields = null;
    $a_params = [''];
    foreach($data as $field_name => $field_value) {
      if ($pkey==$field_name) continue;
      $a_params[0] .= 's';
      $a_params[] = & $data[$field_name];
      $sql_set_fields[] = "$field_name = ?";
    }
    $sql .= implode(", ", $sql_set_fields);

    $sql .= " WHERE {$pkey} = ?";
    $a_params[0] .= 's';
    $a_params[] = & $data[$pkey];

    self::mysqli_execute($sql, $a_params);

    array_shift($a_params);
    $_sql_ = self::buildSql($sql, $a_params);
  }

  static function delete($table, $pkey, $data) {
    $mysqli = self::$mysqli;

    $sql = "DELETE FROM {$table} ";
    $a_params = [''];

    $sql .= " WHERE {$pkey} = ?";
    $a_params[0] .= 's';
    $a_params[] = & $data[$pkey];

    self::mysqli_execute($sql, $a_params);

    array_shift($a_params);
    $_sql_ = self::buildSql($sql, $a_params);
  }

  static function run() {
    $mysqli = self::$mysqli;

    /* Non-prepared statement */
    if (!$mysqli->query("TRUNCATE users")) {
        echo "\nTable creation failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    /* Non-prepared statement */
    if (!$mysqli->query("DROP TABLE IF EXISTS users") || !$mysqli->query("CREATE TABLE users(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50), password VARCHAR(50))")) {
        echo "\nTable creation failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    /* Prepared statement, stage 1: prepare */
    if (!($stmt = $mysqli->prepare("INSERT INTO users(id, username, password) VALUES (?, ?, ?)"))) {
        echo "\nPrepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    $id = 1;
    $username = 'root';
    $password = 'secret';

    if (!$stmt->bind_param("is", $id, $username, $password)) {
        echo "\nBinding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    if (!$stmt->execute()) {
      echo "\nExecute failed: (" . $stmt->errno . ") " . $stmt->error;
    }
  }
}


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
