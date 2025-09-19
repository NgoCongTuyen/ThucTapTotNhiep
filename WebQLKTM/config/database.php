<?php
class Database {
    private $host = "localhost";
    private $db_name = "warehouse_db";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8mb4");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
    
    // Kiểm tra kết nối database
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Kiểm tra bảng có tồn tại không
    public function tableExists($tableName) {
        try {
            $conn = $this->getConnection();
            $query = "SHOW TABLES LIKE ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Lấy thông tin database
    public function getDatabaseInfo() {
        try {
            $conn = $this->getConnection();
            $info = [];
            
            // Tên database
            $info['database_name'] = $this->db_name;
            
            // Danh sách bảng
            $query = "SHOW TABLES";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $info['tables'] = $tables;
            
            // Số lượng bản ghi trong mỗi bảng
            $info['table_counts'] = [];
            foreach ($tables as $table) {
                $query = "SELECT COUNT(*) as count FROM `$table`";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $result = $stmt->fetch();
                $info['table_counts'][$table] = $result['count'];
            }
            
            return $info;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    // Backup database (xuất SQL)
    public function backupDatabase() {
        try {
            $conn = $this->getConnection();
            $backup = "-- Backup database: {$this->db_name}\n";
            $backup .= "-- Created: " . date('Y-m-d H:i:s') . "\n\n";
            
            // Lấy danh sách bảng
            $query = "SHOW TABLES";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                // Cấu trúc bảng
                $query = "SHOW CREATE TABLE `$table`";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $result = $stmt->fetch();
                $backup .= "\n-- Table: $table\n";
                $backup .= "DROP TABLE IF EXISTS `$table`;\n";
                $backup .= $result['Create Table'] . ";\n\n";
                
                // Dữ liệu bảng
                $query = "SELECT * FROM `$table`";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $rows = $stmt->fetchAll();
                
                if (!empty($rows)) {
                    $backup .= "-- Data for table: $table\n";
                    foreach ($rows as $row) {
                        $values = array_map(function($value) {
                            return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                        }, array_values($row));
                        $backup .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $backup .= "\n";
                }
            }
            
            return $backup;
        } catch (Exception $e) {
            return "-- Error: " . $e->getMessage();
        }
    }
}
?>
