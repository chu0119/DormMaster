<?php
/**
 * 房间管理
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Models/DormitoryBuilding.php';
require_once __DIR__ . '/../app/Models/Room.php';

$auth = new Auth();
$auth->requireRole([1]);

$buildingModel = new DormitoryBuilding();
$roomModel = new Room();
$db = Database::getInstance();

// 处理操作
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = '无效的安全令牌';
    } else {
        $action = getPost('action');

        if ($action === 'add') {
            $data = [
                'building_id' => getPost('building_id'),
                'floor' => getPost('floor'),
                'room_number' => getPost('room_number'),
                'room_name' => getPost('room_name'),
                'bed_count' => getPost('bed_count'),
                'gender_type' => getPost('gender_type'),
                'template_id' => getPost('template_id'),
                'status' => 1
            ];

            $id = $roomModel->add($data);
            $message = "房间 {$data['room_number']} 添加成功";

        } elseif ($action === 'batch_add') {
            $buildingId = getPost('building_id');
            $startFloor = getPost('start_floor');
            $endFloor = getPost('end_floor');
            $roomsPerFloor = getPost('rooms_per_floor');
            $bedCount = getPost('bed_count');
            $templateId = getPost('template_id');

            $result = $roomModel->batchAdd($buildingId, $startFloor, $endFloor, $roomsPerFloor, $bedCount, $templateId);

            if ($result['success']) {
                $message = "批量添加成功！共添加 {$result['count']} 个房间";
            } else {
                $error = "批量添加失败：" . $result['message'];
            }

        } elseif ($action === 'edit') {
            $id = getPost('id');
            $data = [
                'room_name' => getPost('room_name'),
                'bed_count' => getPost('bed_count'),
                'gender_type' => getPost('gender_type'),
                'template_id' => getPost('template_id'),
                'status' => getPost('status'),
                'remark' => getPost('remark')
            ];

            $roomModel->update($id, $data);
            $roomModel->updateOccupancy($id); // 更新入住人数
            $message = '房间信息更新成功';

        } elseif ($action === 'delete') {
            $id = getPost('id');
            $result = $roomModel->delete($id);

            if ($result['success']) {
                $message = '房间删除成功';
            } else {
                $error = $result['message'];
            }
        }
    }
}

// 获取列表
$page = getGet('page', 1);
$pageSize = 15;
$filters = [
    'building_id' => getGet('building_id'),
    'floor' => getGet('floor'),
    'keyword' => getGet('keyword'),
    'status' => getGet('status'),
    'gender_type' => getGet('gender_type')
];

$list = $roomModel->getList($page, $pageSize, $filters);

// 获取所有宿舍楼（用于下拉选择）
$buildings = $buildingModel->getAll(1);

// 获取所有模板
$templates = $db->getAll("SELECT * FROM room_templates ORDER BY bed_count");

// 获取当前选中楼栋的楼层数
$floors = [];
if ($filters['building_id']) {
    $building = $buildingModel->getById($filters['building_id']);
    if ($building) {
        $floors = range(1, $building['floor_count']);
    }
}

// 生成CSRF令牌
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>房间管理 - 管理端</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; min-height: 100vh; }

        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 22px; font-weight: 600; }
        .header-actions { display: flex; gap: 10px; }

        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.3s; }
        .btn-primary { background: white; color: #667eea; }
        .btn-primary:hover { background: #f0f0f0; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-link { background: transparent; color: #667eea; text-decoration: underline; }

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
        .status-maintenance { background: #fef3c7; color: #92400e; }
        .status-disabled { background: #fee2e2; color: #991b1b; }

        .gender-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .gender-male { background: #dbeafe; color: #1e40af; }
        .gender-female { background: #fce7f3; color: #9d174d; }

        .occupancy-bar { width: 100%; height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-top: 4px; }
        .occupancy-fill { height: 100%; background: linear-gradient(90deg, #10b981, #3b82f6); transition: width 0.3s; }

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
        .form-group textarea { resize: vertical; min-height: 60px; }
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

        .info-box { background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 12px; color: #666; border-left: 3px solid #667eea; }

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
        <h1>🚪 房间管理</h1>
        <div class="header-actions">
            <a href="index.php" class="btn back-btn">返回首页</a>
            <button class="btn btn-primary" onclick="showTab('single')">+ 单个添加</button>
            <button class="btn btn-success" onclick="showTab('batch')">+ 批量添加</button>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert success"><?php echo h($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <!-- 添加表单区域 -->
        <div class="card" id="addFormCard" style="display: none;">
            <div class="card-header">
                <div class="card-title">添加房间</div>
                <button class="btn btn-sm" style="background: #e0e0e0;" onclick="hideAddForm()">关闭</button>
            </div>

            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('single')">单个添加</button>
                <button class="tab-btn" onclick="showTab('batch')">批量添加</button>
            </div>

            <!-- 单个添加 -->
            <div id="tab-single" class="tab-content active">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="add">

                    <div class="form-row">
                        <div class="form-group">
                            <label>宿舍楼 *</label>
                            <select name="building_id" required>
                                <option value="">请选择</option>
                                <?php foreach ($buildings as $b): ?>
                                    <option value="<?php echo $b['id']; ?>"><?php echo h($b['building_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>楼层 *</label>
                            <input type="number" name="floor" required min="1" max="50" placeholder="如：3">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>房间号 *</label>
                            <input type="text" name="room_number" required placeholder="如：301">
                        </div>
                        <div class="form-group">
                            <label>床位数 *</label>
                            <input type="number" name="bed_count" required min="1" max="12" placeholder="如：6">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>房间名称</label>
                            <input type="text" name="room_name" placeholder="如：301室">
                        </div>
                        <div class="form-group">
                            <label>适用性别 *</label>
                            <select name="gender_type" required>
                                <option value="1">男生</option>
                                <option value="2">女生</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>房间模板</label>
                        <select name="template_id">
                            <option value="">无模板</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo h($t['template_name']); ?> (<?php echo $t['bed_count']; ?>床)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn" style="background: #e0e0e0;">重置</button>
                        <button type="submit" class="btn btn-primary">添加</button>
                    </div>
                </form>
            </div>

            <!-- 批量添加 -->
            <div id="tab-batch" class="tab-content">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="batch_add">

                    <div class="info-box">
                        <strong>批量添加说明：</strong>系统将自动创建从起始楼层到结束楼层的房间，每层楼创建指定数量的房间。
                        例如：1-3层，每层8间，将创建 3 × 8 = 24 个房间。
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>宿舍楼 *</label>
                            <select name="building_id" required>
                                <option value="">请选择</option>
                                <?php foreach ($buildings as $b): ?>
                                    <option value="<?php echo $b['id']; ?>"><?php echo h($b['building_name']); ?> (<?php echo $b['floor_count']; ?>层)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>房间模板</label>
                            <select name="template_id">
                                <option value="">自定义</option>
                                <?php foreach ($templates as $t): ?>
                                    <option value="<?php echo $t['id']; ?>" data-beds="<?php echo $t['bed_count']; ?>">
                                        <?php echo h($t['template_name']); ?> (<?php echo $t['bed_count']; ?>床)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>起始楼层 *</label>
                            <input type="number" name="start_floor" required min="1" value="1">
                        </div>
                        <div class="form-group">
                            <label>结束楼层 *</label>
                            <input type="number" name="end_floor" required min="1" value="6">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>每层房间数 *</label>
                            <input type="number" name="rooms_per_floor" required min="1" value="8">
                        </div>
                        <div class="form-group">
                            <label>每间床位数 *</label>
                            <input type="number" name="bed_count" required min="1" max="12" value="6">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn" style="background: #e0e0e0;">重置</button>
                        <button type="submit" class="btn btn-success" onclick="return confirm('确定要批量添加房间吗？请仔细核对参数！');">批量添加</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 筛选和列表 -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">房间列表</div>
            </div>

            <form method="GET" action="" class="filters">
                <div class="filter-group">
                    <label>宿舍楼</label>
                    <select name="building_id" onchange="this.form.submit()">
                        <option value="">全部</option>
                        <?php foreach ($buildings as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo $filters['building_id'] == $b['id'] ? 'selected' : ''; ?>>
                                <?php echo h($b['building_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>楼层</label>
                    <select name="floor">
                        <option value="">全部</option>
                        <?php foreach ($floors as $f): ?>
                            <option value="<?php echo $f; ?>" <?php echo $filters['floor'] == $f ? 'selected' : ''; ?>><?php echo $f; ?>层</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>关键词</label>
                    <input type="text" name="keyword" placeholder="房间号/名称" value="<?php echo h($filters['keyword']); ?>">
                </div>

                <div class="filter-group">
                    <label>状态</label>
                    <select name="status">
                        <option value="">全部</option>
                        <option value="1" <?php echo $filters['status'] == 1 ? 'selected' : ''; ?>>正常</option>
                        <option value="2" <?php echo $filters['status'] == 2 ? 'selected' : ''; ?>>维修中</option>
                        <option value="3" <?php echo $filters['status'] == 3 ? 'selected' : ''; ?>>停用</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>性别</label>
                    <select name="gender_type">
                        <option value="">全部</option>
                        <option value="1" <?php echo $filters['gender_type'] == 1 ? 'selected' : ''; ?>>男生</option>
                        <option value="2" <?php echo $filters['gender_type'] == 2 ? 'selected' : ''; ?>>女生</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label> </label>
                    <button type="submit" class="btn btn-primary btn-sm">筛选</button>
                </div>

                <div class="filter-group">
                    <label> </label>
                    <a href="rooms.php" class="btn btn-sm" style="background: #e0e0e0;">重置</a>
                </div>
            </form>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>房间</th>
                            <th>楼栋/楼层</th>
                            <th>床位</th>
                            <th>入住</th>
                            <th>性别</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list['data'])): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                                    暂无房间数据，请先添加
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($list['data'] as $room): ?>
                                <?php
                                    $occupancyRate = calculateOccupancyRate($room['current_occupancy'], $room['bed_count']);
                                    $statusClass = $room['status'] == 1 ? 'status-active' : ($room['status'] == 2 ? 'status-maintenance' : 'status-disabled');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo h($room['room_number']); ?></strong>
                                        <?php if ($room['room_name']): ?>
                                            <div style="font-size: 11px; color: #666;"><?php echo h($room['room_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo h($room['building_name']); ?>
                                        <div style="font-size: 11px; color: #666;"><?php echo $room['floor']; ?>层</div>
                                    </td>
                                    <td>
                                        <?php echo $room['bed_count']; ?>床
                                        <div class="occupancy-bar">
                                            <div class="occupancy-fill" style="width: <?php echo $occupancyRate; ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo $room['current_occupancy']; ?></strong>
                                        <span style="font-size: 11px; color: #999;"> / <?php echo $room['bed_count']; ?></span>
                                        <div style="font-size: 11px; color: <?php echo $occupancyRate >= 100 ? '#ef4444' : '#10b981'; ?>">
                                            <?php echo $occupancyRate; ?>%
                                        </div>
                                    </td>
                                    <td>
                                        <span class="gender-badge <?php echo $room['gender_type'] == 1 ? 'gender-male' : 'gender-female'; ?>">
                                            <?php echo getGenderName($room['gender_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo getStatusName($room['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-primary" onclick="openEditModal(
                                                <?php echo $room['id']; ?>,
                                                '<?php echo addslashes($room['room_name'] ?? ''); ?>',
                                                <?php echo $room['bed_count']; ?>,
                                                <?php echo $room['gender_type']; ?>,
                                                <?php echo $room['template_id'] ?? 0; ?>,
                                                <?php echo $room['status']; ?>,
                                                '<?php echo addslashes($room['remark'] ?? ''); ?>'
                                            )">编辑</button>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('确定要删除吗？');">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $room['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">删除</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($list['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($list['current_page'] > 1): ?>
                        <a href="?page=<?php echo $list['current_page'] - 1; ?>&<?php echo http_build_query($filters); ?>">上一页</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $list['total_pages']; $i++): ?>
                        <?php if ($i == $list['current_page']): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($list['current_page'] < $list['total_pages']): ?>
                        <a href="?page=<?php echo $list['current_page'] + 1; ?>&<?php echo http_build_query($filters); ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 编辑模态框 -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">编辑房间</div>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group">
                    <label>房间名称</label>
                    <input type="text" name="room_name" id="edit_room_name" placeholder="如：301室">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>床位数</label>
                        <input type="number" name="bed_count" id="edit_bed_count" required min="1" max="12">
                    </div>
                    <div class="form-group">
                        <label>适用性别</label>
                        <select name="gender_type" id="edit_gender_type" required>
                            <option value="1">男生</option>
                            <option value="2">女生</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>房间模板</label>
                        <select name="template_id" id="edit_template_id">
                            <option value="">无模板</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo h($t['template_name']); ?> (<?php echo $t['bed_count']; ?>床)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>状态</label>
                        <select name="status" id="edit_status">
                            <option value="1">正常</option>
                            <option value="2">维修中</option>
                            <option value="3">停用</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>备注</label>
                    <textarea name="remark" id="edit_remark" placeholder="可选备注信息"></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn" style="background: #e0e0e0;" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 显示/隐藏添加表单
        function showAddForm() {
            document.getElementById('addFormCard').style.display = 'block';
        }

        function hideAddForm() {
            document.getElementById('addFormCard').style.display = 'none';
        }

        // 切换标签页
        function showTab(tab) {
            document.getElementById('addFormCard').style.display = 'block';

            // 更新按钮状态
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            // 更新内容显示
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
        }

        // 打开编辑模态框
        function openEditModal(id, roomName, bedCount, genderType, templateId, status, remark) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_room_name').value = roomName;
            document.getElementById('edit_bed_count').value = bedCount;
            document.getElementById('edit_gender_type').value = genderType;
            document.getElementById('edit_template_id').value = templateId;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_remark').value = remark;
            document.getElementById('modal').classList.add('active');
        }

        // 关闭模态框
        function closeModal() {
            document.getElementById('modal').classList.remove('active');
        }

        // 模板选择自动填充床位数
        document.addEventListener('DOMContentLoaded', function() {
            const templateSelect = document.querySelector('select[name="template_id"]');
            const bedCountInput = document.querySelector('input[name="bed_count"]');

            if (templateSelect && bedCountInput) {
                templateSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const beds = selectedOption.getAttribute('data-beds');
                    if (beds) {
                        bedCountInput.value = beds;
                    }
                });
            }

            // 批量添加模板选择
            const batchTemplateSelect = document.querySelector('#tab-batch select[name="template_id"]');
            const batchBedCountInput = document.querySelector('#tab-batch input[name="bed_count"]');

            if (batchTemplateSelect && batchBedCountInput) {
                batchTemplateSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const beds = selectedOption.getAttribute('data-beds');
                    if (beds) {
                        batchBedCountInput.value = beds;
                    }
                });
            }
        });

        // 点击模态框外部关闭
        document.getElementById('modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // ESC键关闭
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                hideAddForm();
            }
        });
    </script>
</body>
</html>