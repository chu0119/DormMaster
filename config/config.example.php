<?php
/**
 * 系统配置文件示例
 * 请复制此文件为 config.php 并修改数据库配置
 */

// 数据库配置
define('DB_HOST', 'localhost');      // 数据库主机
define('DB_PORT', '3306');           // 数据库端口
define('DB_NAME', 'dormitory_system'); // 数据库名
define('DB_USER', 'root');           // 数据库用户名
define('DB_PASS', '');               // 数据库密码
define('DB_CHARSET', 'utf8mb4');     // 数据库字符集

// 系统配置
define('SYSTEM_NAME', '智慧宿舍管理系统');  // 系统名称
define('SYSTEM_VERSION', '1.0.0');          // 系统版本
define('SYSTEM_DEBUG', true);              // 调试模式

// 会话配置
define('SESSION_LIFETIME', 7200);           // 会话过期时间（秒）
define('COOKIE_LIFETIME', 86400 * 30);      // Cookie过期时间（秒）

// 上传配置
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 最大上传大小（10MB）
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'xls', 'csv']); // 允许的文件类型

// 令牌密钥（安装时自动生成）
define('TOKEN_SECRET', 'your-secret-key-change-this-in-production');

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
spl_autoload_register(function ($class) {
    $classFile = APP_PATH . str_replace('\\', '/', $class) . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
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
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $errorLog = sprintf(
        "[%s] Error: %s in %s on line %d\n",
        date('Y-m-d H:i:s'),
        $errstr,
        $errfile,
        $errline
    );
    file_put_contents(LOG_PATH . 'error_' . date('Y-m-d') . '.log', $errorLog, FILE_APPEND);

    if (SYSTEM_DEBUG) {
        echo "<div style='background:#ffebee; padding:10px; border-left:4px solid #f44336; margin:10px;'>";
        echo "<strong>错误:</strong> " . htmlspecialchars($errstr) . "<br>";
        echo "<strong>文件:</strong> " . htmlspecialchars($errfile) . ":" . $errline;
        echo "</div>";
    }
});

// 自动创建必要目录
$dirs = [UPLOAD_PATH, LOG_PATH];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>