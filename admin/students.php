<?php
/**
 * 学生管理
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Models/Student.php';

$auth = new Auth();
$auth->requireRole([1]);

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

        if ($action === 'add') {
            $data = [
                'student_id' => getPost('student_id'),
                'real_name' => getPost('real_name'),
                'gender' => getPost('gender'),
                'college' => getPost('college'),
                'major' => getPost('major'),
                'class_name' => getPost('class_name'),
                'phone' => getPost('phone'),
                'id_card' => getPost('id_card'),
                'entrance_date' => getPost('entrance_date'),
                'status' => getPost('status')
            ];

            // 检查学号是否已存在
            $exists = $studentModel->getByStudentId($data['student_id']);
            if ($exists) {
                $error = '学号已存在';
            } else {
                $id = $studentModel->add($data);
                $message = "学生【{$data['real_name']}】添加成功";
            }

        } elseif ($action === 'edit') {
            $id = getPost('id');
            $data = [
                'real_name' => getPost('real_name'),
                'gender' => getPost('gender'),
                'college' => getPost('college'),
                'major' => getPost('major'),
                'class_name' => getPost('class_name'),
                'phone' => getPost('phone'),
                'id_card' => getPost('id_card'),
                'entrance_date' => getPost('entrance_date'),
                'status' => getPost('status')
            ];

            $studentModel->update($id, $data);
            $message = '学生信息更新成功';

        } elseif ($action === 'delete') {
            $id = getPost('id');
            $result = $studentModel->delete($id);

            if ($result['success']) {
                $message = '学生删除成功';
            } else {
                $error = $result['message'];
            }

        } elseif ($action === 'export') {
            // 导出数据
            $filters = [
                'college' => getPost('export_college'),
                'status' => getPost('export_status')
            ];

            $exportData = $studentModel->exportData($filters);
            $headers = ['学号', '姓名', '性别', '学院', '专业', '班级', '联系电话', '状态', '入学日期'];

            exportCsv('students_export', $headers, $exportData);
        }
    }
}

// 获取列表
$page = getGet('page', 1);
$pageSize = 15;
$filters = [
    'keyword' => getGet('keyword', ''),
    'college' => getGet('college'),
    'major' => getGet('major'),
    'gender' => getGet('gender'),
    'status' => getGet('status'),
    'has_room' => getGet('has_room')
];

$list = $studentModel->getList($page, $pageSize, $filters);

// 获取筛选选项
$colleges = $studentModel->getColleges();
$majors = $studentModel->getMajors();

// 获取统计
$stats = $studentModel->getStats();

// 生成CSRF令牌
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生管理 - 管理端</title>
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
        .btn-link { background: transparent; color: #667eea; text-decoration: underline; }

        .container { max-width: 1400px; margin: 0 auto; padding: 30px; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .card-title { font-size: 18px; font-weight: 600; color: #333; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 20px; }
        .stat-item { background: #f8f9fa; padding: 12px; border-radius: 8px; text-align: center; border-left: 3px solid #667eea; }
        .stat-item h4 { font-size: 11px; color: #666; margin-bottom: 4px; text-transform: uppercase; }
        .stat-item .value { font-size: 20px; font-weight: 700; color: #333; }

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
        .status-graduated { background: #e0e7ff; color: #3730a3; }
        .status-suspended { background: #fee2e2; color: #991b1b; }

        .gender-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .gender-male { background: #dbeafe; color: #1e40af; }
        .gender-female { background: #fce7f3; color: #9d174d; }

        .room-info { background: #f0f9ff; padding: 6px 8px; border-radius: 4px; font-size: 11px; color: #0369a1; display: inline-block; }
        .no-room { color: #999; font-style: italic; }

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

        .info-box { background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 12px; color: #666; border-left: 3px solid #667eea; }
        .warning-box { background: #fff7ed; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 12px; color: #9a3412; border-left: 3px solid #f59e0b; }

        .export-form { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; }

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 10px; padding: 15px; }
            .container { padding: 15px; }
            table { display: block; overflow-x: auto; }
            .filters { flex-direction: column; align-items: stretch; }
            .filters input, .filters select { width: 100%; }
            .form-row { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👨‍🎓 学生管理</h1>
        <div class="header-actions">
            <a href="index.php" class="btn back-btn">返回首页</a>
            <button class="btn btn-primary" onclick="showTab('single')">+ 添加学生</button>
            <button class="btn btn-success" onclick="showTab('import')">📥 导入</button>
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
                <h4>总人数</h4>
                <div class="value"><?php echo $stats['total_students']; ?></div>
            </div>
            <div class="stat-item">
                <h4>男生</h4>
                <div class="value"><?php echo $stats['male_students']; ?></div>
            </div>
            <div class="stat-item">
                <h4>女生</h4>
                <div class="value"><?php echo $stats['female_students']; ?></div>
            </div>
            <div class="stat-item">
                <h4>在读</h4>
                <div class="value"><?php echo $stats['active_students']; ?></div>
            </div>
            <div class="stat-item">
                <h4>毕业</h4>
                <div class="value"><?php echo $stats['graduated_students']; ?></div>
            </div>
        </div>

        <!-- 添加/导入表单区域 -->
        <div class="card" id="addFormCard" style="display: none;">
            <div class="card-header">
                <div class="card-title">学生信息管理</div>
                <button class="btn btn-sm" style="background: #e0e0e0;" onclick="hideAddForm()">关闭</button>
            </div>

            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('single')">单个添加</button>
                <button class="tab-btn" onclick="showTab('import')">批量导入</button>
                <button class="tab-btn" onclick="showTab('export')">数据导出</button>
            </div>

            <!-- 单个添加 -->
            <div id="tab-single" class="tab-content active">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="add">

                    <div class="form-row">
                        <div class="form-group">
                            <label>学号 *</label>
                            <input type="text" name="student_id" required placeholder="如：2021001">
                        </div>
                        <div class="form-group">
                            <label>姓名 *</label>
                            <input type="text" name="real_name" required placeholder="学生姓名">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>性别 *</label>
                            <select name="gender" required>
                                <option value="1">男</option>
                                <option value="2">女</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>入学日期 *</label>
                            <input type="date" name="entrance_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>学院 *</label>
                            <input type="text" name="college" required placeholder="如：计算机学院">
                        </div>
                        <div class="form-group">
                            <label>专业 *</label>
                            <input type="text" name="major" required placeholder="如：软件工程">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>班级</label>
                            <input type="text" name="class_name" placeholder="如：2021级1班">
                        </div>
                        <div class="form-group">
                            <label>联系电话</label>
                            <input type="text" name="phone" placeholder="手机号码">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>身份证号</label>
                            <input type="text" name="id_card" placeholder="18位身份证号">
                        </div>
                        <div class="form-group">
                            <label>状态 *</label>
                            <select name="status" required>
                                <option value="1">在读</option>
                                <option value="2">毕业</option>
                                <option value="3">休学</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn" style="background: #e0e0e0;">重置</button>
                        <button type="submit" class="btn btn-primary">添加</button>
                    </div>
                </form>
            </div>

            <!-- 批量导入 -->
            <div id="tab-import" class="tab-content">
                <div class="info-box">
                    <strong>CSV导入格式说明：</strong><br>
                    第一行必须为表头：学号,姓名,性别,学院,专业,班级,联系电话,身份证号,入学日期,状态<br>
                    性别：男/女；状态：在读/毕业/休学<br>
                    示例：2021001,张三,男,计算机学院,软件工程,2021级1班,13800138000,110101200001011234,2021-09-01,在读
                </div>

                <form method="POST" action="import.php" enctype="multipart/form-data" target="_blank">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="redirect" value="admin/students.php">

                    <div class="form-group">
                        <label>选择CSV文件</label>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn" style="background: #e0e0e0;" onclick="window.open('template_students.csv')">下载模板</button>
                        <button type="submit" class="btn btn-success">开始导入</button>
                    </div>
                </form>
            </div>

            <!-- 数据导出 -->
            <div id="tab-export" class="tab-content">
                <form method="POST" action="" id="exportForm">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="export">

                    <div class="export-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>学院筛选</label>
                                <select name="export_college">
                                    <option value="">全部学院</option>
                                    <?php foreach ($colleges as $college): ?>
                                        <option value="<?php echo h($college); ?>"><?php echo h($college); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>状态筛选</label>
                                <select name="export_status">
                                    <option value="">全部状态</option>
                                    <option value="1">在读</option>
                                    <option value="2">毕业</option>
                                    <option value="3">休学</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">📥 导出CSV</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 筛选和列表 -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">学生列表</div>
            </div>

            <form method="GET" action="" class="filters">
                <div class="filter-group">
                    <label>关键词</label>
                    <input type="text" name="keyword" placeholder="学号/姓名/电话" value="<?php echo h($filters['keyword']); ?>">
                </div>

                <div class="filter-group">
                    <label>学院</label>
                    <select name="college">
                        <option value="">全部</option>
                        <?php foreach ($colleges as $college): ?>
                            <option value="<?php echo h($college); ?>" <?php echo $filters['college'] == $college ? 'selected' : ''; ?>><?php echo h($college); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>专业</label>
                    <select name="major">
                        <option value="">全部</option>
                        <?php foreach ($majors as $major): ?>
                            <option value="<?php echo h($major); ?>" <?php echo $filters['major'] == $major ? 'selected' : ''; ?>><?php echo h($major); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>性别</label>
                    <select name="gender">
                        <option value="">全部</option>
                        <option value="1" <?php echo $filters['gender'] == 1 ? 'selected' : ''; ?>>男</option>
                        <option value="2" <?php echo $filters['gender'] == 2 ? 'selected' : ''; ?>>女</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>状态</label>
                    <select name="status">
                        <option value="">全部</option>
                        <option value="1" <?php echo $filters['status'] == 1 ? 'selected' : ''; ?>>在读</option>
                        <option value="2" <?php echo $filters['status'] == 2 ? 'selected' : ''; ?>>毕业</option>
                        <option value="3" <?php echo $filters['status'] == 3 ? 'selected' : ''; ?>>休学</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>宿舍</label>
                    <select name="has_room">
                        <option value="">全部</option>
                        <option value="1" <?php echo $filters['has_room'] == 1 ? 'selected' : ''; ?>>有宿舍</option>
                        <option value="0" <?php echo $filters['has_room'] == 0 ? 'selected' : ''; ?>>无宿舍</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label> </label>
                    <button type="submit" class="btn btn-primary btn-sm">筛选</button>
                </div>

                <div class="filter-group">
                    <label> </label>
                    <a href="students.php" class="btn btn-sm" style="background: #e0e0e0;">重置</a>
                </div>
            </form>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>学号</th>
                            <th>姓名</th>
                            <th>性别</th>
                            <th>学院/专业</th>
                            <th>班级</th>
                            <th>宿舍信息</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list['data'])): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                                    暂无学生数据，请先添加或导入
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($list['data'] as $student): ?>
                                <tr>
                                    <td><strong><?php echo h($student['student_id']); ?></strong></td>
                                    <td><?php echo h($student['real_name']); ?></td>
                                    <td>
                                        <span class="gender-badge <?php echo $student['gender'] == 1 ? 'gender-male' : 'gender-female'; ?>">
                                            <?php echo getGenderName($student['gender']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo h($student['college']); ?></div>
                                        <div style="font-size: 11px; color: #666;"><?php echo h($student['major']); ?></div>
                                    </td>
                                    <td><?php echo h($student['class_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($student['room_id']): ?>
                                            <span class="room-info">
                                                <?php echo h($student['building_name']); ?>-<?php echo h($student['room_number']); ?>-<?php echo $student['bed_number']; ?>床
                                            </span>
                                        <?php else: ?>
                                            <span class="no-room">未分配</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            $statusClass = $student['status'] == 1 ? 'status-active' : ($student['status'] == 2 ? 'status-graduated' : 'status-suspended');
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo getStatusName($student['status'], 'student'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-primary" onclick="openEditModal(
                                                <?php echo $student['id']; ?>,
                                                '<?php echo addslashes($student['real_name']); ?>',
                                                <?php echo $student['gender']; ?>,
                                                '<?php echo addslashes($student['college']); ?>',
                                                '<?php echo addslashes($student['major']); ?>',
                                                '<?php echo addslashes($student['class_name'] ?? ''); ?>',
                                                '<?php echo addslashes($student['phone'] ?? ''); ?>',
                                                '<?php echo addslashes($student['id_card'] ?? ''); ?>',
                                                '<?php echo $student['entrance_date']; ?>',
                                                <?php echo $student['status']; ?>
                                            )">编辑</button>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('确定要删除吗？');">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
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
                <div class="modal-title">编辑学生信息</div>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-row">
                    <div class="form-group">
                        <label>姓名 *</label>
                        <input type="text" name="real_name" id="edit_real_name" required>
                    </div>
                    <div class="form-group">
                        <label>性别 *</label>
                        <select name="gender" id="edit_gender" required>
                            <option value="1">男</option>
                            <option value="2">女</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>学院 *</label>
                        <input type="text" name="college" id="edit_college" required>
                    </div>
                    <div class="form-group">
                        <label>专业 *</label>
                        <input type="text" name="major" id="edit_major" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>班级</label>
                        <input type="text" name="class_name" id="edit_class_name">
                    </div>
                    <div class="form-group">
                        <label>联系电话</label>
                        <input type="text" name="phone" id="edit_phone">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>身份证号</label>
                        <input type="text" name="id_card" id="edit_id_card">
                    </div>
                    <div class="form-group">
                        <label>入学日期</label>
                        <input type="date" name="entrance_date" id="edit_entrance_date">
                    </div>
                </div>

                <div class="form-group">
                    <label>状态 *</label>
                    <select name="status" id="edit_status" required>
                        <option value="1">在读</option>
                        <option value="2">毕业</option>
                        <option value="3">休学</option>
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
        function openEditModal(id, realName, gender, college, major, className, phone, idCard, entranceDate, status) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_real_name').value = realName;
            document.getElementById('edit_gender').value = gender;
            document.getElementById('edit_college').value = college;
            document.getElementById('edit_major').value = major;
            document.getElementById('edit_class_name').value = className;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_id_card').value = idCard;
            document.getElementById('edit_entrance_date').value = entranceDate;
            document.getElementById('edit_status').value = status;
            document.getElementById('modal').classList.add('active');
        }

        // 关闭模态框
        function closeModal() {
            document.getElementById('modal').classList.remove('active');
        }

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

        // 导出表单提交
        document.getElementById('exportForm')?.addEventListener('submit', function(e) {
            // 允许提交，会触发下载
        });
    </script>
</body>
</html>