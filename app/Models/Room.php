<?php
/**
 * 宿舍房间模型
 */

class Room {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 获取房间列表
     */
    public function getList($page = 1, $pageSize = 10, $filters = []) {
        $where = "1 = 1";
        $params = [];

        if (!empty($filters['building_id'])) {
            $where .= " AND r.building_id = ?";
            $params[] = $filters['building_id'];
        }

        if (!empty($filters['floor'])) {
            $where .= " AND r.floor = ?";
            $params[] = $filters['floor'];
        }

        if (!empty($filters['keyword'])) {
            $where .= " AND (r.room_number LIKE ? OR r.room_name LIKE ?)";
            $keyword = "%" . $filters['keyword'] . "%";
            $params[] = $keyword;
            $params[] = $keyword;
        }

        if (isset($filters['status'])) {
            $where .= " AND r.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['gender_type'])) {
            $where .= " AND r.gender_type = ?";
            $params[] = $filters['gender_type'];
        }

        $sql = "SELECT
                    r.*,
                    b.building_name,
                    b.building_code,
                    t.template_name,
                    t.room_type,
                    t.area
                FROM rooms r
                LEFT JOIN dormitory_buildings b ON r.building_id = b.id
                LEFT JOIN room_templates t ON r.template_id = t.id
                WHERE $where
                ORDER BY r.building_id, r.floor, r.room_number";

        // 使用自定义查询以支持JOIN
        $stmt = $this->db->query($sql, $params);
        $total = $this->db->count('rooms', str_replace('r.', '', $where), $params);

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
     * 获取单个房间
     */
    public function getById($id) {
        $sql = "SELECT
                    r.*,
                    b.building_name,
                    b.building_code,
                    t.template_name,
                    t.room_type,
                    t.area,
                    t.has_balcony,
                    t.has_bathroom,
                    t.has_ac
                FROM rooms r
                LEFT JOIN dormitory_buildings b ON r.building_id = b.id
                LEFT JOIN room_templates t ON r.template_id = t.id
                WHERE r.id = ?";

        return $this->db->getRow($sql, [$id]);
    }

    /**
     * 获取房间内的学生列表
     */
    public function getRoomStudents($roomId) {
        $sql = "SELECT
                    s.*,
                    ra.bed_number,
                    ra.move_in_date,
                    ra.status as assignment_status
                FROM room_assignments ra
                JOIN students s ON ra.student_id = s.id
                WHERE ra.room_id = ? AND ra.status = 1
                ORDER BY ra.bed_number";

        return $this->db->getAll($sql, [$roomId]);
    }

    /**
     * 添加房间
     */
    public function add($data) {
        $data['current_occupancy'] = 0;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $id = $this->db->insert('rooms', $data);

        // 记录日志
        $auth = new Auth();
        $auth->logOperation($auth->getUserId(), 'room', 'add', "添加房间: {$data['building_id']}-{$data['floor']}-{$data['room_number']}");

        return $id;
    }

    /**
     * 批量添加房间
     */
    public function batchAdd($buildingId, $startFloor, $endFloor, $roomsPerFloor, $bedCount, $templateId = null) {
        $this->db->beginTransaction();

        try {
            $count = 0;
            for ($floor = $startFloor; $floor <= $endFloor; $floor++) {
                for ($roomNum = 1; $roomNum <= $roomsPerFloor; $roomNum++) {
                    $roomNumber = generateRoomNumber($floor, $roomNum);

                    $data = [
                        'building_id' => $buildingId,
                        'floor' => $floor,
                        'room_number' => $roomNumber,
                        'bed_count' => $bedCount,
                        'template_id' => $templateId,
                        'current_occupancy' => 0,
                        'status' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];

                    $this->db->insert('rooms', $data);
                    $count++;
                }
            }

            $this->db->commit();

            // 记录日志
            $auth = new Auth();
            $auth->logOperation($auth->getUserId(), 'room', 'batch_add',
                "批量添加房间: 楼栋{$buildingId}, {$startFloor}-{$endFloor}层, 共{$count}间");

            return ['success' => true, 'count' => $count];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 更新房间
     */
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $result = $this->db->update('rooms', $data, 'id = ?', [$id]);

        // 记录日志
        $auth = new Auth();
        $auth->logOperation($auth->getUserId(), 'room', 'update', "更新房间ID: $id");

        return $result;
    }

    /**
     * 删除房间
     */
    public function delete($id) {
        // 检查是否有学生入住
        $studentCount = $this->db->count('room_assignments', 'room_id = ? AND status = 1', [$id]);
        if ($studentCount > 0) {
            return ['success' => false, 'message' => '该房间有学生入住，无法删除'];
        }

        $result = $this->db->delete('rooms', 'id = ?', [$id]);

        // 记录日志
        $auth = new Auth();
        $auth->logOperation($auth->getUserId(), 'room', 'delete', "删除房间ID: $id");

        return ['success' => true, 'affected' => $result];
    }

    /**
     * 更新房间入住人数
     */
    public function updateOccupancy($roomId) {
        $count = $this->db->getOne("SELECT COUNT(*) FROM room_assignments WHERE room_id = ? AND status = 1", [$roomId]);
        $this->db->update('rooms', ['current_occupancy' => $count], 'id = ?', [$roomId]);
        return $count;
    }

    /**
     * 获取房间统计
     */
    public function getStats() {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM rooms WHERE status = 1) as total_rooms,
                    (SELECT SUM(bed_count) FROM rooms WHERE status = 1) as total_beds,
                    (SELECT SUM(current_occupancy) FROM rooms WHERE status = 1) as current_occupancy,
                    (SELECT COUNT(*) FROM rooms WHERE status = 2) as maintenance_rooms,
                    (SELECT COUNT(*) FROM rooms WHERE status = 3) as disabled_rooms";

        return $this->db->getRow($sql);
    }

    /**
     * 搜索房间（自动补全）
     */
    public function search($keyword, $buildingId = null) {
        $where = "r.status = 1 AND (r.room_number LIKE ? OR r.room_name LIKE ?)";
        $params = ["%$keyword%", "%$keyword%"];

        if ($buildingId) {
            $where .= " AND r.building_id = ?";
            $params[] = $buildingId;
        }

        $sql = "SELECT
                    r.id,
                    r.room_number,
                    r.room_name,
                    r.floor,
                    r.current_occupancy,
                    r.bed_count,
                    b.building_name
                FROM rooms r
                LEFT JOIN dormitory_buildings b ON r.building_id = b.id
                WHERE $where
                ORDER BY r.building_id, r.floor, r.room_number
                LIMIT 10";

        return $this->db->getAll($sql, $params);
    }

    /**
     * 获取空闲房间
     */
    public function getAvailableRooms($buildingId = null, $genderType = null) {
        $where = "r.status = 1 AND r.current_occupancy < r.bed_count";
        $params = [];

        if ($buildingId) {
            $where .= " AND r.building_id = ?";
            $params[] = $buildingId;
        }

        if ($genderType) {
            $where .= " AND r.gender_type = ?";
            $params[] = $genderType;
        }

        $sql = "SELECT
                    r.*,
                    b.building_name,
                    (r.bed_count - r.current_occupancy) as available_beds
                FROM rooms r
                LEFT JOIN dormitory_buildings b ON r.building_id = b.id
                WHERE $where
                ORDER BY available_beds DESC";

        return $this->db->getAll($sql, $params);
    }
}