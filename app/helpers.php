<?php
/**
 * 辅助函数集合
 */

/**
 * 安全输出HTML
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * JSON响应
 */
function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * 重定向
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * 获取POST数据
 */
function getPost($key = null, $default = null) {
    if ($key === null) {
        return $_POST;
    }
    return $_POST[$key] ?? $default;
}

/**
 * 获取GET数据
 */
function getGet($key = null, $default = null) {
    if ($key === null) {
        return $_GET;
    }
    return $_GET[$key] ?? $default;
}

/**
 * 验证CSRF令牌
 */
function verifyCsrfToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return $_POST['csrf_token'] === $_SESSION['csrf_token'];
}

/**
 * 生成CSRF令牌
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 生成随机字符串
 */
function randomString($length = 16) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $str;
}

/**
 * 格式化日期
 */
function formatDate($date, $format = 'Y-m-d') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * 格式化时间
 */
function formatTime($time, $format = 'Y-m-d H:i:s') {
    if (!$time) return '';
    return date($format, strtotime($time));
}

/**
 * 获取当前学期
 */
function getCurrentSemester() {
    $month = date('n');
    $year = date('Y');

    if ($month >= 9 && $month <= 12) {
        return $year . '-' . ($year + 1) . ' 第一学期';
    } elseif ($month >= 1 && $month <= 2) {
        return ($year - 1) . '-' . $year . ' 第二学期';
    } else {
        return $year . '-' . ($year + 1) . ' 第二学期';
    }
}

/**
 * 生成宿舍房间号
 */
function generateRoomNumber($floor, $roomNum) {
    return str_pad($floor, 2, '0', STR_PAD_LEFT) . str_pad($roomNum, 2, '0', STR_PAD_LEFT);
}

/**
 * 计算入住率
 */
function calculateOccupancyRate($current, $total) {
    if ($total == 0) return 0;
    return round(($current / $total) * 100, 2);
}

/**
 * 获取性别名称
 */
function getGenderName($gender) {
    $genders = [1 => '男', 2 => '女'];
    return $genders[$gender] ?? '未知';
}

/**
 * 获取状态名称
 */
function getStatusName($status, $type = 'room') {
    if ($type == 'room') {
        $statuses = [1 => '正常', 2 => '维修中', 3 => '停用'];
    } elseif ($type == 'student') {
        $statuses = [1 => '在读', 2 => '毕业', 3 => '休学'];
    } elseif ($type == 'assignment') {
        $statuses = [1 => '在住', 2 => '已退宿'];
    } elseif ($type == 'application') {
        $statuses = [0 => '待审核', 1 => '通过', 2 => '拒绝'];
    } else {
        $statuses = [1 => '正常', 0 => '禁用'];
    }
    return $statuses[$status] ?? '未知';
}

/**
 * 文件上传处理
 */
function uploadFile($file, $subDir = '') {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => '无效的文件上传'];
    }

    // 检查错误
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => '文件上传失败'];
    }

    // 检查文件大小
    $maxSize = UPLOAD_MAX_SIZE ?? (10 * 1024 * 1024);
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => '文件大小超过限制'];
    }

    // 检查文件类型
    $allowedTypes = UPLOAD_ALLOWED_TYPES ?? ['jpg', 'jpeg', 'png', 'pdf'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => '不支持的文件类型'];
    }

    // 生成唯一文件名
    $filename = uniqid() . '_' . time() . '.' . $extension;

    // 创建上传目录
    $uploadDir = UPLOAD_PATH . $subDir;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filepath = $uploadDir . $filename;

    // 移动文件
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => '/uploads/' . $subDir . $filename
        ];
    }

    return ['success' => false, 'message' => '文件保存失败'];
}

/**
 * 导出CSV
 */
function exportCsv($filename, $headers, $data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // BOM for Excel
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // 写入表头
    fputcsv($output, $headers);

    // 写入数据
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

/**
 * 导出Excel (简单CSV格式)
 */
function exportExcel($filename, $headers, $data) {
    exportCsv($filename . '.csv', $headers, $data);
}

/**
 * 导入CSV
 * @param string $filePath 文件路径
 * @param callable $callback 回调函数
 * @param string $encoding 输入编码 (auto, gbk, utf-8, gb2312)
 */
function importCsv($filePath, $callback, $encoding = 'auto') {
    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => '文件不存在'];
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        return ['success' => false, 'message' => '无法打开文件'];
    }

    // 读取表头（需要处理编码）
    $headers = fgetcsv($handle);
    if ($headers === false) {
        fclose($handle);
        return ['success' => false, 'message' => '无法读取CSV表头'];
    }

    // 转换表头编码
    $headers = array_map(function($item) use ($encoding) {
        return convertEncoding($item, $encoding);
    }, $headers);

    $rowNum = 1;
    $successCount = 0;
    $errorCount = 0;
    $errors = [];

    while (($data = fgetcsv($handle)) !== false) {
        $rowNum++;

        // 转换数据行编码
        $data = array_map(function($item) use ($encoding) {
            return convertEncoding($item, $encoding);
        }, $data);

        try {
            $result = $callback($data, $headers, $rowNum);
            if ($result === true) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "第{$rowNum}行: " . $result;
            }
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "第{$rowNum}行: " . $e->getMessage();
        }
    }

    fclose($handle);

    return [
        'success' => true,
        'success_count' => $successCount,
        'error_count' => $errorCount,
        'errors' => $errors,
        'total' => $successCount + $errorCount
    ];
}

/**
 * 自动检测并转换字符串编码
 * @param string $str 输入字符串
 * @param string $encoding auto|gbk|utf-8|gb2312
 * @return string UTF-8编码的字符串
 */
function convertEncoding($str, $encoding = 'auto') {
    if ($str === null || $str === '') {
        return $str;
    }

    // 如果指定了UTF-8，直接返回
    if (strtolower($encoding) === 'utf-8' || strtolower($encoding) === 'utf8') {
        return $str;
    }

    // 自动检测编码
    if (strtolower($encoding) === 'auto') {
        // 检测是否已经是UTF-8
        if (mb_check_encoding($str, 'UTF-8')) {
            return $str;
        }

        // 检测是否为GBK/GB2312
        if (function_exists('mb_detect_encoding')) {
            $detected = mb_detect_encoding($str, ['GBK', 'GB2312', 'UTF-8'], true);
            if ($detected === 'GBK' || $detected === 'GB2312') {
                return mb_convert_encoding($str, 'UTF-8', $detected);
            }
        }

        // 如果检测失败，尝试GBK转换
        $converted = @iconv('GBK', 'UTF-8//IGNORE', $str);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return $str;
    }

    // 明确指定编码转换
    if (strtolower($encoding) === 'gbk' || strtolower($encoding) === 'gb2312') {
        return mb_convert_encoding($str, 'UTF-8', $encoding);
    }

    return $str;
}

/**
 * 生成统计图表数据
 */
function generateChartData($labels, $data, $type = 'bar') {
    return [
        'type' => $type,
        'data' => [
            'labels' => $labels,
            'datasets' => [[
                'label' => '数据统计',
                'data' => $data,
                'backgroundColor' => 'rgba(102, 126, 234, 0.5)',
                'borderColor' => 'rgba(102, 126, 234, 1)',
                'borderWidth' => 2
            ]]
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false
        ]
    ];
}

/**
 * 发送邮件（简单实现）
 */
function sendEmail($to, $subject, $content) {
    // 这里可以集成PHPMailer等邮件库
    // 简单实现使用PHP mail函数
    $headers = "From: " . SYSTEM_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $body = "<html><body>$content</body></html>";

    return mail($to, $subject, $body, $headers);
}

/**
 * 记录系统日志
 */
function systemLog($level, $message) {
    $logDir = LOG_PATH;
    $logFile = $logDir . $level . '_' . date('Y-m-d') . '.log';
    $logMessage = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message);
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * 获取客户端IP
 */
function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }

    return $ip;
}

/**
 * 检查是否为Ajax请求
 */
function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * 渲染视图
 */
function view($viewName, $data = []) {
    extract($data);
    $viewPath = VIEW_PATH . $viewName . '.php';

    if (file_exists($viewPath)) {
        include $viewPath;
    } else {
        echo "视图文件不存在: " . h($viewName);
    }
}

/**
 * 获取配置项
 */
function getConfig($key, $default = null) {
    $db = Database::getInstance();
    $config = $db->getRow("SELECT config_value FROM sys_config WHERE config_key = ?", [$key]);
    return $config ? $config['config_value'] : $default;
}

/**
 * 设置配置项
 */
function setConfig($key, $value, $name = '', $desc = '') {
    $db = Database::getInstance();

    $exists = $db->count('sys_config', 'config_key = ?', [$key]) > 0;

    if ($exists) {
        $db->update('sys_config', ['config_value' => $value], 'config_key = ?', [$key]);
    } else {
        $db->insert('sys_config', [
            'config_key' => $key,
            'config_value' => $value,
            'config_name' => $name,
            'config_desc' => $desc
        ]);
    }
}