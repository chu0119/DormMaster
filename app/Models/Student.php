<?php
/**
 * 学生模型
 */

class Student {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 获取学生列表
     */
    public function getList($page = 1, $pageSize = 10, $filters = []) {
        $where = "1 = 1";
        $params = [];

        if (!empty($filters['keyword'])) {
            $where .= " AND (s.student_id LIKE ? OR s.real_name LIKE ? OR s.phone LIKE ?)";
            $keyword = "%" . $filters['keyword'] . "%";
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
        }

        if (!empty($filters['college'])) {
            $where .= " AND s.college = ?";
            $params[] = $filters['college'];
        }

        if (!empty($filters['major'])) {
            $where .= " AND s.major = ?";
            $params[] = $filters['major'];
        }

        if (isset($filters['gender'])) {
            $where .= " AND s.gender = ?";
            $params[] = $filters['gender'];
        }

        if (isset($filters['status'])) {
            $where .= " AND s.status = ?";
            $params[] = $filters['status'];
        }

        // 检查是否有宿舍
        if (isset($filters['has_room'])) {
            if ($filters['has_room'] == 1) {
                $where .= " AND EXISTS (SELECT 1 FROM room_assignments ra WHERE ra.student_id = s.id AND ra.status = 1)";
            } else {
                $where .= " AND NOT EXISTS (SELECT 1 FROM room_assignments ra WHERE ra.student_id = s.id AND ra.status = 1)";
            }
        }

        $sql = "SELECT
                    s.*,
                    ra.room_id,
                    r.room_number,
                    r.floor,
                    b.building_name,
                    b.building_code,
                    ra.bed_number,
                    ra.move_in_date,
                    u.username
                FROM students s
                LEFT JOIN room_assignments ra ON s.id = ra.student_id AND ra.status = 1
                LEFT JOIN rooms r ON ra.room_id = r.id
                LEFT JOIN dormitory_buildings b ON r.building_id = b.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE $where
                ORDER BY s.id DESC";

        // 使用自定义查询以支持JOIN
        $stmt = $this->db->query($sql, $params);
        $total = $this->db->count('students', str_replace('s.', '', $where), $params);

        // 分页
        $offset = ($page - 1) * $pageSize;
        $sql .= " LIMIT $offset, $pageSize";
        $data = $this->db->getAll($sql, $params);

        return [
            'data' => $data,
            'total' => $total,
            'current_page' => $page,
            'total_pages' => ceil($total / $pageSize),
            'page_size' => $pageSize
        ];
    }

    /**
     * 获取单个学生
     */
    public function getById($id) {
        $sql = "SELECT
                    s.*,
                    ra.room_id,
                    r.room_number,
                    r.floor,
                    b.building_name,
                    b.building_code,
                    ra.bed_number,
                    ra.move_in_date,
                    u.username
                FROM students s
                LEFT JOIN room_assignments ra ON s.id = ra.student_id AND ra.status = 1
                LEFT JOIN rooms r ON ra.room_id = r.id
                LEFT JOIN dormitory_buildings b ON r.building_id = b.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.id = ?";

        return $this->db->getRow($sql, [$id]);
    }

    /**
     * 通过学号获取学生
     */
    public function getByStudentId($studentId) {
        return $this->db->getRow("SELECT * FROM students WHERE student_id = ?", [$studentId]);
    }

    /**
     * 添加学生
     */
    public function add($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $id = $this->db->insert('students', $data);

        // 记录日志
        $auth = new Auth();
        $auth->logOperation($auth->getUserId(), 'student', 'add', "添加学生: {$data['student_id']} {$data['real_name']}");

        return $id;
    }

    /**
     * 批量添加学生
     */
    public function batchAdd($students) {
        $this->db->beginTransaction();

        try {
            $count = 0;
            foreach ($students as $data) {
                // 检查学号是否已存在
                $exists = $this->db->count('students', 'student_id = ?', [$data['student_id']]);
                if ($exists > 0) {
                    continue; // 跳过已存在的学生
                }

                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');
                $data['status'] = $data['status'] ?? 1;

                $this->db->insert('students', $data);
                $count++;
            }

            $this->db->commit();

            // 记录日志
            $auth = new Auth();
            $auth->logOperation($auth->getUserId(), 'student', 'batch_add', "批量导入学生: {$count} 人");

            return ['success' => true, 'count' => $count];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 更新学生
     */
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $result = $this->db->update('students', $data, 'id = ?', [$id]);

        // 记录日志
        $auth = new Auth();
        $auth->logOperation($auth->getUserId(), 'student', 'update', "更新学生ID: $id");

        return $result;
    }

    /**
     * 删除学生
     */
    public function delete($id) {
        // 检查是否有宿舍分配
        $assignmentCount = $this->db->count('room_assignments', 'student_id = ? AND status = 1', [$id]);
        if ($assignmentCount > 0) {
            return ['success' => false, 'message' => '该学生有宿舍分配记录，无法删除'];
        }

        $result = $this->db->delete('students', 'id = ?', [$id]);

        // 记录日志
        $auth = new Auth();
        $auth->logOperation($auth->getUserId(), 'student', 'delete', "删除学生ID: $id");

        return ['success' => true, 'affected' => $result];
    }

    /**
     * 获取学生所在宿舍信息
     */
    public function getStudentRoom($studentId) {
        $sql = "SELECT
                    ra.*,
                    r.room_number,
                    r.floor,
                    r.bed_count,
                    r.current_occupancy,
                    b.building_name,
                    b.building_code,
                    b.gender_type as building_gender
                FROM room_assignments ra
                JOIN rooms r ON ra.room_id = r.id
                JOIN dormitory_buildings b ON r.building_id = b.id
                WHERE ra.student_id = ? AND ra.status = 1";

        return $this->db->getRow($sql, [$studentId]);
    }

    /**
     * 获取学院列表
     */
    public function getColleges() {
        $result = $this->db->getAll("SELECT DISTINCT college FROM students WHERE college IS NOT NULL AND college != '' ORDER BY college");
        return array_column($result, 'college');
    }

    /**
     * 获取专业列表
     */
    public function getMajors() {
        $result = $this->db->getAll("SELECT DISTINCT major FROM students WHERE major IS NOT NULL AND major != '' ORDER BY major");
        return array_column($result, 'major');
    }

    /**
     * 统计学生信息
     */
    public function getStats() {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM students) as total_students,
                    (SELECT COUNT(*) FROM students WHERE gender = 1) as male_students,
                    (SELECT COUNT(*) FROM students WHERE gender = 2) as female_students,
                    (SELECT COUNT(*) FROM students WHERE status = 1) as active_students,
                    (SELECT COUNT(*) FROM students WHERE status = 2) as graduated_students,
                    (SELECT COUNT(*) FROM students WHERE status = 3) as suspended_students,
                    (SELECT COUNT(DISTINCT student_id) FROM students) as unique_students";

        return $this->db->getRow($sql);
    }

    /**
     * 搜索学生（自动补全）
     */
    public function search($keyword, $limit = 10) {
        $sql = "SELECT
                    id,
                    student_id,
                    real_name,
                    college,
                    major
                FROM students
                WHERE (student_id LIKE ? OR real_name LIKE ?)
                AND status = 1
                ORDER BY student_id
                LIMIT ?";

        $params = ["%$keyword%", "%$keyword%", $limit];
        return $this->db->getAll($sql, $params);
    }

    /**
     * 导出学生数据
     */
    public function exportData($filters = []) {
        $where = "1 = 1";
        $params = [];

        if (!empty($filters['college'])) {
            $where .= " AND college = ?";
            $params[] = $filters['college'];
        }

        if (isset($filters['status'])) {
            $where .= " AND status = ?";
            $params[] = $filters['status'];
        }

        $sql = "SELECT
                    student_id as 学号,
                    real_name as 姓名,
                    CASE gender WHEN 1 THEN '男' WHEN 2 THEN '女' ELSE '未知' END as 性别,
                    college as 学院,
                    major as 专业,
                    class_name as 班级,
                    phone as 联系电话,
                    CASE status WHEN 1 THEN '在读' WHEN 2 THEN '毕业' WHEN 3 THEN '休学' ELSE '未知' END as 状态,
                    entrance_date as 入学日期
                FROM students
                WHERE $where
                ORDER BY student_id";

        return $this->db->getAll($sql, $params);
    }
}