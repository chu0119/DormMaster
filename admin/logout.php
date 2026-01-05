<?php
/**
 * 登出处理
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/helpers.php';

$auth = new Auth();
$auth->logout();

header('Location: ../login.php');
exit;