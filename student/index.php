<?php
/**
 * 学生端首页
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Models/Student.php';

$auth = new Auth();
$auth->requireRole([4]); // 仅学生

$db = Database::getInstance();
$studentModel = new Student();

// 获取当前学生信息
$currentUser = $auth->getCurrentUser();
$studentId = $currentUser['id']; // 这里应该是学生表的ID

// 获取学生详细信息
$student = $studentModel->getById($studentId);

// 获取宿舍信息
$roomInfo = $studentModel->getStudentRoom($studentId);

// 获取室友信息
$roommates = [];
if ($roomInfo) {
    $roommates = $db->getAll("
        SELECT s.id, s.student_id, s.real_name, s.gender, s.college, s.major, ra.bed_number
        FROM room_assignments ra
        JOIN students s ON ra.student_id = s.id
        WHERE ra.room_id = ? AND ra.status = 1 AND s.id != ?
        ORDER BY ra.bed_number
    ", [$roomInfo['room_id'], $studentId]);
}

// 获取宿舍公告（示例）
$announcements = $db->getAll("
    SELECT * FROM sys_config
    WHERE config_key LIKE 'notice_%'
    ORDER BY created_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生端 - <?php echo SYSTEM_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }

        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 22px; font-weight: 600; }
        .header-info { display: flex; align-items: center; gap: 20px; }
        .user-info { background: rgba(255, 255, 255, 0.2); padding: 8px 16px; border-radius: 20px; backdrop-filter: blur(10px); }
        .logout-btn { background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.3); padding: 8px 16px; border-radius: 20px; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .logout-btn:hover { background: rgba(255, 255, 255, 0.3); transform: translateY(-2px); }

        .container { max-width: 1000px; margin: 0 auto; padding: 30px; }

        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .info-item { padding: 12px; background: #f8f9fa; border-radius: 8px; }
        .info-label { font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 4px; }
        .info-value { font-size: 16px; font-weight: 600; color: #333; }

        .room-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .room-card h2 { font-size: 28px; margin-bottom: 10px; }
        .room-card .details { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-top: 15px; }
        .room-card .detail-item { background: rgba(255,255,255,0.2); padding: 10px; border-radius: 6px; text-align: center; }
        .room-card .detail-label { font-size: 11px; opacity: 0.9; }
        .room-card .detail-value { font-size: 18px; font-weight: 700; margin-top: 4px; }

        .no-room { text-align: center; padding: 40px; background: #fff7ed; border: 2px dashed #f59e0b; border-radius: 12px; color: #9a3412; }
        .no-room .icon { font-size: 48px; margin-bottom: 10px; }

        .roommate-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
        .roommate-card { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea; }
        .roommate-name { font-weight: 600; color: #333; margin-bottom: 4px; }
        .roommate-info { font-size: 12px; color: #666; }
        .roommate-bed { background: #667eea; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; display: inline-block; margin-top: 6px; }

        .announcement-list { list-style: none; padding: 0; }
        .announcement-item { padding: 12px; border-bottom: 1px solid #f0f0f0; display: flex; gap: 10px; }
        .announcement-item:last-child { border-bottom: none; }
        .announcement-date { font-size: 11px; color: #999; white-space: nowrap; }
        .announcement-content { font-size: 13px; color: #333; }

        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.3s; background: #667eea; color: white; }
        .btn:hover { background: #5568d3; transform: translateY(-2px); }

        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; margin-top: 40px; }

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 10px; padding: 15px; }
            .container { padding: 15px; }
            .info-grid { grid-template-columns: 1fr; }
            .room-card .details { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👨‍🎓 学生端</h1>
        <div class="header-info">
            <div class="user-info">
                <?php echo h($student['real_name'] ?? $currentUser['real_name']); ?>
            </div>
            <a href="../admin/logout.php" class="logout-btn">退出登录</a>
        </div>
    </div>

    <div class="container">
        <!-- 个人信息 -->
        <div class="card">
            <div class="card-header">📋 个人信息</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">学号</div>
                    <div class="info-value"><?php echo h($student['student_id']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">姓名</div>
                    <div class="info-value"><?php echo h($student['real_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">性别</div>
                    <div class="info-value"><?php echo getGenderName($student['gender']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">学院</div>
                    <div class="info-value"><?php echo h($student['college']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">专业</div>
                    <div class="info-value"><?php echo h($student['major']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">班级</div>
                    <div class="info-value"><?php echo h($student['class_name'] ?? '未设置'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">联系电话</div>
                    <div class="info-value"><?php echo h($student['phone'] ?? '未设置'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">状态</div>
                    <div class="info-value"><?php echo getStatusName($student['status'], 'student'); ?></div>
                </div>
            </div>
        </div>

        <!-- 宿舍信息 -->
        <div class="card">
            <div class="card-header">🏠 我的宿舍</div>
            <?php if ($roomInfo): ?>
                <div class="room-card">
                    <h2><?php echo h($roomInfo['building_name']); ?> - <?php echo h($roomInfo['room_number']); ?></h2>
                    <div style="opacity: 0.9; font-size: 14px;">
                        <?php echo h($roomInfo['building_code']); ?> | <?php echo $roomInfo['floor']; ?>层
                    </div>
                    <div class="details">
                        <div class="detail-item">
                            <div class="detail-label">我的床位</div>
                            <div class="detail-value"><?php echo $roomInfo['bed_number']; ?>号床</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">总床位</div>
                            <div class="detail-value"><?php echo $roomInfo['bed_count']; ?>床</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">已入住</div>
                            <div class="detail-value"><?php echo $roomInfo['current_occupancy']; ?>人</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">入住日期</div>
                            <div class="detail-value"><?php echo h($roomInfo['move_in_date']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- 室友信息 -->
                <?php if (!empty($roommates)): ?>
                    <div style="margin-top: 20px;">
                        <h3 style="font-size: 16px; margin-bottom: 12px; color: #333;">👬 室友</h3>
                        <div class="roommate-list">
                            <?php foreach ($roommates as $m): ?>
                                <div class="roommate-card">
                                    <div class="roommate-name"><?php echo h($m['real_name']); ?></div>
                                    <div class="roommate-info">
                                        <?php echo h($m['student_id']); ?><br>
                                        <?php echo h($m['college']); ?> - <?php echo h($m['major']); ?>
                                    </div>
                                    <span class="roommate-bed"><?php echo $m['bed_number']; ?>号床</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-room">
                    <div class="icon">🏠</div>
                    <h3>尚未分配宿舍</h3>
                    <p style="margin-top: 8px; font-size: 13px;">请联系宿管或辅导员进行宿舍分配</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- 公告通知 -->
        <div class="card">
            <div class="card-header">📢 宿舍公告</div>
            <?php if (!empty($announcements)): ?>
                <ul class="announcement-list">
                    <?php foreach ($announcements as $notice): ?>
                        <li class="announcement-item">
                            <span class="announcement-date"><?php echo h($notice['created_at']); ?></span>
                            <span class="announcement-content">
                                <strong><?php echo h($notice['config_name']); ?>:</strong>
                                <?php echo h($notice['config_value']); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p style="color: #999; text-align: center; padding: 20px;">暂无公告</p>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>智慧宿舍管理系统 - 学生端</p>
            <p>当前学期：<?php echo getCurrentSemester(); ?></p>
        </div>
    </div>
</body>
</html>