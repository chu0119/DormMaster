<?php
/**
 * 导入处理页面
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

// 检查CSRF令牌
if (!verifyCsrfToken()) {
    die('无效的安全令牌');
}

// 检查文件上传
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    die('文件上传失败');
}

$uploadedFile = $_FILES['csv_file'];
$redirect = $_POST['redirect'] ?? 'admin/students.php';

// 验证文件类型
$extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
if ($extension !== 'csv') {
    die('请上传CSV文件');
}

// 临时保存文件
$tempFile = $uploadedFile['tmp_name'];

// 解析并导入（自动处理GBK/UTF-8编码）
$result = importCsv($tempFile, function($data, $headers, $rowNum) use ($studentModel) {
    // 验证数据完整性
    if (count($data) < 10) {
        return "数据列数不足";
    }

    // 映射数据
    $studentData = [
        'student_id' => trim($data[0]),
        'real_name' => trim($data[1]),
        'gender' => trim($data[2]) == '男' ? 1 : 2,
        'college' => trim($data[3]),
        'major' => trim($data[4]),
        'class_name' => trim($data[5]),
        'phone' => trim($data[6]),
        'id_card' => trim($data[7]),
        'entrance_date' => trim($data[8]),
        'status' => trim($data[9]) == '在读' ? 1 : (trim($data[9]) == '毕业' ? 2 : 3)
    ];

    // 验证必填字段
    if (empty($studentData['student_id']) || empty($studentData['real_name']) || empty($studentData['college']) || empty($studentData['major'])) {
        return "必填字段缺失";
    }

    // 检查学号是否已存在
    $exists = $studentModel->getByStudentId($studentData['student_id']);
    if ($exists) {
        return "学号 {$studentData['student_id']} 已存在";
    }

    // 插入数据
    $studentModel->add($studentData);

    return true;
}, 'auto');

// 显示结果页面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>导入结果 - 学生管理</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; padding: 40px; }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .header h1 { font-size: 24px; color: #333; margin-bottom: 8px; }
        .header p { color: #666; font-size: 14px; }

        .result-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 3px solid #667eea;
        }

        .summary-item.success { border-left-color: #10b981; }
        .summary-item.error { border-left-color: #ef4444; }
        .summary-item.total { border-left-color: #667eea; }

        .summary-item .label { font-size: 12px; color: #666; margin-bottom: 5px; }
        .summary-item .value { font-size: 24px; font-weight: 700; color: #333; }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }

        .error-list {
            max-height: 300px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .error-item {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
            color: #721c24;
        }

        .error-item:last-child { border-bottom: none; }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            margin: 5px;
        }

        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }

        .btn-secondary { background: #e0e0e0; color: #333; }
        .btn-secondary:hover { background: #d0d0d0; }

        .actions { text-align: center; margin-top: 25px; }

        .info-box {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #666;
            border-left: 3px solid #667eea;
        }

        .loading {
            text-align: center;
            padding: 40px;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 600px) {
            .container { padding: 20px; margin: 10px; }
            .result-summary { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📥 数据导入结果</h1>
            <p>学生信息批量导入处理</p>
        </div>

        <?php if ($result['success']): ?>
            <div class="alert success">
                ✅ 导入完成！
            </div>

            <div class="result-summary">
                <div class="summary-item total">
                    <div class="label">总记录数</div>
                    <div class="value"><?php echo $result['total']; ?></div>
                </div>
                <div class="summary-item success">
                    <div class="label">成功</div>
                    <div class="value"><?php echo $result['success_count']; ?></div>
                </div>
                <div class="summary-item error">
                    <div class="label">失败</div>
                    <div class="value"><?php echo $result['error_count']; ?></div>
                </div>
            </div>

            <?php if ($result['error_count'] > 0): ?>
                <div class="alert warning">
                    ⚠️ 有 <?php echo $result['error_count']; ?> 条记录导入失败，请查看下方错误详情
                </div>

                <div class="error-list">
                    <strong style="display: block; margin-bottom: 10px;">错误详情：</strong>
                    <?php foreach ($result['errors'] as $error): ?>
                        <div class="error-item"><?php echo h($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert error">
                ❌ 导入失败：<?php echo h($result['message']); ?>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a href="<?php echo $redirect; ?>" class="btn btn-primary">返回列表</a>
            <a href="students.php" class="btn btn-secondary">学生管理</a>
        </div>
    </div>

    <script>
        // 3秒后自动跳转
        setTimeout(function() {
            const link = document.querySelector('.btn-primary');
            if (link) {
                link.style.textDecoration = 'underline';
            }
        }, 1000);
    </script>
</body>
</html>