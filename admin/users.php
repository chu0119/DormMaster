<?php
/**
 * 用户管理
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/helpers.php';

$auth = new Auth();
$auth->requireRole([1]);

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
            $result = $auth->createUser([
                'username' => getPost('username'),
                'password' => getPost('password'),
                'real_name' => getPost('real_name'),
                'role' => getPost('role'),
                'phone' => getPost('phone'),
                'email' => getPost('email')
            ]);

            if ($result['success']) {
                $message = "用户创建成功";
            } else {
                $error = $result['message'];
            }

        } elseif ($action === 'reset_password') {
            $userId = getPost('user_id');
            $newPassword = getPost('new_password');

            $passwordHash = $auth->hashPassword($newPassword);
            $db->update('users', ['password' => $passwordHash], 'id = ?', [$userId]);

            $auth->logOperation($auth->getUserId(), 'user', 'reset_password', "重置用户密码: $userId");
            $message = '密码重置成功';

        } elseif ($action === 'toggle_status') {
            $userId = getPost('user_id');
            $status = getPost('status');

            $db->update('users', ['status' => $status], 'id = ?', [$userId]);

            $auth->logOperation($auth->getUserId(), 'user', 'toggle_status', "切换用户状态: $userId -> $status");
            $message = '状态更新成功';

        } elseif ($action === 'delete') {
            $userId = getPost('user_id');

            // 检查是否是管理员自己
            if ($userId == $auth->getUserId()) {
                $error = '不能删除自己';
            } else {
                $db->delete('users', 'id = ?', [$userId]);
                $auth->logOperation($auth->getUserId(), 'user', 'delete', "删除用户: $userId");
                $message = '用户删除成功';
            }
        }
    }
}

// 获取用户列表
$page = getGet('page', 1);
$pageSize = 15;
$filters = [
    'keyword' => getGet('keyword', ''),
    'role' => getGet('role'),
    'status' => getGet('status')
];

$where = "1 = 1";
$params = [];

if (!empty($filters['keyword'])) {
    $where .= " AND (username LIKE ? OR real_name LIKE ? OR phone LIKE ?)";
    $keyword = "%" . $filters['keyword'] . "%";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
}

if (isset($filters['role'])) {
    $where .= " AND role = ?";
    $params[] = $filters['role'];
}

if (isset($filters['status'])) {
    $where .= " AND status = ?";
    $params[] = $filters['status'];
}

$users = $db->paginate('users', $page, $pageSize, $where, $params, 'id DESC');

// 获取角色列表
$roles = $auth->getAllRoles();

// 生成CSRF令牌
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 管理端</title>
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

        .container { max-width: 1200px; margin: 0 auto; padding: 30px; }

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

        .role-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .role-admin { background: #fee2e2; color: #991b1b; }
        .role-teacher { background: #dbeafe; color: #1e40af; }
        .role-housekeeper { background: #fef3c7; color: #92400e; }
        .role-student { background: #d1fae5; color: #065f46; }

        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        .actions { display: flex; gap: 6px; flex-wrap: wrap; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; padding: 25px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .modal-title { font-size: 18px; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #999; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .form-actions .btn { min-width: 80px; }

        .pagination { display: flex; gap: 5px; justify-content: center; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #333; font-size: 13px; }
        .pagination a:hover { background: #f0f0f0; }
        .pagination .active { background: #667eea; color: white; border-color: #667eea; }

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
        <h1>👥 用户管理</h1>
        <div class="header-actions">
            <a href="index.php" class="btn back-btn">返回首页</a>
            <button class="btn btn-primary" onclick="openAddModal()">+ 添加用户</button>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert success"><?php echo h($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div class="card-title">用户列表</div>
                <span style="font-size: 12px; color: #666;">共 <?php echo $users['total']; ?> 个用户</span>
            </div>

            <form method="GET" action="" class="filters">
                <div class="filter-group">
                    <label>关键词</label>
                    <input type="text" name="keyword" placeholder="用户名/姓名/电话" value="<?php echo h($filters['keyword']); ?>">
                </div>

                <div class="filter-group">
                    <label>角色</label>
                    <select name="role">
                        <option value="">全部</option>
                        <?php foreach ($roles as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $filters['role'] == $id ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>状态</label>
                    <select name="status">
                        <option value="">全部</option>
                        <option value="1" <?php echo $filters['status'] == 1 ? 'selected' : ''; ?>>正常</option>
                        <option value="0" <?php echo $filters['status'] == 0 ? 'selected' : ''; ?>>禁用</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label> </label>
                    <button type="submit" class="btn btn-primary btn-sm">筛选</button>
                </div>

                <div class="filter-group">
                    <label> </label>
                    <a href="users.php" class="btn btn-sm" style="background: #e0e0e0;">重置</a>
                </div>
            </form>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>用户名</th>
                            <th>姓名</th>
                            <th>角色</th>
                            <th>联系方式</th>
                            <th>最后登录</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users['data'])): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                                    暂无用户数据
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users['data'] as $user): ?>
                                <tr>
                                    <td><strong><?php echo h($user['username']); ?></strong></td>
                                    <td><?php echo h($user['real_name']); ?></td>
                                    <td>
                                        <?php
                                            $roleClass = '';
                                            switch($user['role']) {
                                                case 1: $roleClass = 'role-admin'; break;
                                                case 2: $roleClass = 'role-teacher'; break;
                                                case 3: $roleClass = 'role-housekeeper'; break;
                                                case 4: $roleClass = 'role-student'; break;
                                            }
                                        ?>
                                        <span class="role-badge <?php echo $roleClass; ?>">
                                            <?php echo $roles[$user['role']] ?? '未知'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo h($user['phone'] ?? '-'); ?></div>
                                        <div style="font-size: 11px; color: #666;"><?php echo h($user['email'] ?? '-'); ?></div>
                                    </td>
                                    <td><?php echo $user['last_login'] ? h($user['last_login']) : '从未登录'; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $user['status'] == 1 ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $user['status'] == 1 ? '正常' : '禁用'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-primary" onclick="openResetModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')">重置密码</button>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo $user['status'] == 1 ? 0 : 1; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $user['status'] == 1 ? 'btn-danger' : 'btn-success'; ?>">
                                                    <?php echo $user['status'] == 1 ? '禁用' : '启用'; ?>
                                                </button>
                                            </form>
                                            <?php if ($user['id'] != $auth->getUserId()): ?>
                                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('确定要删除吗？');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">删除</button>
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

            <?php if ($users['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($users['current_page'] > 1): ?>
                        <a href="?page=<?php echo $users['current_page'] - 1; ?>&<?php echo http_build_query($filters); ?>">上一页</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $users['total_pages']; $i++): ?>
                        <?php if ($i == $users['current_page']): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($users['current_page'] < $users['total_pages']): ?>
                        <a href="?page=<?php echo $users['current_page'] + 1; ?>&<?php echo http_build_query($filters); ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 添加用户模态框 -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">添加用户</div>
                <button class="modal-close" onclick="closeAddModal()">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="action" value="add">

                <div class="form-row">
                    <div class="form-group">
                        <label>用户名 *</label>
                        <input type="text" name="username" required placeholder="唯一用户名">
                    </div>
                    <div class="form-group">
                        <label>密码 *</label>
                        <input type="password" name="password" required placeholder="至少6位">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>姓名 *</label>
                        <input type="text" name="real_name" required placeholder="真实姓名">
                    </div>
                    <div class="form-group">
                        <label>角色 *</label>
                        <select name="role" required>
                            <?php foreach ($roles as $id => $name): ?>
                                <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>联系电话</label>
                        <input type="text" name="phone" placeholder="手机号码">
                    </div>
                    <div class="form-group">
                        <label>邮箱</label>
                        <input type="email" name="email" placeholder="电子邮箱">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn" style="background: #e0e0e0;" onclick="closeAddModal()">取消</button>
                    <button type="submit" class="btn btn-primary">创建</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 重置密码模态框 -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">重置密码</div>
                <button class="modal-close" onclick="closeResetModal()">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">

                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" id="reset_username" disabled style="background: #f5f5f5;">
                </div>

                <div class="form-group">
                    <label>新密码 *</label>
                    <input type="password" name="new_password" required placeholder="至少6位">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn" style="background: #e0e0e0;" onclick="closeResetModal()">取消</button>
                    <button type="submit" class="btn btn-primary">确认重置</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function openResetModal(userId, username) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_username').value = username;
            document.getElementById('resetModal').classList.add('active');
        }

        function closeResetModal() {
            document.getElementById('resetModal').classList.remove('active');
        }

        // 点击模态框外部关闭
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // ESC键关闭
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
    </script>
</body>
</html>