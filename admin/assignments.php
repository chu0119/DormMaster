<?php
/**
 * 宿舍分配管理
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Models/Room.php';
require_once __DIR__ . '/../app/Models/Student.php';

$auth = new Auth();
$auth->requireRole([1]);

$roomModel = new Room();
$studentModel = new Student();
$db = Database::getInstance();

// 处理操作
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = '无效的安全令牌';
    } else {
        $action = getPost('action');

        if ($action === 'assign') {
            $studentId = getPost('student_id');
            $roomId = getPost('room_id');
            $bedNumber = getPost('bed_number');
            $moveInDate = getPost('move_in_date');

            // 检查学生是否已有宿舍
            $existingAssignment = $db->getRow("SELECT * FROM room_assignments WHERE student_id = ? AND status = 1", [$studentId]);
            if ($existingAssignment) {
                $error = '该学生已有宿舍，请先退宿';
            } else {
                // 检查房间是否已满
                $room = $db->getRow("SELECT * FROM rooms WHERE id = ?", [$roomId]);
                if ($room['current_occupancy'] >= $room['bed_count']) {
                    $error = '该房间已满';
                } else {
                    // 检查床位是否已被占用
                    $bedCheck = $db->getRow("SELECT * FROM room_assignments WHERE room_id = ? AND bed_number = ? AND status = 1", [$roomId, $bedNumber]);
                    if ($bedCheck) {
                        $error = '该床位已被占用';
                    } else {
                        // 创建分配
                        $db->insert('room_assignments', [
                            'room_id' => $roomId,
                            'student_id' => $studentId,
                            'bed_number' => $bedNumber,
                            'move_in_date' => $moveInDate,
                            'status' => 1,
                            'created_by' => $auth->getUserId(),
                            'created_at' => date('Y-m-d H:i:s')
                        ]);

                        // 更新房间入住人数
                        $roomModel->updateOccupancy($roomId);

                        $student = $studentModel->getById($studentId);
                        $auth->logOperation($auth->getUserId(), 'assignment', 'assign',
                            "分配宿舍: {$student['real_name']} 到 {$room['building_id']}-{$room['room_number']}");

                        $message = "分配成功！{$student['real_name']} 已入住 {$room['room_number']}";
                    }
                }
            }

        } elseif ($action === 'move_out') {
            $assignmentId = getPost('assignment_id');
            $roomId = getPost('room_id');

            // 更新分配状态
            $db->update('room_assignments', [
                'status' => 2,
                'move_out_date' => date('Y-m-d')
            ], 'id = ?', [$assignmentId]);

            // 更新房间入住人数
            $roomModel->updateOccupancy($roomId);

            $auth->logOperation($auth->getUserId(), 'assignment', 'move_out', "退宿: 分配ID $assignmentId");
            $message = '退宿成功';

        } elseif ($action === 'batch_assign') {
            // 批量分配（简单实现：按顺序分配）
            $roomId = getPost('room_id');
            $studentIds = getPost('student_ids');
            $moveInDate = getPost('move_in_date');

            if (empty($studentIds)) {
                $error = '请至少选择一名学生';
            } else {
                $room = $db->getRow("SELECT * FROM rooms WHERE id = ?", [$roomId]);
                $currentOcc = $room['current_occupancy'];
                $bedCount = $room['bed_count'];
                $availableBeds = $bedCount - $currentOcc;

                if (count($studentIds) > $availableBeds) {
                    $error = "房间仅剩 $availableBeds 个床位，无法分配 " . count($studentIds) . " 名学生";
                } else {
                    $successCount = 0;
                    foreach ($studentIds as $index => $studentId) {
                        // 检查学生是否已有宿舍
                        $existing = $db->getRow("SELECT * FROM room_assignments WHERE student_id = ? AND status = 1", [$studentId]);
                        if ($existing) continue;

                        // 分配床位（从当前入住数+1开始）
                        $bedNumber = $currentOcc + $index + 1;

                        $db->insert('room_assignments', [
                            'room_id' => $roomId,
                            'student_id' => $studentId,
                            'bed_number' => $bedNumber,
                            'move_in_date' => $moveInDate,
                            'status' => 1,
                            'created_by' => $auth->getUserId(),
                            'created_at' => date('Y-m-d H:i:s')
                        ]);

                        $successCount++;
                    }

                    // 更新房间入住人数
                    $roomModel->updateOccupancy($roomId);

                    $auth->logOperation($auth->getUserId(), 'assignment', 'batch_assign',
                        "批量分配: $successCount 名学生到 {$room['room_number']}");

                    $message = "批量分配成功！共分配 $successCount 名学生";
                }
            }
        }
    }
}

// 获取分配列表
$page = getGet('page', 1);
$pageSize = 15;
$filters = [
    'keyword' => getGet('keyword', ''),
    'building_id' => getGet('building_id'),
    'status' => getGet('status', 1)
];

// 构建查询
$where = "ra.status = ?";
$params = [$filters['status']];

if (!empty($filters['keyword'])) {
    $where .= " AND (s.real_name LIKE ? OR s.student_id LIKE ? OR r.room_number LIKE ?)";
    $keyword = "%" . $filters['keyword'] . "%";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
}

if (!empty($filters['building_id'])) {
    $where .= " AND r.building_id = ?";
    $params[] = $filters['building_id'];
}

$sql = "SELECT
            ra.*,
            s.real_name,
            s.student_id,
            s.college,
            s.major,
            r.room_number,
            r.floor,
            b.building_name,
            b.building_code
        FROM room_assignments ra
        JOIN students s ON ra.student_id = s.id
        JOIN rooms r ON ra.room_id = r.id
        JOIN dormitory_buildings b ON r.building_id = b.id
        WHERE $where
        ORDER BY ra.move_in_date DESC";

$offset = ($page - 1) * $pageSize;
$sql .= " LIMIT $offset, $pageSize";

$assignments = $db->getAll($sql, $params);

// 获取总数
$countSql = "SELECT COUNT(*) as total FROM room_assignments ra
             JOIN students s ON ra.student_id = s.id
             JOIN rooms r ON ra.room_id = r.id
             WHERE $where";
$totalResult = $db->getRow($countSql, $params);
$total = $totalResult['total'];
$totalPages = ceil($total / $pageSize);

// 获取筛选选项
$buildings = $db->getAll("SELECT id, building_name FROM dormitory_buildings WHERE status = 1 ORDER BY building_code");

// 获取可分配的学生（无宿舍的学生）
$availableStudents = $db->getAll("
    SELECT s.id, s.student_id, s.real_name, s.college, s.major, s.gender
    FROM students s
    LEFT JOIN room_assignments ra ON s.id = ra.student_id AND ra.status = 1
    WHERE ra.id IS NULL AND s.status = 1
    ORDER BY s.student_id
");

// 处理学生搜索（AJAX支持）
if (isset($_GET['search_students'])) {
    $keyword = '%' . $_GET['search_students'] . '%';
    $searchResults = $db->getAll("
        SELECT s.id, s.student_id, s.real_name, s.college, s.major, s.gender
        FROM students s
        LEFT JOIN room_assignments ra ON s.id = ra.student_id AND ra.status = 1
        WHERE ra.id IS NULL AND s.status = 1
        AND (s.student_id LIKE ? OR s.real_name LIKE ? OR s.college LIKE ? OR s.major LIKE ?)
        ORDER BY s.student_id
        LIMIT 50
    ", [$keyword, $keyword, $keyword, $keyword]);

    header('Content-Type: application/json');
    echo json_encode($searchResults);
    exit;
}

// 获取可用房间
$availableRooms = $roomModel->getAvailableRooms();

// 生成CSRF令牌
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>宿舍分配 - 管理端</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; min-height: 100vh; }

        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 22px; font-weight: 600; }
        .header-actions { display: flex; gap: 10px; }

        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.3s; }
        .btn-primary { background: white; color: #667eea; }
        .btn-primary:hover { background: #f0f0f0; transform: translateY(-2px); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        .container { max-width: 1400px; margin: 0 auto; padding: 30px; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .card-title { font-size: 18px; font-weight: 600; color: #333; }

        .filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 11px; color: #666; font-weight: 600; }
        .filters input, .filters select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
        .filters input[type="text"] { width: 200px; }

        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; font-weight: 600; color: #666; }
        tr:hover { background: #f8f9fa; }

        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #e0e7ff; color: #3730a3; }

        .actions { display: flex; gap: 6px; flex-wrap: wrap; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; padding: 25px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .modal-title { font-size: 18px; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #999; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .form-actions .btn { min-width: 80px; }

        .pagination { display: flex; gap: 5px; justify-content: center; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #333; font-size: 13px; }
        .pagination a:hover { background: #f0f0f0; }
        .pagination .active { background: #667eea; color: white; border-color: #667eea; }

        .tab-buttons { display: flex; gap: 5px; margin-bottom: 15px; }
        .tab-btn { padding: 10px 20px; background: #f0f0f0; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .tab-btn.active { background: #667eea; color: white; }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* 学生选择器容器 */
        .student-selector-container {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
        }

        /* 搜索栏 */
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }

        .search-bar input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-count {
            font-size: 12px;
            color: #666;
            white-space: nowrap;
            padding: 5px 10px;
            background: white;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        /* 学生选择器双栏布局 */
        .student-selector-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            min-height: 350px;
        }

        .student-selector-left,
        .student-selector-right {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* 列表头部 */
        .list-header {
            background: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 13px;
        }

        .btn-select-all,
        .btn-clear-all {
            padding: 4px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-select-all {
            background: #667eea;
            color: white;
        }

        .btn-select-all:hover {
            background: #5568d3;
        }

        .btn-clear-all {
            background: #ef4444;
            color: white;
        }

        .btn-clear-all:hover {
            background: #dc2626;
        }

        /* 学生列表容器 */
        .student-list-new {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            max-height: 280px;
        }

        .student-list-new::-webkit-scrollbar {
            width: 6px;
        }

        .student-list-new::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .student-list-new::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }

        /* 学生卡片 */
        .student-card {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 8px;
            background: white;
            transition: all 0.2s;
            overflow: hidden;
        }

        .student-card:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
            transform: translateY(-1px);
        }

        .student-card.hidden {
            display: none;
        }

        .student-card-label {
            display: flex;
            align-items: center;
            padding: 10px;
            cursor: pointer;
            gap: 10px;
            width: 100%;
        }

        .student-card input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }

        .student-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .student-main {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .student-name {
            font-weight: 600;
            color: #333;
            min-width: 50px;
        }

        .student-id {
            color: #666;
            font-size: 11px;
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .gender-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 700;
            color: white;
        }

        .gender-badge.male {
            background: #3b82f6;
        }

        .gender-badge.female {
            background: #ec4899;
        }

        .student-sub {
            display: flex;
            gap: 8px;
            font-size: 11px;
            color: #666;
        }

        .college,
        .major {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
        }

        /* 已选学生列表 */
        .selected-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            min-height: 200px;
            max-height: 280px;
        }

        .selected-list::-webkit-scrollbar {
            width: 6px;
        }

        .selected-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .selected-list::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }

        .selected-item {
            background: #e8f5e9;
            border: 1px solid #10b981;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }

        .selected-item .info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .selected-item .name {
            font-weight: 600;
            color: #065f46;
        }

        .selected-item .meta {
            font-size: 10px;
            color: #047857;
        }

        .selected-item .remove-btn {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 10px;
            transition: all 0.2s;
        }

        .selected-item .remove-btn:hover {
            background: #dc2626;
        }

        /* 选中状态 */
        .student-card input[type="checkbox"]:checked ~ .student-info {
            opacity: 0.6;
        }

        .student-card input[type="checkbox"]:checked ~ .student-info .student-name {
            color: #10b981;
        }

        .student-card input[type="checkbox"]:checked {
            accent-color: #10b981;
        }

        /* 统计区域 */
        .selected-stats {
            border-top: 1px solid #e0e0e0;
            padding: 10px 15px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 600;
        }

        .selected-stats span {
            color: #333;
        }

        .selected-stats strong {
            color: #10b981;
            font-size: 14px;
        }

        /* 空状态 */
        .empty-state {
            text-align: center;
            color: #999;
            padding: 30px 20px;
            font-size: 13px;
            font-style: italic;
        }

        /* 选中学生为空时的样式 */
        .selected-list:has(.empty-state) {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* 响应式设计 */
        @media (max-width: 900px) {
            .student-selector-wrapper {
                grid-template-columns: 1fr;
            }

            .search-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-count {
                align-self: flex-end;
            }
        }

        /* 选中学生卡片高亮 */
        .student-card.selected {
            background: #e8f5e9;
            border-color: #10b981;
        }

        /* 搜索高亮 */
        .highlight {
            background: #fef08a;
            padding: 1px 2px;
            border-radius: 2px;
        }

        /* 房间信息提示 */
        .room-info-display {
            background: #e0f2fe;
            border: 1px solid #0284c7;
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 8px;
            font-size: 12px;
            color: #075985;
            display: none;
        }

        .room-info-display.show {
            display: block;
        }

        /* 旧的样式保持兼容 */
        .student-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px;
        }

        .student-item {
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .student-item:last-child { border-bottom: none; }

        .student-item label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            width: 100%;
        }

        .info-box {
            background: #f0f9ff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 12px;
            color: #0369a1;
            border-left: 3px solid #0ea5e9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            border-left: 3px solid #667eea;
        }

        .stat-item .label { font-size: 11px; color: #666; margin-bottom: 4px; }
        .stat-item .value { font-size: 20px; font-weight: 700; color: #333; }

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 10px; padding: 15px; }
            .container { padding: 15px; }
            table { display: block; overflow-x: auto; }
            .filters { flex-direction: column; align-items: stretch; }
            .filters input, .filters select { width: 100%; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔑 宿舍分配管理</h1>
        <div class="header-actions">
            <a href="index.php" class="btn back-btn">返回首页</a>
            <button class="btn btn-primary" onclick="showTab('single')">+ 单个分配</button>
            <button class="btn btn-success" onclick="showTab('batch')">+ 批量分配</button>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert success"><?php echo h($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <!-- 统计概览 -->
        <?php
            $totalAssignments = $db->getOne("SELECT COUNT(*) FROM room_assignments WHERE status = 1");
            $totalStudents = $db->getOne("SELECT COUNT(*) FROM students WHERE status = 1");
            $emptyRooms = $db->getOne("SELECT COUNT(*) FROM rooms WHERE current_occupancy = 0 AND status = 1");
        ?>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="label">已分配</div>
                <div class="value"><?php echo $totalAssignments; ?></div>
            </div>
            <div class="stat-item">
                <div class="label">在读学生</div>
                <div class="value"><?php echo $totalStudents; ?></div>
            </div>
            <div class="stat-item">
                <div class="label">空房间</div>
                <div class="value"><?php echo $emptyRooms; ?></div>
            </div>
            <div class="stat-item">
                <div class="label">分配率</div>
                <div class="value"><?php echo $totalStudents > 0 ? round($totalAssignments / $totalStudents * 100, 1) : 0; ?>%</div>
            </div>
        </div>

        <!-- 分配表单区域 -->
        <div class="card" id="assignFormCard" style="display: none;">
            <div class="card-header">
                <div class="card-title">宿舍分配</div>
                <button class="btn btn-sm" style="background: #e0e0e0;" onclick="hideAssignForm()">关闭</button>
            </div>

            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('single')">单个分配</button>
                <button class="tab-btn" onclick="showTab('batch')">批量分配</button>
            </div>

            <!-- 单个分配 -->
            <div id="tab-single" class="tab-content active">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="assign">

                    <div class="form-row">
                        <div class="form-group">
                            <label>选择学生 *</label>
                            <select name="student_id" required>
                                <option value="">请选择学生</option>
                                <?php foreach ($availableStudents as $s): ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo h($s['student_id']); ?> - <?php echo h($s['real_name']); ?>
                                        (<?php echo h($s['college']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: #666;">仅显示未分配宿舍的学生</small>
                        </div>
                        <div class="form-group">
                            <label>选择房间 *</label>
                            <select name="room_id" required>
                                <option value="">请选择房间</option>
                                <?php foreach ($availableRooms as $r): ?>
                                    <option value="<?php echo $r['id']; ?>">
                                        <?php echo h($r['building_name']); ?>-<?php echo h($r['room_number']); ?>
                                        (剩余<?php echo $r['available_beds']; ?>床)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>床位号 *</label>
                            <input type="number" name="bed_number" required min="1" max="12" placeholder="如：1">
                        </div>
                        <div class="form-group">
                            <label>入住日期 *</label>
                            <input type="date" name="move_in_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn" style="background: #e0e0e0;">重置</button>
                        <button type="submit" class="btn btn-primary">分配</button>
                    </div>
                </form>
            </div>

            <!-- 批量分配 -->
            <div id="tab-batch" class="tab-content">
                <form method="POST" action="" id="batchAssignForm">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="batch_assign">

                    <div class="info-box">
                        💡 <strong>批量分配流程：</strong> 1. 选择目标房间 → 2. 搜索并选择学生 → 3. 确认分配
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>目标房间 *</label>
                            <select name="room_id" id="batch_room_id" required>
                                <option value="">请选择房间</option>
                                <?php foreach ($availableRooms as $r): ?>
                                    <option value="<?php echo $r['id']; ?>">
                                        <?php echo h($r['building_name']); ?>-<?php echo h($r['room_number']); ?>
                                        (共<?php echo $r['bed_count']; ?>床，剩余<?php echo $r['available_beds']; ?>床)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>入住日期 *</label>
                            <input type="date" name="move_in_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <!-- 新的学生选择器 -->
                    <div class="form-group">
                        <label>选择学生（支持搜索和多选）</label>

                        <div class="student-selector-container">
                            <!-- 搜索区域 -->
                            <div class="search-bar">
                                <input type="text" id="studentSearch" placeholder="🔍 搜索学号、姓名、学院或专业..." autocomplete="off">
                                <span class="search-count" id="searchCount">共 <?php echo count($availableStudents); ?> 人</span>
                            </div>

                            <!-- 学生列表区域 -->
                            <div class="student-selector-wrapper">
                                <div class="student-selector-left">
                                    <div class="list-header">
                                        <span>可选学生</span>
                                        <button type="button" class="btn-select-all" id="selectAllBtn">全选</button>
                                    </div>
                                    <div class="student-list-new" id="studentList">
                                        <?php if (empty($availableStudents)): ?>
                                            <div class="empty-state">暂无可分配的学生</div>
                                        <?php else: ?>
                                            <?php foreach ($availableStudents as $s): ?>
                                                <div class="student-card" data-id="<?php echo $s['id']; ?>"
                                                     data-search="<?php echo strtolower($s['student_id'] . ' ' . $s['real_name'] . ' ' . $s['college'] . ' ' . $s['major']); ?>">
                                                    <label class="student-card-label">
                                                        <input type="checkbox" name="student_ids[]" value="<?php echo $s['id']; ?>" class="student-checkbox">
                                                        <div class="student-info">
                                                            <div class="student-main">
                                                                <span class="student-name"><?php echo h($s['real_name']); ?></span>
                                                                <span class="student-id"><?php echo h($s['student_id']); ?></span>
                                                                <span class="gender-badge <?php echo $s['gender'] == 1 ? 'male' : 'female'; ?>">
                                                                    <?php echo $s['gender'] == 1 ? '♂' : '♀'; ?>
                                                                </span>
                                                            </div>
                                                            <div class="student-sub">
                                                                <span class="college"><?php echo h($s['college']); ?></span>
                                                                <span class="major"><?php echo h($s['major']); ?></span>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="student-selector-right">
                                    <div class="list-header">
                                        <span>已选学生</span>
                                        <button type="button" class="btn-clear-all" id="clearAllBtn">清空</button>
                                    </div>
                                    <div class="selected-list" id="selectedList">
                                        <div class="empty-state">请从左侧选择学生</div>
                                    </div>
                                    <div class="selected-stats">
                                        <span>已选: <strong id="selectedCount">0</strong> 人</span>
                                        <span id="availableBedsInfo">房间剩余: -</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn" style="background: #e0e0e0;" onclick="resetBatchForm()">重置</button>
                        <button type="submit" class="btn btn-success" onclick="return validateBatchAssign();">批量分配</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 分配列表 -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">分配记录</div>
            </div>

            <form method="GET" action="" class="filters">
                <div class="filter-group">
                    <label>关键词</label>
                    <input type="text" name="keyword" placeholder="姓名/学号/房间号" value="<?php echo h($filters['keyword']); ?>">
                </div>

                <div class="filter-group">
                    <label>宿舍楼</label>
                    <select name="building_id">
                        <option value="">全部</option>
                        <?php foreach ($buildings as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo $filters['building_id'] == $b['id'] ? 'selected' : ''; ?>>
                                <?php echo h($b['building_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>状态</label>
                    <select name="status">
                        <option value="1" <?php echo $filters['status'] == 1 ? 'selected' : ''; ?>>在住</option>
                        <option value="2" <?php echo $filters['status'] == 2 ? 'selected' : ''; ?>>已退宿</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label> </label>
                    <button type="submit" class="btn btn-primary btn-sm">筛选</button>
                </div>

                <div class="filter-group">
                    <label> </label>
                    <a href="assignments.php" class="btn btn-sm" style="background: #e0e0e0;">重置</a>
                </div>
            </form>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>学生信息</th>
                            <th>学号</th>
                            <th>宿舍位置</th>
                            <th>床位</th>
                            <th>入住日期</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                                    暂无分配记录
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $assign): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo h($assign['real_name']); ?></strong>
                                        <div style="font-size: 11px; color: #666;">
                                            <?php echo h($assign['college']); ?> - <?php echo h($assign['major']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo h($assign['student_id']); ?></td>
                                    <td>
                                        <?php echo h($assign['building_name']); ?>-<?php echo h($assign['room_number']); ?>
                                        <div style="font-size: 11px; color: #666;"><?php echo $assign['floor']; ?>层</div>
                                    </td>
                                    <td><strong><?php echo $assign['bed_number']; ?>号床</strong></td>
                                    <td><?php echo h($assign['move_in_date']); ?></td>
                                    <td>
                                        <?php if ($assign['status'] == 1): ?>
                                            <span class="status-badge status-active">在住</span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">已退宿</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($assign['status'] == 1): ?>
                                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('确定要办理退宿吗？');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="move_out">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $assign['id']; ?>">
                                                    <input type="hidden" name="room_id" value="<?php echo $assign['room_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">退宿</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">上一页</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 显示/隐藏分配表单
        function showAssignForm() {
            document.getElementById('assignFormCard').style.display = 'block';
        }

        function hideAssignForm() {
            document.getElementById('assignFormCard').style.display = 'none';
        }

        // 切换标签页
        function showTab(tab) {
            document.getElementById('assignFormCard').style.display = 'block';

            // 更新按钮状态
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            // 更新内容显示
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
        }

        // 批量分配表单的交互功能
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('studentSearch');
            const studentList = document.getElementById('studentList');
            const selectedList = document.getElementById('selectedList');
            const selectedCount = document.getElementById('selectedCount');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const clearAllBtn = document.getElementById('clearAllBtn');
            const searchCount = document.getElementById('searchCount');
            const batchRoomSelect = document.getElementById('batch_room_id');
            const availableBedsInfo = document.getElementById('availableBedsInfo');

            // 房间选择变化时更新可用床位信息
            if (batchRoomSelect) {
                batchRoomSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        const text = selectedOption.text;
                        const match = text.match(/剩余(\d+)床/);
                        if (match) {
                            availableBedsInfo.textContent = `房间剩余: ${match[1]}床`;
                            availableBedsInfo.style.color = '#0284c7';
                        }
                    } else {
                        availableBedsInfo.textContent = '房间剩余: -';
                    }
                });
            }

            // 搜索功能
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const keyword = this.value.toLowerCase().trim();
                    const cards = studentList.querySelectorAll('.student-card');
                    let visibleCount = 0;

                    cards.forEach(card => {
                        const searchData = card.getAttribute('data-search');
                        if (searchData.includes(keyword)) {
                            card.classList.remove('hidden');
                            visibleCount++;
                        } else {
                            card.classList.add('hidden');
                        }
                    });

                    // 更新计数
                    searchCount.textContent = `显示 ${visibleCount} / ${cards.length} 人`;
                });
            }

            // 学生卡片点击事件（支持点击整行选择）
            if (studentList) {
                studentList.addEventListener('click', function(e) {
                    // 如果点击的是卡片区域但不是checkbox本身
                    if (e.target.classList.contains('student-card') ||
                        e.target.classList.contains('student-card-label') ||
                        e.target.classList.contains('student-info') ||
                        e.target.classList.contains('student-main') ||
                        e.target.classList.contains('student-sub')) {

                        const card = e.target.closest('.student-card');
                        if (card) {
                            const checkbox = card.querySelector('input[type="checkbox"]');
                            checkbox.checked = !checkbox.checked;
                            updateSelectedList();
                        }
                    }
                });

                // 监听checkbox变化
                studentList.addEventListener('change', function(e) {
                    if (e.target.classList.contains('student-checkbox')) {
                        updateSelectedList();
                    }
                });
            }

            // 全选功能
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    const cards = studentList.querySelectorAll('.student-card:not(.hidden)');
                    const allChecked = Array.from(cards).every(card =>
                        card.querySelector('input[type="checkbox"]').checked
                    );

                    cards.forEach(card => {
                        const checkbox = card.querySelector('input[type="checkbox"]');
                        checkbox.checked = !allChecked;
                    });

                    updateSelectedList();
                });
            }

            // 清空选择
            if (clearAllBtn) {
                clearAllBtn.addEventListener('click', function() {
                    const checkboxes = studentList.querySelectorAll('input[type="checkbox"]:checked');
                    checkboxes.forEach(cb => cb.checked = false);
                    updateSelectedList();
                });
            }

            // 更新已选列表
            function updateSelectedList() {
                const checkboxes = studentList.querySelectorAll('input[type="checkbox"]:checked');
                const selectedData = [];

                checkboxes.forEach(checkbox => {
                    const card = checkbox.closest('.student-card');
                    const id = card.getAttribute('data-id');
                    const name = card.querySelector('.student-name').textContent;
                    const studentId = card.querySelector('.student-id').textContent;
                    const college = card.querySelector('.college').textContent;
                    const major = card.querySelector('.major').textContent;

                    selectedData.push({ id, name, studentId, college, major });
                });

                // 更新已选列表显示
                if (selectedData.length === 0) {
                    selectedList.innerHTML = '<div class="empty-state">请从左侧选择学生</div>';
                } else {
                    selectedList.innerHTML = selectedData.map(item => `
                        <div class="selected-item">
                            <div class="info">
                                <span class="name">${item.name}</span>
                                <span class="meta">${item.studentId} · ${item.college} · ${item.major}</span>
                            </div>
                            <button type="button" class="remove-btn" onclick="removeStudent('${item.id}')">移除</button>
                        </div>
                    `).join('');
                }

                // 更新计数
                selectedCount.textContent = selectedData.length;

                // 检查房间容量
                checkRoomCapacity(selectedData.length);
            }

            // 移除单个学生
            window.removeStudent = function(id) {
                const card = studentList.querySelector(`.student-card[data-id="${id}"]`);
                if (card) {
                    const checkbox = card.querySelector('input[type="checkbox"]');
                    checkbox.checked = false;
                    updateSelectedList();
                }
            };

            // 检查房间容量
            function checkRoomCapacity(selectedCount) {
                const selectedOption = batchRoomSelect.options[batchRoomSelect.selectedIndex];
                if (!selectedOption || !selectedOption.value) return;

                const text = selectedOption.text;
                const match = text.match(/剩余(\d+)床/);
                if (match) {
                    const availableBeds = parseInt(match[1]);
                    const info = availableBedsInfo;

                    if (selectedCount > availableBeds) {
                        info.textContent = `⚠️ 超出容量! 需要 ${selectedCount} 床, 仅剩 ${availableBeds} 床`;
                        info.style.color = '#ef4444';
                        info.style.fontWeight = 'bold';
                    } else if (selectedCount > 0) {
                        info.textContent = `剩余: ${availableBeds - selectedCount} 床`;
                        info.style.color = '#10b981';
                        info.style.fontWeight = '600';
                    } else {
                        info.textContent = `房间剩余: ${availableBeds} 床`;
                        info.style.color = '#0284c7';
                        info.style.fontWeight = 'normal';
                    }
                }
            }

            // 验证批量分配表单
            window.validateBatchAssign = function() {
                const roomSelect = document.getElementById('batch_room_id');
                const selectedStudents = studentList.querySelectorAll('input[type="checkbox"]:checked');

                if (!roomSelect.value) {
                    alert('请选择目标房间！');
                    return false;
                }

                if (selectedStudents.length === 0) {
                    alert('请至少选择一名学生！');
                    return false;
                }

                // 检查容量
                const text = roomSelect.options[roomSelect.selectedIndex].text;
                const match = text.match(/剩余(\d+)床/);
                if (match) {
                    const availableBeds = parseInt(match[1]);
                    if (selectedStudents.length > availableBeds) {
                        alert(`房间仅剩余 ${availableBeds} 个床位，无法分配 ${selectedStudents.length} 名学生！`);
                        return false;
                    }
                }

                return confirm(`确定要批量分配 ${selectedStudents.length} 名学生吗？`);
            };

            // 重置批量表单
            window.resetBatchForm = function() {
                if (searchInput) searchInput.value = '';
                if (batchRoomSelect) batchRoomSelect.value = '';

                const checkboxes = studentList.querySelectorAll('input[type="checkbox"]:checked');
                checkboxes.forEach(cb => cb.checked = false);

                updateSelectedList();
                searchCount.textContent = `共 ${studentList.querySelectorAll('.student-card').length} 人`;
                availableBedsInfo.textContent = '房间剩余: -';
            };

            // 初始化
            updateSelectedList();
        });
    </script>
</body>
</html>