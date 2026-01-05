<?php
/**
 * 宿舍楼模型 - 修复版
 */

class DormitoryBuilding {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 获取宿舍楼列表
     */
    public function getList($page = 1, $pageSize = 10, $filters = []) {
        $where = "1 = 1";
        $params = [];

        if (!empty($filters['keyword'])) {
            $where .= " AND (building_name LIKE ? OR building_code LIKE ?)";
            $keyword = "%" . $filters['keyword'] . "%";
            $params[] = $keyword;
            $params[] = $keyword;
        }

        if (isset($filters['gender_type'])) {
            $where .= " AND gender_type = ?";
            $params[] = $filters['gender_type'];
        }

        if (isset($filters['status'])) {
            $where .= " AND status = ?";
            $params[] = $filters['status'];
        }

        return $this->db->paginate('dormitory_buildings', $page, $pageSize, $where, $params, 'id DESC');
    }

    /**
     * 获取单个宿舍楼
     */
    public function getById($id) {
        return $this->db->getRow("SELECT * FROM dormitory_buildings WHERE id = ?", [$id]);
    }

    /**
     * 获取所有宿舍楼（简单列表）- 修复版
     */
    public function getAll($status = null) {
        $sql = "SELECT id, building_name, building_code, gender_type, status, floor_count FROM dormitory_buildings WHERE 1 = 1";
        $params = [];

        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY building_code ASC";
        return $this->db->getAll($sql, $params);
    }

    /**
     * 添加宿舍楼
     */
    public function add($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $id = $this->db->insert('dormitory_buildings', $data);

        // 记录日志
        $auth = new Auth();
        $auth->logOperation($auth->getUserId(), 'building', 'add', "添加宿舍楼: {$data['building_name']}");

        return $id;
    }

    /**
     * 更新宿舍楼
     */
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $result = $this->db->update('dormitory_buildings', $data, 'id = ?', [$id]);

        // 记录日志
        $auth = new Auth();
        $auth->logOperation($auth->getUserId(), 'building', 'update', "更新宿舍楼ID: $id");

        return $result;
    }

    /**
     * 删除宿舍楼
     */
    public function delete($id) {
        // 检查是否有房间关联
        $roomCount = $this->db->count('rooms', 'building_id = ?', [$id]);
        if ($roomCount > 0) {
            return ['success' => false, 'message' => '该宿舍楼下还有房间，无法删除'];
        }

        $result = $this->db->delete('dormitory_buildings', 'id = ?', [$id]);

        // 记录日志
        $auth = new Auth();
        $auth->logOperation($auth->getUserId(), 'building', 'delete', "删除宿舍楼ID: $id");

        return ['success' => true, 'affected' => $result];
    }

    /**
     * 统计宿舍楼信息
     */
    public function getStats($buildingId = null) {
        $where = $buildingId ? "WHERE b.id = ?" : "WHERE 1 = 1";
        $params = $buildingId ? [$buildingId] : [];

        $sql = "SELECT
                    b.id,
                    b.building_name,
                    b.building_code,
                    b.gender_type,
                    COUNT(DISTINCT r.id) as room_count,
                    COALESCE(SUM(r.bed_count), 0) as total_beds,
                    COALESCE(SUM(r.current_occupancy), 0) as current_occupancy,
                    COALESCE(SUM(r.bed_count) - SUM(r.current_occupancy), 0) as available_beds
                FROM dormitory_buildings b
                LEFT JOIN rooms r ON b.id = r.building_id AND r.status = 1
                $where
                GROUP BY b.id
                ORDER BY b.building_code";

        return $this->db->getAll($sql, $params);
    }

    /**
     * 获取宿舍楼下的房间统计
     */
    public function getBuildingRoomStats($buildingId) {
        $sql = "SELECT
                    floor,
                    COUNT(*) as room_count,
                    COALESCE(SUM(bed_count), 0) as total_beds,
                    COALESCE(SUM(current_occupancy), 0) as current_occupancy,
                    COALESCE(SUM(bed_count) - SUM(current_occupancy), 0) as available_beds
                FROM rooms
                WHERE building_id = ? AND status = 1
                GROUP BY floor
                ORDER BY floor";

        return $this->db->getAll($sql, [$buildingId]);
    }
}
