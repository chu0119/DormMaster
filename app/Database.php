<?php
/**
 * 数据库连接类
 * 使用PDO进行数据库操作
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $lastQuery;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    /**
     * 执行查询并返回结果
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $this->lastQuery = $sql;
        return $stmt;
    }

    /**
     * 获取单行数据
     */
    public function getRow($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * 获取多行数据
     */
    public function getAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * 获取单个值
     */
    public function getOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ? array_values($result)[0] : null;
    }

    /**
     * 插入数据
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    /**
     * 更新数据
     */
    public function update($table, $data, $where, $whereParams = []) {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "`$key` = ?";
        }
        $sql = "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE $where";
        $params = array_merge(array_values($data), $whereParams);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * 删除数据
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM `$table` WHERE $where";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * 获取记录总数
     */
    public function count($table, $where = "1", $params = []) {
        $sql = "SELECT COUNT(*) as total FROM `$table` WHERE $where";
        $result = $this->getRow($sql, $params);
        return $result['total'] ?? 0;
    }

    /**
     * 分页查询
     */
    public function paginate($table, $page = 1, $pageSize = 10, $where = "1", $params = [], $order = "id DESC") {
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT * FROM `$table` WHERE $where ORDER BY $order LIMIT $offset, $pageSize";
        $data = $this->getAll($sql, $params);
        $total = $this->count($table, $where, $params);
        $totalPages = ceil($total / $pageSize);

        return [
            'data' => $data,
            'total' => $total,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'page_size' => $pageSize
        ];
    }

    /**
     * 开始事务
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * 提交事务
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * 回滚事务
     */
    public function rollBack() {
        return $this->pdo->rollBack();
    }

    /**
     * 获取最后插入ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * 获取最后查询
     */
    public function getLastQuery() {
        return $this->lastQuery;
    }
}