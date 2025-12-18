<?php
class Database {
    private $apiUrl = 'http://localhost:3001/api';
    public $conn;
    private $lastInsertedId = null;

    public function __construct() {
        $this->conn = $this;
    }

    public function getConnection() {
        $health = $this->apiRequest('GET', '/health');
        if ($health && isset($health['status']) && $health['status'] === 'ok') {
            return $this;
        }
        throw new Exception("Cannot connect to API server");
    }

    private function apiRequest($method, $endpoint, $data = null) {
        $url = $this->apiUrl . $endpoint;

        $options = [
            'http' => [
                'method' => $method,
                'header' => 'Content-Type: application/json',
                'timeout' => 30,
                'ignore_errors' => true
            ]
        ];

        if ($data !== null) {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            error_log("API request failed: $url");
            return false;
        }

        return json_decode($result, true);
    }

    private function parseQuery($sql, $params) {
        $sql = trim($sql);
        $sqlLower = strtolower($sql);

        if (strpos($sqlLower, 'select') === 0) {
            preg_match('/from\s+(\w+)/i', $sql, $matches);
            $table = $matches[1] ?? null;

            if (!$table) return null;

            $columns = '*';
            preg_match('/select\s+(.*?)\s+from/i', $sql, $colMatches);
            if (isset($colMatches[1])) {
                $columns = $colMatches[1];
            }

            $where = [];
            if (preg_match('/where\s+(.+?)(\s+limit|\s+order|\s*$)/i', $sql, $whereMatches)) {
                $conditions = explode('AND', $whereMatches[1]);
                foreach ($conditions as $i => $condition) {
                    if (preg_match('/(\w+)\s*=\s*\?/', $condition, $condMatch)) {
                        if (isset($params[$i])) {
                            $where[$condMatch[1]] = $params[$i];
                        }
                    }
                }
            }

            $limit = null;
            if (preg_match('/limit\s+(\d+)/i', $sql, $limitMatch)) {
                $limit = (int)$limitMatch[1];
            }

            return [
                'type' => 'select',
                'table' => $table,
                'columns' => $columns,
                'where' => $where,
                'limit' => $limit
            ];
        }

        return null;
    }

    public function query($sql, $params = []) {
        try {
            $parsed = $this->parseQuery($sql, $params);

            if ($parsed && $parsed['type'] === 'select') {
                $response = $this->apiRequest('POST', '/select', [
                    'table' => $parsed['table'],
                    'columns' => $parsed['columns'],
                    'where' => $parsed['where'],
                    'limit' => $parsed['limit']
                ]);

                if ($response && isset($response['success']) && $response['success']) {
                    return new DatabaseResult($response['data']);
                }
            }

            return false;
        } catch(Exception $e) {
            error_log("خطا در اجرای کوئری: " . $e->getMessage());
            return false;
        }
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : false;
    }

    public function rowCount($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->rowCount() : 0;
    }

    public function lastInsertId() {
        return $this->lastInsertedId;
    }

    public function beginTransaction() {
        return true;
    }

    public function commit() {
        return true;
    }

    public function rollback() {
        return true;
    }

    public function closeConnection() {
        $this->conn = null;
    }

    public function prepare($sql) {
        return new DatabaseStatement($this, $sql);
    }
}

class DatabaseResult {
    private $data;
    private $position = 0;

    public function __construct($data) {
        $this->data = is_array($data) ? $data : [];
    }

    public function fetch($fetchStyle = null) {
        if ($this->position < count($this->data)) {
            return $this->data[$this->position++];
        }
        return false;
    }

    public function fetchAll($fetchStyle = null) {
        return $this->data;
    }

    public function rowCount() {
        return count($this->data);
    }
}

class DatabaseStatement {
    private $db;
    private $sql;
    private $result;

    public function __construct($db, $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute($params = []) {
        $this->result = $this->db->query($this->sql, $params);
        return $this->result !== false;
    }

    public function fetch($fetchStyle = null) {
        return $this->result ? $this->result->fetch() : false;
    }

    public function fetchAll($fetchStyle = null) {
        return $this->result ? $this->result->fetchAll() : false;
    }

    public function rowCount() {
        return $this->result ? $this->result->rowCount() : 0;
    }
}

$database = new Database();
$pdo = $database->getConnection();
?>
