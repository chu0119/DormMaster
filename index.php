<?php
/**
 * 系统主入口 - 自动跳转到对应角色的首页
 */

session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Auth.php';
require_once __DIR__ . '/app/helpers.php';

$auth = new Auth();

// 检查是否已安装
if (!file_exists(__DIR__ . '/config/config.php')) {
    header('Location: install.php');
    exit;
}

// 如果未登录，跳转到登录页
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// 根据角色跳转到对应首页
$role = $_SESSION['role'];
$redirects = [
    1 => 'admin/index.php',
    2 => 'teacher/index.php',
    3 => 'housekeeper/index.php',
    4 => 'student/index.php'
];

if (isset($redirects[$role])) {
    header('Location: ' . $redirects[$role]);
} else {
    // 未知角色，强制登出
    $auth->logout();
    header('Location: login.php');
}

exit;