class Db {
  private static $mysqli;
  function __construct($config) {
    $mysqli = new mysqli("localhost", "homestead", "secret", "homestead");

    if ($mysqli->connect_errno) {
        echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    }
    self::$mysqli = $mysqli;
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
    return call_user_func_array('sprintf', $params);
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

    if (!($stmt = $mysqli->prepare($sql))) {
        echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    if ($stmt) {
      if (!call_user_func_array([$stmt, 'bind_param'], $a_params)) {
          echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
      }

      if (!$stmt->execute()) {
          echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
      }
    }

    array_shift($a_params);
    $_sql_ = self::buildSql($sql, $a_params);

    echo "\nExecuted sql: {$_sql_}";
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

    if (!($stmt = $mysqli->prepare($sql))) {
        echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    if ($stmt) {
      if (!call_user_func_array([$stmt, 'bind_param'], $a_params)) {
          echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
      }
      if (!$stmt->execute()) {
          echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
      }
    }

    array_shift($a_params);
    $_sql_ = self::buildSql($sql, $a_params);

    echo "\nExecuted sql: {$_sql_}";
  }

  static function delete($table, $pkey, $data) {
    $mysqli = self::$mysqli;

    $sql = "DELETE FROM {$table} ";
    $sql_set_fields = null;
    $a_params = [''];

    $sql .= " WHERE {$pkey} = ?";
    $a_params[0] .= 's';
    $a_params[] = & $data[$pkey];

    if (!($stmt = $mysqli->prepare($sql))) {
        echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    if ($stmt) {
      if (!call_user_func_array([$stmt, 'bind_param'], $a_params)) {
          echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
      }
      if (!$stmt->execute()) {
          echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
      }
    }

    array_shift($a_params);
    $_sql_ = self::buildSql($sql, $a_params);

    echo "\nExecuted sql: {$_sql_}";
  }

  static function execute() {
    $mysqli = self::$mysqli;

    /* Non-prepared statement */
    if (!$mysqli->query("TRUNCATE users")) {
        echo "\nTable creation failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    /* Non-prepared statement */
    if (!$mysqli->query("DROP TABLE IF EXISTS test") || !$mysqli->query("CREATE TABLE test(id INT, description VARCHAR(50))")) {
        echo "\nTable creation failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    /* Prepared statement, stage 1: prepare */
    if (!($stmt = $mysqli->prepare("INSERT INTO test(id, description) VALUES (?, ?)"))) {
        echo "\nPrepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    $id = null;
    $description = null;

    if (!$stmt->bind_param("is", $id, $description)) {
        echo "\nBinding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    for($i=0; $i < 10; $i++) {
      /* Prepared statement, stage 2: bind and execute */
      $id = $i + 1;
      $description = "record #{$id}";

      if (!$stmt->execute()) {
          echo "\nExecute failed: (" . $stmt->errno . ") " . $stmt->error;
      }
    }
  }
}


$db = new Db(array());

Db::execute();

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
