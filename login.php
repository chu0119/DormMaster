<?php
/**
 * 登录页面
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Auth.php';
require_once __DIR__ . '/app/helpers.php';

$auth = new Auth();

// 如果已登录，根据角色重定向
if ($auth->isLoggedIn()) {
    $role = $_SESSION['role'];
    $redirects = [
        1 => 'admin/index.php',
        2 => 'teacher/index.php',
        3 => 'housekeeper/index.php',
        4 => 'student/index.php'
    ];
    if (isset($redirects[$role])) {
        redirect($redirects[$role]);
    }
}

$error = '';
$success = '';

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = '无效的安全令牌';
    } else {
        $username = trim(getPost('username'));
        $password = getPost('password');

        if (empty($username) || empty($password)) {
            $error = '请输入用户名和密码';
        } else {
            $result = $auth->login($username, $password);

            if ($result['success']) {
                // 根据角色重定向
                $role = $result['user']['role'];
                $redirects = [
                    1 => 'admin/index.php',
                    2 => 'teacher/index.php',
                    3 => 'housekeeper/index.php',
                    4 => 'student/index.php'
                ];

                $redirect = getGet('redirect');
                if ($redirect && strpos($redirect, 'login.php') === false) {
                    redirect($redirect);
                } else {
                    redirect($redirects[$role] ?? 'login.php');
                }
            } else {
                $error = $result['message'];
            }
        }
    }
}

// 生成CSRF令牌
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo SYSTEM_NAME; ?></title>
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
            overflow: hidden;
        }

        /* 动态背景 */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); }
            25% { transform: translateY(-20px) translateX(10px); }
            50% { transform: translateY(10px) translateX(-10px); }
            75% { transform: translateY(-10px) translateX(5px); }
        }

        .login-container {
            position: relative;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 50px;
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transform: translateY(0);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            margin-bottom: 15px;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .logo h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo p {
            font-size: 13px;
            color: #666;
            letter-spacing: 1px;
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

        .input-wrapper {
            position: relative;
        }

        .input-wrapper::before {
            content: attr(data-icon);
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f9f9f9;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-group input:focus + .input-wrapper::before,
        .form-group input:not(:placeholder-shown) + .input-wrapper::before {
            color: #667eea;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-top: 10px;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
            animation: shake 0.5s;
        }

        .alert.error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
            display: block;
        }

        .alert.success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
            display: block;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .role-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
            border-left: 3px solid #667eea;
        }

        .role-info strong {
            color: #333;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }

        .version {
            color: #667eea;
            font-weight: 600;
        }

        /* 响应式 */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }

            .logo h1 {
                font-size: 20px;
            }

            .form-group input {
                padding: 12px 12px 12px 44px;
                font-size: 14px;
            }

            .btn-login {
                padding: 14px;
                font-size: 15px;
            }
        }

        /* 浮动粒子效果 */
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            pointer-events: none;
            animation: particleFloat 15s infinite;
        }

        @keyframes particleFloat {
            0% {
                transform: translateY(0) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(50px) rotate(360deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <!-- 浮动粒子 -->
    <div class="particle" style="width: 4px; height: 4px; left: 10%; animation-delay: 0s;"></div>
    <div class="particle" style="width: 6px; height: 6px; left: 30%; animation-delay: 2s;"></div>
    <div class="particle" style="width: 3px; height: 3px; left: 50%; animation-delay: 4s;"></div>
    <div class="particle" style="width: 5px; height: 5px; left: 70%; animation-delay: 6s;"></div>
    <div class="particle" style="width: 4px; height: 4px; left: 85%; animation-delay: 8s;"></div>

    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">🏠</div>
            <h1><?php echo SYSTEM_NAME; ?></h1>
            <p>DORMITORY MANAGEMENT SYSTEM</p>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?php echo h($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">

            <div class="form-group">
                <label>用户名</label>
                <div class="input-wrapper" data-icon="👤">
                    <input type="text" name="username" placeholder="请输入用户名" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label>密码</label>
                <div class="input-wrapper" data-icon="🔒">
                    <input type="password" name="password" placeholder="请输入密码" required>
                </div>
            </div>

            <button type="submit" class="btn-login">登 录</button>
        </form>

        <div class="role-info">
            <strong>💡 登录说明：</strong><br>
            • 管理员：admin / admin123<br>
            • 教师、宿管、学生：使用分配的账号登录
        </div>

        <div class="footer">
            <span class="version">v<?php echo SYSTEM_VERSION; ?></span> | 智慧宿舍管理系统
        </div>
    </div>

    <script>
        // 自动聚焦
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.querySelector('input[name="username"]');
            if (usernameInput && !usernameInput.value) {
                usernameInput.focus();
            }

            // 回车键提交
            document.querySelectorAll('input').forEach(input => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>