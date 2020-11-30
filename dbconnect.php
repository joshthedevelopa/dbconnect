<?php
class DBConnect {
    private static $_instance = null;
    private $_pdo, $_query, $_operators = [">", "<", "=", "<=", ">="];
    public $results, $count, $hasError, $lastID;

    private function __construct() {
        try {
            $this->_pdo = new PDO('mysql:host=' . Config::get('mysql/host') . ';dbname=' . Config::get('mysql/db'), Config::get('mysql/username'), Config::get('mysql/password'));
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }

    public static function getInstance() {
        if (!isset(self::$_instance)) {
            self::$_instance = new DBConnect();
        }

        return self::$_instance;
    }

    public function query($sql, $params = []) {
        $this->hasError = false;

        if($this->_query = $this->_pdo->prepare($sql)) {
            if(count($params)) {
                foreach ($params as $key => $value) {
                    $this->_query->bindValue($key + 1, $value);
                }
            }
        }

        if($this->_query->execute()) {
            $this->results = $this->_query->fetchAll(PDO::FETCH_OBJ);
            $this->count = $this->_query->rowCount();
        } else {
            $this->hasError = true;
        }

        return $this;
    }

    public function action($action, $table, $where = [], $conjuctions = []) {
        $_extend = "";
        $values = [];

        if (count($where) > 0) {
            foreach (is_array($where[0]) ? $where : [$where] as $key => $array) {
                if (count($array) === 3) {
                    $field = $array[0];
                    $operator = $array[1];
                    $value = $array[2];

                    if (in_array($operator, $this->_operators)) {
                        $_extend .= " {$field} {$operator} ?";
                        array_push($values, $value);


                        if ($key + 1 < count($where) && is_array($where[0])) {
                            $_extend .=  " " . strtoupper($conjuctions[$key] ?? $conjuctions[0]) . " ";
                        }
                    }
                }
            }
        }
       
        $sql = "$action $table" . (($_extend == "") ? "" : " WHERE $_extend");
        return $this->query($sql, $values);        
    }

    public function get($table, $where = [], $conjuctions = ["AND"]) {
        return $this->action("SELECT * FROM", $table, $where, $conjuctions);
    }

    public function delete($table, $where, $conjuctions = ["OR"]) {
        return !$this->action("DELETE FROM", $table, $where, $conjuctions)->hasError;
    }

    public function insert($table, $fields) {
        if (count($fields)) {
            $_string = '';

            for ($i = 1; $i <= count($fields); $i++) {
                $_string .= ($i < count($fields)) ? "?, " : "? ";
            }

            $sql = "INSERT INTO {$table} (" . implode(", ", array_keys($fields)) . ") VALUES ({$_string})";
            if (!$this->query($sql, array_values($fields))->hasError) {
                $this->lastID = $this->_pdo->lastInsertId();

                return true;
            }
        }
        return false;
    }

    public function update($table, $fields, $id) {
        $_string = '';

        for ($i = 1; $i <= count($fields); $i++) {
            $_string .= $i < count($fields) ? ${array_keys($fields)[0]} . " = ?, " : array_keys($fields)[0] . " = ?";
        }

        $values = array_values($fields);
        array_push($values, $id);
        $sql = "UPDATE {$table} SET {$_string} WHERE id = ?";

        if (!$this->query($sql, $values)->hasError) {
            return true;
        }
        return false;
    }
}