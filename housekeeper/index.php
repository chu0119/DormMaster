<?php
/**
 * 宿管端首页
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/helpers.php';

$auth = new Auth();
$auth->requireRole([3]); // 仅宿管

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();

// 获取宿管管辖的楼栋
$managerBuildings = $db->getAll("SELECT id, building_name, building_code FROM dormitory_buildings WHERE manager_id = ? AND status = 1", [$currentUser['id']]);

$buildingIds = array_column($managerBuildings, 'id');

// 统计信息
$stats = [];
if (!empty($buildingIds)) {
    $placeholders = implode(',', array_fill(0, count($buildingIds), '?'));

    // 房间统计
    $stats['room_count'] = $db->getOne("SELECT COUNT(*) FROM rooms WHERE building_id IN ($placeholders) AND status = 1", $buildingIds);
    $stats['total_beds'] = $db->getOne("SELECT SUM(bed_count) FROM rooms WHERE building_id IN ($placeholders) AND status = 1", $buildingIds);
    $stats['current_occupancy'] = $db->getOne("SELECT SUM(current_occupancy) FROM rooms WHERE building_id IN ($placeholders) AND status = 1", $buildingIds);
    $stats['empty_rooms'] = $db->getOne("SELECT COUNT(*) FROM rooms WHERE building_id IN ($placeholders) AND current_occupancy = 0 AND status = 1", $buildingIds);

    // 获取房间列表
    $rooms = $db->getAll("
        SELECT
            r.*,
            b.building_name,
            (SELECT COUNT(*) FROM room_assignments ra WHERE ra.room_id = r.id AND ra.status = 1) as current_occupancy_real
        FROM rooms r
        JOIN dormitory_buildings b ON r.building_id = b.id
        WHERE r.building_id IN ($placeholders) AND r.status = 1
        ORDER BY b.building_code, r.floor, r.room_number
        LIMIT 20
    ", $buildingIds);

    // 获取巡查记录
    $inspections = $db->getAll("
        SELECT
            i.*,
            r.room_number,
            b.building_name,
            u.real_name as inspector_name
        FROM inspection_records i
        JOIN rooms r ON i.room_id = r.id
        JOIN dormitory_buildings b ON r.building_id = b.id
        JOIN users u ON i.inspector_id = u.id
        WHERE r.building_id IN ($placeholders)
        ORDER BY i.inspection_date DESC
        LIMIT 10
    ", $buildingIds);
} else {
    $rooms = [];
    $inspections = [];
    $stats = ['room_count' => 0, 'total_beds' => 0, 'current_occupancy' => 0, 'empty_rooms' => 0];
}

// 处理巡查记录添加
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = '无效的安全令牌';
    } else {
        $action = getPost('action');

        if ($action === 'inspection') {
            $data = [
                'room_id' => getPost('room_id'),
                'inspector_id' => $auth->getUserId(),
                'inspection_date' => getPost('inspection_date'),
                'score' => getPost('score'),
                'issues' => getPost('issues'),
                'notes' => getPost('notes'),
                'status' => getPost('status')
            ];

            $db->insert('inspection_records', $data);
            $auth->logOperation($auth->getUserId(), 'inspection', 'add', "巡查记录: 房间 {$data['room_id']}");
            $message = '巡查记录添加成功';
        }
    }
}

// 获取所有可巡查的房间（宿管管辖的楼栋）
$availableRooms = [];
if (!empty($buildingIds)) {
    $placeholders = implode(',', array_fill(0, count($buildingIds), '?'));
    $availableRooms = $db->getAll("
        SELECT r.id, r.room_number, r.floor, b.building_name
        FROM rooms r
        JOIN dormitory_buildings b ON r.building_id = b.id
        WHERE r.building_id IN ($placeholders) AND r.status = 1
        ORDER BY b.building_code, r.floor, r.room_number
    ", $buildingIds);
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>宿管端 - <?php echo SYSTEM_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }

        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 22px; font-weight: 600; }
        .header-info { display: flex; align-items: center; gap: 20px; }
        .user-info { background: rgba(255, 255, 255, 0.2); padding: 8px 16px; border-radius: 20px; backdrop-filter: blur(10px); }
        .logout-btn { background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.3); padding: 8px 16px; border-radius: 20px; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .logout-btn:hover { background: rgba(255, 255, 255, 0.3); transform: translateY(-2px); }

        .container { max-width: 1200px; margin: 0 auto; padding: 30px; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 18px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-left: 4px solid #667eea; }
        .stat-card h3 { color: #666; font-size: 12px; font-weight: 500; margin-bottom: 6px; text-transform: uppercase; }
        .stat-card .value { font-size: 24px; font-weight: 700; color: #333; }

        .content-section { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .section-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }

        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.3s; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; font-weight: 600; color: #666; }
        tr:hover { background: #f8f9fa; }

        .occupancy-bar { width: 100%; height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-top: 4px; }
        .occupancy-fill { height: 100%; background: linear-gradient(90deg, #10b981, #3b82f6); transition: width 0.3s; }

        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-normal { background: #d1fae5; color: #065f46; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-danger { background: #fee2e2; color: #991b1b; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; padding: 25px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .modal-title { font-size: 18px; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #999; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; min-height: 60px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .form-actions .btn { min-width: 80px; }

        .building-list { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
        .building-tag { background: #e0e7ff; color: #4338ca; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; }

        .info-box { background: #f0f9ff; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 12px; color: #0369a1; border-left: 3px solid #0ea5e9; }

        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; margin-top: 40px; }

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 10px; padding: 15px; }
            .container { padding: 15px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            table { display: block; overflow-x: auto; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏠 宿管端</h1>
        <div class="header-info">
            <div class="user-info">
                <?php echo h($currentUser['real_name']); ?>
            </div>
            <a href="../admin/logout.php" class="logout-btn">退出登录</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert success"><?php echo h($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <!-- 管辖楼栋 -->
        <div class="content-section">
            <div class="section-title">管辖楼栋</div>
            <?php if (empty($managerBuildings)): ?>
                <p style="color: #999;">您尚未被分配管辖楼栋，请联系管理员</p>
            <?php else: ?>
                <div class="building-list">
                    <?php foreach ($managerBuildings as $b): ?>
                        <div class="building-tag"><?php echo h($b['building_name']); ?> (<?php echo h($b['building_code']); ?>)</div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 统计卡片 -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>房间总数</h3>
                <div class="value"><?php echo $stats['room_count']; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #10b981;">
                <h3>总床位</h3>
                <div class="value"><?php echo $stats['total_beds']; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #3b82f6;">
                <h3>已入住</h3>
                <div class="value"><?php echo $stats['current_occupancy']; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #f59e0b;">
                <h3>空房间</h3>
                <div class="value"><?php echo $stats['empty_rooms']; ?></div>
            </div>
        </div>

        <!-- 房间列表 -->
        <div class="content-section">
            <div class="section-title">
                <span>房间状态（前20间）</span>
            </div>
            <?php if (empty($rooms)): ?>
                <p style="color: #999; text-align: center; padding: 20px;">暂无房间数据</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>房间</th>
                                <th>楼栋/楼层</th>
                                <th>床位</th>
                                <th>入住</th>
                                <th>入住率</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $r): ?>
                                <?php
                                    $rate = $r['bed_count'] > 0 ? round($r['current_occupancy'] / $r['bed_count'] * 100) : 0;
                                    $statusClass = $rate == 0 ? 'status-danger' : ($rate < 100 ? 'status-warning' : 'status-normal');
                                ?>
                                <tr>
                                    <td><strong><?php echo h($r['room_number']); ?></strong></td>
                                    <td>
                                        <?php echo h($r['building_name']); ?>
                                        <div style="font-size: 11px; color: #666;"><?php echo $r['floor']; ?>层</div>
                                    </td>
                                    <td><?php echo $r['bed_count']; ?>床</td>
                                    <td><?php echo $r['current_occupancy']; ?></td>
                                    <td>
                                        <div><?php echo $rate; ?>%</div>
                                        <div class="occupancy-bar">
                                            <div class="occupancy-fill" style="width: <?php echo $rate; ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo $rate == 0 ? '空置' : ($rate < 100 ? '未满' : '已满'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 巡查记录 -->
        <div class="content-section">
            <div class="section-title">
                <span>最近巡查记录</span>
                <button class="btn btn-primary btn-sm" onclick="openInspectionModal()">+ 添加巡查</button>
            </div>
            <?php if (empty($inspections)): ?>
                <p style="color: #999; text-align: center; padding: 20px;">暂无巡查记录</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>日期</th>
                                <th>房间</th>
                                <th>评分</th>
                                <th>问题</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inspections as $i): ?>
                                <tr>
                                    <td><?php echo h($i['inspection_date']); ?></td>
                                    <td>
                                        <?php echo h($i['building_name']); ?>-<?php echo h($i['room_number']); ?>
                                    </td>
                                    <td>
                                        <strong style="color: <?php echo $i['score'] >= 80 ? '#10b981' : ($i['score'] >= 60 ? '#f59e0b' : '#ef4444'); ?>">
                                            <?php echo $i['score']; ?>
                                        </strong>
                                    </td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo h($i['issues'] ?? '无'); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $i['status'] == 1 ? 'status-normal' : 'status-warning'; ?>">
                                            <?php echo $i['status'] == 1 ? '正常' : '需整改'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>智慧宿舍管理系统 - 宿管端</p>
            <p>当前学期：<?php echo getCurrentSemester(); ?></p>
        </div>
    </div>

    <!-- 巡查记录模态框 -->
    <div id="inspectionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">添加巡查记录</div>
                <button class="modal-close" onclick="closeInspectionModal()">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="action" value="inspection">

                <div class="form-row">
                    <div class="form-group">
                        <label>房间 *</label>
                        <select name="room_id" required>
                            <option value="">请选择房间</option>
                            <?php foreach ($availableRooms as $r): ?>
                                <option value="<?php echo $r['id']; ?>">
                                    <?php echo h($r['building_name']); ?>-<?php echo h($r['room_number']); ?> (<?php echo $r['floor']; ?>层)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>巡查日期 *</label>
                        <input type="date" name="inspection_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>评分 (0-100) *</label>
                        <input type="number" name="score" required min="0" max="100" placeholder="如：95">
                    </div>
                    <div class="form-group">
                        <label>状态 *</label>
                        <select name="status" required>
                            <option value="1">正常</option>
                            <option value="2">需整改</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>发现问题</label>
                    <textarea name="issues" placeholder="如：卫生差、物品损坏等"></textarea>
                </div>

                <div class="form-group">
                    <label>备注</label>
                    <textarea name="notes" placeholder="其他备注信息"></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn" style="background: #e0e0e0;" onclick="closeInspectionModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openInspectionModal() {
            document.getElementById('inspectionModal').classList.add('active');
        }

        function closeInspectionModal() {
            document.getElementById('inspectionModal').classList.remove('active');
        }

        // 点击模态框外部关闭
        document.getElementById('inspectionModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeInspectionModal();
            }
        });

        // ESC键关闭
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeInspectionModal();
            }
        });
    </script>
</body>
</html>