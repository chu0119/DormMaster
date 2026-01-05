<?php
/**
 * 用户认证类
 * 处理登录、登出、权限验证
 */

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 用户登录（增强安全版）
     * 修复：会话固定防护、登录失败限制
     */
    public function login($username, $password) {
        // 输入验证
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => '用户名和密码不能为空'];
        }

        $sql = "SELECT u.*, s.real_name as student_name, s.college, s.major
                FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                WHERE u.username = ? AND u.status = 1";

        $user = $this->db->getRow($sql, [$username]);

        if (!$user) {
            // 记录失败尝试（针对不存在的用户）
            $this->recordFailedLogin($username);
            return ['success' => false, 'message' => '用户不存在或已被禁用'];
        }

        // 检查账户锁定
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $lockTime = date('Y-m-d H:i:s', strtotime($user['locked_until']));
            return ['success' => false, 'message' => "账户已锁定，请稍后再试，解锁时间：$lockTime"];
        }

        // 验证密码
        if (!password_verify($password, $user['password'])) {
            $this->recordFailedLogin($username);
            $this->logOperation(0, 'auth', 'login_failed', "登录失败: $username", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            return ['success' => false, 'message' => '密码错误'];
        }

        // 重置失败次数
        $this->db->update('users', [
            'login_attempts' => 0,
            'last_login_attempt' => null,
            'locked_until' => null
        ], 'id = ?', [$user['id']]);

        // 更新最后登录时间
        $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

        // 重新生成会话ID（防止会话固定）
        session_regenerate_id(true);

        // 设置会话
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['real_name'] = $user['real_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['role_name'] = $this->getRoleName($user['role']);
        $_SESSION['last_activity'] = time(); // 会话超时检查

        // 学生额外信息
        if ($user['role'] == 4) {
            $_SESSION['student_name'] = $user['student_name'] ?? $user['real_name'];
            $_SESSION['college'] = $user['college'] ?? '';
            $_SESSION['major'] = $user['major'] ?? '';
        }

        // 记录登录成功日志
        $this->logOperation($user['id'], 'auth', 'login_success', "登录成功", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

        return ['success' => true, 'user' => $user];
    }

    /**
     * 记录登录失败（带锁定机制）
     */
    private function recordFailedLogin($username) {
        $this->db->query("
            UPDATE users
            SET login_attempts = login_attempts + 1,
                last_login_attempt = NOW(),
                locked_until = CASE
                    WHEN login_attempts >= 5 THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                    ELSE locked_until
                END
            WHERE username = ?
        ", [$username]);
    }

    /**
     * 用户登出
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            $this->logOperation($userId, 'auth', 'logout', "用户登出");
        }

        session_destroy();
        return true;
    }

    /**
     * 检查是否登录
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    /**
     * 获取当前用户ID
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * 获取当前用户信息
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $sql = "SELECT u.*, s.real_name as student_name, s.college, s.major, s.student_id
                FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                WHERE u.id = ?";

        return $this->db->getRow($sql, [$_SESSION['user_id']]);
    }

    /**
     * 检查权限
     */
    public function checkRole($requiredRole) {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $userRole = $_SESSION['role'] ?? 0;

        // 管理员拥有所有权限
        if ($userRole == 1) {
            return true;
        }

        return $userRole == $requiredRole;
    }

    /**
     * 检查是否为指定角色（支持数组）
     */
    public function checkRoleAny($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $userRole = $_SESSION['role'] ?? 0;

        if ($userRole == 1) {
            return true;
        }

        return in_array($userRole, (array)$roles);
    }

    /**
     * 获取角色名称
     */
    public function getRoleName($role) {
        $roles = [
            1 => '管理员',
            2 => '教师',
            3 => '宿管',
            4 => '学生'
        ];
        return $roles[$role] ?? '未知';
    }

    /**
     * 获取所有角色
     */
    public function getAllRoles() {
        return [
            1 => '管理员',
            2 => '教师',
            3 => '宿管',
            4 => '学生'
        ];
    }

    /**
     * 记录操作日志
     */
    public function logOperation($userId, $module, $action, $content, $ip = null) {
        if ($ip === null) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $this->db->insert('operation_logs', [
            'user_id' => $userId,
            'module' => $module,
            'action' => $action,
            'content' => $content,
            'ip_address' => $ip,
            'user_agent' => $userAgent
        ]);
    }

    /**
     * 生成密码哈希
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * 验证密码强度（增强版）
     */
    public function validatePassword($password) {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = '密码长度至少8位';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = '密码必须包含至少一个大写字母';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = '密码必须包含至少一个小写字母';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = '密码必须包含至少一个数字';
        }

        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errors[] = '密码必须包含至少一个特殊字符';
        }

        // 检查常见弱密码
        $weakPasswords = ['123456', 'password', 'qwerty', 'abc123', 'admin', '111111'];
        if (in_array(strtolower($password), $weakPasswords)) {
            $errors[] = '密码过于简单，请使用更复杂的密码';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'message' => empty($errors) ? '' : implode('；', $errors)
        ];
    }

    /**
     * 检查用户名是否存在
     */
    public function usernameExists($username) {
        $count = $this->db->count('users', 'username = ?', [$username]);
        return $count > 0;
    }

    /**
     * 创建用户
     */
    public function createUser($data) {
        // 检查用户名
        if ($this->usernameExists($data['username'])) {
            return ['success' => false, 'message' => '用户名已存在'];
        }

        // 验证密码强度
        $passwordCheck = $this->validatePassword($data['password']);
        if (!$passwordCheck['valid']) {
            return ['success' => false, 'message' => $passwordCheck['message']];
        }

        // 密码加密
        $data['password'] = $this->hashPassword($data['password']);

        // 插入用户
        $userId = $this->db->insert('users', [
            'username' => $data['username'],
            'password' => $data['password'],
            'real_name' => $data['real_name'] ?? '',
            'role' => $data['role'],
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'status' => 1
        ]);

        // 记录日志
        $currentUserId = $this->getUserId();
        $this->logOperation($currentUserId, 'user', 'create', "创建用户: {$data['username']}");

        return ['success' => true, 'user_id' => $userId];
    }

    /**
     * 强制重定向到登录页
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    /**
     * 强制重定向到指定角色页面
     */
    public function requireRole($roles) {
        $this->requireLogin();

        if (!$this->checkRoleAny($roles)) {
            // 根据当前角色重定向到对应首页
            $role = $_SESSION['role'];
            $redirects = [
                1 => 'admin/index.php',
                2 => 'teacher/index.php',
                3 => 'housekeeper/index.php',
                4 => 'student/index.php'
            ];

            $target = $redirects[$role] ?? 'login.php';
            header('Location: ' . $target);
            exit;
        }
    }
}