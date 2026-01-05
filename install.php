<?php
/**
 * 系统安装向导
 * 首次运行时自动创建数据库表和初始数据
 */

session_start();

// 检查是否已安装
function isInstalled() {
    return file_exists(__DIR__ . '/config/config.php');
}

// 数据库连接测试
function testDBConnection($host, $port, $name, $user, $pass) {
    try {
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return ['success' => true, 'message' => '连接成功'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// 执行数据库初始化
function initDatabase($host, $port, $name, $user, $pass) {
    try {
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 读取SQL文件
        $sqlFile = __DIR__ . '/database.sql';
        if (!file_exists($sqlFile)) {
            return ['success' => false, 'message' => '数据库SQL文件不存在'];
        }

        $sqlContent = file_get_contents($sqlFile);

        // 替换数据库名
        $sqlContent = str_replace('`dormitory_system`', "`$name`", $sqlContent);

        // 执行SQL
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");

        // 分割SQL语句并执行
        $statements = array_filter(explode(';', $sqlContent));
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }

        return ['success' => true, 'message' => '数据库初始化成功'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// 生成配置文件
function generateConfigFile($host, $port, $name, $user, $pass) {
    $configContent = "<?php
/**
 * 系统配置文件
 * 自动生成于 " . date('Y-m-d H:i:s') . "
 */

// 数据库配置
define('DB_HOST', '$host');
define('DB_PORT', '$port');
define('DB_NAME', '$name');
define('DB_USER', '$user');
define('DB_PASS', '$pass');
define('DB_CHARSET', 'utf8mb4');

// 系统配置
define('SYSTEM_NAME', '智慧宿舍管理系统');
define('SYSTEM_VERSION', '1.0.0');
define('SYSTEM_DEBUG', false);

// 会话配置
define('SESSION_LIFETIME', 7200);
define('COOKIE_LIFETIME', 86400 * 30);

// 上传配置
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024);
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'xls', 'csv']);

// 令牌密钥
define('TOKEN_SECRET', '" . bin2hex(random_bytes(32)) . "');

// 路径配置
define('ROOT_PATH', dirname(__DIR__) . '/');
define('APP_PATH', ROOT_PATH . 'app/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('VIEW_PATH', ROOT_PATH . 'views/');
define('UPLOAD_PATH', ROOT_PATH . 'uploads/');
define('LOG_PATH', ROOT_PATH . 'logs/');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 自动加载函数
spl_autoload_register(function (\$class) {
    \$classFile = APP_PATH . str_replace('\\\\', '/', \$class) . '.php';
    if (file_exists(\$classFile)) {
        require_once \$classFile;
    }
});

// 错误处理
if (SYSTEM_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 自定义错误处理函数
set_error_handler(function(\$errno, \$errstr, \$errfile, \$errline) {
    \$errorLog = sprintf(
        \"[%s] Error: %s in %s on line %d\\n\",
        date('Y-m-d H:i:s'),
        \$errstr,
        \$errfile,
        \$errline
    );
    file_put_contents(LOG_PATH . 'error_' . date('Y-m-d') . '.log', \$errorLog, FILE_APPEND);

    if (SYSTEM_DEBUG) {
        echo \"<div style='background:#ffebee; padding:10px; border-left:4px solid #f44336; margin:10px;'>\";
        echo \"<strong>错误:</strong> \" . htmlspecialchars(\$errstr) . \"<br>\";
        echo \"<strong>文件:</strong> \" . htmlspecialchars(\$errfile) . \":\" . \$errline;
        echo \"</div>\";
    }
});

// 自动创建必要目录
\$dirs = [UPLOAD_PATH, LOG_PATH];
foreach (\$dirs as \$dir) {
    if (!is_dir(\$dir)) {
        mkdir(\$dir, 0755, true);
    }
}
?>";

    $configPath = __DIR__ . '/config/config.php';
    if (file_put_contents($configPath, $configContent)) {
        return ['success' => true, 'message' => '配置文件生成成功'];
    }
    return ['success' => false, 'message' => '配置文件写入失败，请检查目录权限'];
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test_connection') {
        $result = testDBConnection(
            $_POST['db_host'],
            $_POST['db_port'],
            $_POST['db_name'],
            $_POST['db_user'],
            $_POST['db_pass']
        );
        echo json_encode($result);
        exit;
    }

    if ($action === 'install') {
        $host = $_POST['db_host'];
        $port = $_POST['db_port'];
        $name = $_POST['db_name'];
        $user = $_POST['db_user'];
        $pass = $_POST['db_pass'];

        // 先测试连接
        $testResult = testDBConnection($host, $port, $name, $user, $pass);
        if (!$testResult['success']) {
            echo json_encode($testResult);
            exit;
        }

        // 初始化数据库
        $initResult = initDatabase($host, $port, $name, $user, $pass);
        if (!$initResult['success']) {
            echo json_encode($initResult);
            exit;
        }

        // 生成配置文件
        $configResult = generateConfigFile($host, $port, $name, $user, $pass);
        echo json_encode($configResult);
        exit;
    }
}

// 如果已安装，跳转到首页
if (isInstalled()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>智慧宿舍管理系统 - 安装向导</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .step {
            display: none;
        }

        .step.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f9f9f9;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .btn {
            width: 100%;
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            display: block;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
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

        .success-icon {
            font-size: 48px;
            color: #28a745;
            text-align: center;
            margin-bottom: 20px;
        }

        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #666;
            border-left: 4px solid #667eea;
        }

        .info-box strong {
            color: #333;
        }

        .progress-bar {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 33%;
            transition: width 0.3s;
        }

        .step-2 .progress-fill { width: 66%; }
        .step-3 .progress-fill { width: 100%; }

        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 10px;
        }

        .success-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .success-actions a {
            flex: 1;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        @media (max-width: 600px) {
            .container {
                padding: 25px;
                margin: 10px;
            }

            .header h1 {
                font-size: 24px;
            }

            .btn {
                padding: 12px 16px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏠 智慧宿舍管理系统</h1>
            <p>安装向导 - 首次配置数据库连接</p>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>

        <!-- 步骤1：数据库配置 -->
        <div class="step active" id="step1">
            <div class="info-box">
                <strong>数据库配置说明：</strong><br>
                请确保已创建空的MySQL数据库，并准备好连接信息。
                系统将自动创建所需的数据表并插入初始数据。
            </div>

            <form id="dbForm">
                <div class="form-group">
                    <label>数据库主机</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>数据库端口</label>
                    <input type="text" name="db_port" value="3306" required>
                </div>
                <div class="form-group">
                    <label>数据库名称</label>
                    <input type="text" name="db_name" value="dormitory_system" required>
                </div>
                <div class="form-group">
                    <label>数据库用户名</label>
                    <input type="text" name="db_user" value="root" required>
                </div>
                <div class="form-group">
                    <label>数据库密码</label>
                    <input type="password" name="db_pass" placeholder="请输入数据库密码">
                </div>

                <div id="testResult" class="alert"></div>

                <button type="button" class="btn btn-secondary" onclick="testConnection()">测试连接</button>
                <button type="button" class="btn btn-primary" onclick="installSystem()" id="installBtn">开始安装</button>
            </form>
        </div>

        <!-- 步骤2：安装中 -->
        <div class="step" id="step2">
            <div class="loading">
                <div class="spinner"></div>
                <p>正在初始化数据库，请稍候...</p>
                <p style="font-size: 12px; color: #999; margin-top: 10px;">创建数据表、插入初始数据、生成配置文件</p>
            </div>
        </div>

        <!-- 步骤3：安装完成 -->
        <div class="step" id="step3">
            <div class="success-icon">✅</div>
            <h3 style="text-align: center; margin-bottom: 20px; color: #333;">安装成功！</h3>

            <div class="info-box">
                <strong>默认管理员账号：</strong><br>
                用户名：admin<br>
                密码：admin123<br>
                <br>
                <strong>⚠️ 请登录后立即修改密码！</strong>
            </div>

            <div class="success-actions">
                <a href="index.php" class="btn-login">登录系统</a>
                <a href="install.php" class="btn-secondary" style="background: #f0f0f0; color: #333;">重新安装</a>
            </div>
        </div>
    </div>

    <script>
        function showStep(stepNumber) {
            document.querySelectorAll('.step').forEach(step => step.classList.remove('active'));
            document.getElementById('step' + stepNumber).classList.add('active');

            const progressFill = document.getElementById('progressFill');
            if (stepNumber === 1) progressFill.style.width = '33%';
            if (stepNumber === 2) progressFill.style.width = '66%';
            if (stepNumber === 3) progressFill.style.width = '100%';
        }

        function showAlert(message, type) {
            const alert = document.getElementById('testResult');
            alert.className = 'alert ' + type;
            alert.textContent = message;
            alert.style.display = 'block';
        }

        function hideAlert() {
            const alert = document.getElementById('testResult');
            alert.style.display = 'none';
        }

        async function testConnection() {
            const form = document.getElementById('dbForm');
            const formData = new FormData(form);
            formData.append('action', 'test_connection');

            hideAlert();

            try {
                const response = await fetch('install.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('✅ 数据库连接成功！', 'success');
                } else {
                    showAlert('❌ 连接失败：' + result.message, 'error');
                }
            } catch (error) {
                showAlert('❌ 请求失败：' + error.message, 'error');
            }
        }

        async function installSystem() {
            const form = document.getElementById('dbForm');
            const formData = new FormData(form);
            formData.append('action', 'install');

            const installBtn = document.getElementById('installBtn');
            installBtn.disabled = true;
            installBtn.textContent = '安装中...';

            // 切换到步骤2
            showStep(2);

            try {
                const response = await fetch('install.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // 延迟1秒显示成功页面
                    setTimeout(() => {
                        showStep(3);
                    }, 1000);
                } else {
                    alert('安装失败：' + result.message);
                    showStep(1);
                    installBtn.disabled = false;
                    installBtn.textContent = '开始安装';
                }
            } catch (error) {
                alert('安装过程中发生错误：' + error.message);
                showStep(1);
                installBtn.disabled = false;
                installBtn.textContent = '开始安装';
            }
        }

        // 回车键提交
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('#dbForm input');
            inputs.forEach(input => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        testConnection();
                    }
                });
            });
        });
    </script>
</body>
</html>