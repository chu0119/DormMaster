<?php
/**
 * 宿舍楼管理
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Models/DormitoryBuilding.php';

$auth = new Auth();
$auth->requireRole([1]);

$buildingModel = new DormitoryBuilding();
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
                'building_name' => getPost('building_name'),
                'building_code' => getPost('building_code'),
                'address' => getPost('address'),
                'floor_count' => getPost('floor_count'),
                'gender_type' => getPost('gender_type'),
                'description' => getPost('description'),
                'status' => 1
            ];

            // 检查编码是否重复
            $exists = $db->count('dormitory_buildings', 'building_code = ?', [$data['building_code']]);
            if ($exists > 0) {
                $error = '楼栋编码已存在';
            } else {
                $id = $buildingModel->add($data);
                $message = "宿舍楼【{$data['building_name']}】添加成功";
            }

        } elseif ($action === 'edit') {
            $id = getPost('id');
            $data = [
                'building_name' => getPost('building_name'),
                'building_code' => getPost('building_code'),
                'address' => getPost('address'),
                'floor_count' => getPost('floor_count'),
                'gender_type' => getPost('gender_type'),
                'description' => getPost('description'),
                'status' => getPost('status')
            ];

            $buildingModel->update($id, $data);
            $message = '宿舍楼信息更新成功';

        } elseif ($action === 'delete') {
            $id = getPost('id');
            $result = $buildingModel->delete($id);

            if ($result['success']) {
                $message = '宿舍楼删除成功';
            } else {
                $error = $result['message'];
            }
        }
    }
}

// 获取列表
$page = getGet('page', 1);
$pageSize = 10;
$filters = [
    'keyword' => getGet('keyword', ''),
    'gender_type' => getGet('gender_type'),
    'status' => getGet('status')
];

$list = $buildingModel->getList($page, $pageSize, $filters);

// 获取统计
$stats = $buildingModel->getStats();

// 生成CSRF令牌
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>宿舍楼管理 - 管理端</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 { font-size: 22px; font-weight: 600; }
        .header-actions { display: flex; gap: 10px; }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary { background: white; color: #667eea; }
        .btn-primary:hover { background: #f0f0f0; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-link { background: transparent; color: #667eea; text-decoration: underline; }

        .container { max-width: 1200px; margin: 0 auto; padding: 30px; }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-title { font-size: 18px; font-weight: 600; color: #333; }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .filters input, .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
        }

        .filters input[type="text"] { width: 200px; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #666;
        }

        tr:hover { background: #f8f9fa; }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        .gender-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .gender-male { background: #dbeafe; color: #1e40af; }
        .gender-female { background: #fce7f3; color: #9d174d; }
        .gender-mixed { background: #e0e7ff; color: #4338ca; }

        .actions { display: flex; gap: 8px; }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active { display: flex; }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-title { font-size: 18px; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #999; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-group textarea { resize: vertical; min-height: 60px; }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .pagination {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            font-size: 13px;
        }

        .pagination a:hover { background: #f0f0f0; }
        .pagination .active { background: #667eea; color: white; border-color: #667eea; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-item h4 { font-size: 12px; opacity: 0.9; margin-bottom: 5px; }
        .stat-item .value { font-size: 24px; font-weight: 700; }

        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .back-btn:hover { background: rgba(255,255,255,0.3); }

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 10px; padding: 15px; }
            .container { padding: 15px; }
            table { display: block; overflow-x: auto; }
            .filters { flex-direction: column; }
            .filters input, .filters select { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏢 宿舍楼管理</h1>
        <div class="header-actions">
            <a href="index.php" class="btn back-btn">返回首页</a>
            <button class="btn btn-primary" onclick="openAddModal()">+ 添加宿舍楼</button>
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
        <div class="stats-grid">
            <div class="stat-item">
                <h4>宿舍楼总数</h4>
                <div class="value"><?php echo count($stats); ?></div>
            </div>
            <div class="stat-item" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <h4>男生楼</h4>
                <div class="value"><?php echo array_filter($stats, fn($s) => $s['gender_type'] == 1) ? count(array_filter($stats, fn($s) => $s['gender_type'] == 1)) : 0; ?></div>
            </div>
            <div class="stat-item" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <h4>女生楼</h4>
                <div class="value"><?php echo array_filter($stats, fn($s) => $s['gender_type'] == 2) ? count(array_filter($stats, fn($s) => $s['gender_type'] == 2)) : 0; ?></div>
            </div>
            <div class="stat-item" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                <h4>混合楼</h4>
                <div class="value"><?php echo array_filter($stats, fn($s) => $s['gender_type'] == 3) ? count(array_filter($stats, fn($s) => $s['gender_type'] == 3)) : 0; ?></div>
            </div>
        </div>

        <!-- 筛选和搜索 -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">宿舍楼列表</div>
            </div>
            <form method="GET" action="" class="filters">
                <input type="text" name="keyword" placeholder="搜索楼栋名称或编码" value="<?php echo h($filters['keyword']); ?>">
                <select name="gender_type">
                    <option value="">全部性别</option>
                    <option value="1" <?php echo $filters['gender_type'] == 1 ? 'selected' : ''; ?>>男生楼</option>
                    <option value="2" <?php echo $filters['gender_type'] == 2 ? 'selected' : ''; ?>>女生楼</option>
                    <option value="3" <?php echo $filters['gender_type'] == 3 ? 'selected' : ''; ?>>混合楼</option>
                </select>
                <select name="status">
                    <option value="">全部状态</option>
                    <option value="1" <?php echo $filters['status'] == 1 ? 'selected' : ''; ?>>正常</option>
                    <option value="0" <?php echo $filters['status'] == 0 ? 'selected' : ''; ?>>停用</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">筛选</button>
                <a href="buildings.php" class="btn btn-sm" style="background: #e0e0e0;">重置</a>
            </form>

            <!-- 列表 -->
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>楼栋名称</th>
                            <th>编码</th>
                            <th>性别</th>
                            <th>楼层数</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list['data'])): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                                    暂无数据，请添加宿舍楼
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($list['data'] as $building): ?>
                                <tr>
                                    <td><strong><?php echo h($building['building_name']); ?></strong></td>
                                    <td><?php echo h($building['building_code']); ?></td>
                                    <td>
                                        <span class="gender-badge <?php
                                            echo $building['gender_type'] == 1 ? 'gender-male' :
                                                 ($building['gender_type'] == 2 ? 'gender-female' : 'gender-mixed');
                                        ?>">
                                            <?php echo getGenderName($building['gender_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo h($building['floor_count']); ?>层</td>
                                    <td>
                                        <span class="status-badge <?php echo $building['status'] == 1 ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo getStatusName($building['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-primary" onclick="openEditModal(
                                                <?php echo $building['id']; ?>,
                                                '<?php echo addslashes($building['building_name']); ?>',
                                                '<?php echo addslashes($building['building_code']); ?>',
                                                '<?php echo addslashes($building['address'] ?? ''); ?>',
                                                <?php echo $building['floor_count']; ?>,
                                                <?php echo $building['gender_type']; ?>,
                                                '<?php echo addslashes($building['description'] ?? ''); ?>',
                                                <?php echo $building['status']; ?>
                                            )">编辑</button>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('确定要删除吗？此操作不可恢复。');">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $building['id']; ?>">
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

            <!-- 分页 -->
            <?php if ($list['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($list['current_page'] > 1): ?>
                        <a href="?page=<?php echo $list['current_page'] - 1; ?>&keyword=<?php echo urlencode($filters['keyword']); ?>&gender_type=<?php echo $filters['gender_type']; ?>&status=<?php echo $filters['status']; ?>">上一页</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $list['total_pages']; $i++): ?>
                        <?php if ($i == $list['current_page']): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&keyword=<?php echo urlencode($filters['keyword']); ?>&gender_type=<?php echo $filters['gender_type']; ?>&status=<?php echo $filters['status']; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($list['current_page'] < $list['total_pages']): ?>
                        <a href="?page=<?php echo $list['current_page'] + 1; ?>&keyword=<?php echo urlencode($filters['keyword']); ?>&gender_type=<?php echo $filters['gender_type']; ?>&status=<?php echo $filters['status']; ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 添加/编辑模态框 -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modalTitle">添加宿舍楼</div>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <form method="POST" action="" id="modalForm">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="">

                <div class="form-group">
                    <label>楼栋名称 *</label>
                    <input type="text" name="building_name" id="building_name" required placeholder="例如：1号楼、北区公寓A栋">
                </div>

                <div class="form-group">
                    <label>楼栋编码 *</label>
                    <input type="text" name="building_code" id="building_code" required placeholder="例如：B01、A02（唯一标识）">
                </div>

                <div class="form-group">
                    <label>地址</label>
                    <input type="text" name="address" id="address" placeholder="详细地址">
                </div>

                <div class="form-group">
                    <label>楼层数 *</label>
                    <input type="number" name="floor_count" id="floor_count" required min="1" max="50" value="6">
                </div>

                <div class="form-group">
                    <label>适用性别 *</label>
                    <select name="gender_type" id="gender_type" required>
                        <option value="1">男生楼</option>
                        <option value="2">女生楼</option>
                        <option value="3">混合楼</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>描述</label>
                    <textarea name="description" id="description" placeholder="可选描述信息"></textarea>
                </div>

                <div class="form-group" id="statusGroup" style="display: none;">
                    <label>状态</label>
                    <select name="status" id="status">
                        <option value="1">正常</option>
                        <option value="0">停用</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn" style="background: #e0e0e0;" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = '添加宿舍楼';
            document.getElementById('formAction').value = 'add';
            document.getElementById('formId').value = '';
            document.getElementById('modalForm').reset();
            document.getElementById('statusGroup').style.display = 'none';
            document.getElementById('modal').classList.add('active');
        }

        function openEditModal(id, name, code, address, floor, gender, desc, status) {
            document.getElementById('modalTitle').textContent = '编辑宿舍楼';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formId').value = id;
            document.getElementById('building_name').value = name;
            document.getElementById('building_code').value = code;
            document.getElementById('address').value = address;
            document.getElementById('floor_count').value = floor;
            document.getElementById('gender_type').value = gender;
            document.getElementById('description').value = desc;
            document.getElementById('status').value = status;
            document.getElementById('statusGroup').style.display = 'block';
            document.getElementById('modal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('modal').classList.remove('active');
        }

        // 点击模态框外部关闭
        document.getElementById('modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // ESC键关闭
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>