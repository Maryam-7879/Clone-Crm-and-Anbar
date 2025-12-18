<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    public $conn;

    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->charset = DB_CHARSET;
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $connection = DB_CONNECTION ?? 'pgsql';
            $port = defined('DB_PORT') ? DB_PORT : 6543;

            if ($connection === 'pgsql') {
                $dsn = "pgsql:host=" . $this->host . ";port=" . $port . ";dbname=" . $this->db_name;
            } else {
                $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
                if (defined('DB_SOCKET') && file_exists(DB_SOCKET)) {
                    $dsn = "mysql:unix_socket=" . DB_SOCKET . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
                }
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            error_log("خطا در اتصال به دیتابیس: " . $exception->getMessage());
            die("خطا در اتصال به دیتابیس: " . $exception->getMessage());
        }

        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }

    // تابع امن برای اجرای کوئری
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("خطا در اجرای کوئری: " . $e->getMessage());
            return false;
        }
    }

    // تابع برای دریافت یک رکورد
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    // تابع برای دریافت چندین رکورد
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : false;
    }

    // تابع برای دریافت تعداد رکوردها
    public function rowCount($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->rowCount() : 0;
    }

    // تابع برای دریافت آخرین ID اضافه شده
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    // تابع برای شروع تراکنش
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    // تابع برای تایید تراکنش
    public function commit() {
        return $this->conn->commit();
    }

    // تابع برای لغو تراکنش
    public function rollback() {
        return $this->conn->rollback();
    }
}

// ایجاد نمونه سراسری از دیتابیس
$database = new Database();
$pdo = $database->getConnection();
?>
