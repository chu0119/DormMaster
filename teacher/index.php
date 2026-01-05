<?php
/**
 * 教师端首页
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/helpers.php';

$auth = new Auth();
$auth->requireRole([2]); // 仅教师

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();

// 获取教师管辖的学院
$teacherCollege = $currentUser['college'] ?? null;

// 统计信息
$stats = [];
if ($teacherCollege) {
    // 该学院的学生总数
    $stats['student_count'] = $db->getOne("SELECT COUNT(*) FROM students WHERE college = ? AND status = 1", [$teacherCollege]);

    // 已分配宿舍的学生
    $stats['assigned_count'] = $db->getOne("
        SELECT COUNT(DISTINCT s.id)
        FROM students s
        JOIN room_assignments ra ON s.id = ra.student_id AND ra.status = 1
        WHERE s.college = ? AND s.status = 1
    ", [$teacherCollege]);

    // 未分配宿舍的学生
    $stats['unassigned_count'] = $stats['student_count'] - $stats['assigned_count'];

    // 获取管辖学生列表
    $students = $db->getAll("
        SELECT
            s.*,
            r.room_number,
            r.floor,
            b.building_name,
            ra.bed_number
        FROM students s
        LEFT JOIN room_assignments ra ON s.id = ra.student_id AND ra.status = 1
        LEFT JOIN rooms r ON ra.room_id = r.id
        LEFT JOIN dormitory_buildings b ON r.building_id = b.id
        WHERE s.college = ? AND s.status = 1
        ORDER BY s.student_id
        LIMIT 20
    ", [$teacherCollege]);

    // 获取宿舍楼统计
    $buildingStats = $db->getAll("
        SELECT
            b.building_name,
            b.building_code,
            COUNT(DISTINCT r.id) as room_count,
            COALESCE(SUM(r.current_occupancy), 0) as occupancy
        FROM dormitory_buildings b
        LEFT JOIN rooms r ON b.id = r.building_id
        LEFT JOIN room_assignments ra ON r.id = ra.room_id AND ra.status = 1
        LEFT JOIN students s ON ra.student_id = s.id
        WHERE s.college = ? OR s.id IS NULL
        GROUP BY b.id
    ", [$teacherCollege]);
} else {
    $students = [];
    $buildingStats = [];
    $stats = ['student_count' => 0, 'assigned_count' => 0, 'unassigned_count' => 0];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教师端 - <?php echo SYSTEM_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }

        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 22px; font-weight: 600; }
        .header-info { display: flex; align-items: center; gap: 20px; }
        .user-info { background: rgba(255, 255, 255, 0.2); padding: 8px 16px; border-radius: 20px; backdrop-filter: blur(10px); }
        .logout-btn { background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.3); padding: 8px 16px; border-radius: 20px; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .logout-btn:hover { background: rgba(255, 255, 255, 0.3); transform: translateY(-2px); }

        .container { max-width: 1200px; margin: 0 auto; padding: 30px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-left: 4px solid #667eea; }
        .stat-card h3 { color: #666; font-size: 13px; font-weight: 500; margin-bottom: 8px; }
        .stat-card .value { font-size: 28px; font-weight: 700; color: #333; }

        .content-section { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .section-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }

        .student-list { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; font-weight: 600; color: #666; }
        tr:hover { background: #f8f9fa; }

        .room-info { background: #f0f9ff; padding: 4px 8px; border-radius: 4px; font-size: 12px; color: #0369a1; display: inline-block; }
        .no-room { color: #999; font-style: italic; }

        .building-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
        .building-card { background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0; }
        .building-name { font-weight: 600; color: #333; margin-bottom: 8px; }
        .building-info { font-size: 12px; color: #666; line-height: 1.6; }

        .info-box { background: #fff7ed; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; color: #9a3412; border-left: 3px solid #f59e0b; }

        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; margin-top: 40px; }

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 10px; padding: 15px; }
            .container { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
            table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👨‍🏫 教师端</h1>
        <div class="header-info">
            <div class="user-info">
                <?php echo h($currentUser['real_name']); ?> |
                <?php echo h($teacherCollege ?? '未分配学院'); ?>
            </div>
            <a href="../admin/logout.php" class="logout-btn">退出登录</a>
        </div>
    </div>

    <div class="container">
        <?php if (!$teacherCollege): ?>
            <div class="info-box">
                ⚠️ 您尚未分配管辖学院，请联系管理员设置。
            </div>
        <?php endif; ?>

        <!-- 统计卡片 -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>学生总数</h3>
                <div class="value"><?php echo $stats['student_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #10b981;">
                <h3>已分配宿舍</h3>
                <div class="value"><?php echo $stats['assigned_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ef4444;">
                <h3>未分配宿舍</h3>
                <div class="value"><?php echo $stats['unassigned_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #f59e0b;">
                <h3>分配率</h3>
                <div class="value">
                    <?php
                        $rate = $stats['student_count'] > 0 ? round($stats['assigned_count'] / $stats['student_count'] * 100, 1) : 0;
                        echo $rate . '%';
                    ?>
                </div>
            </div>
        </div>

        <!-- 学生列表 -->
        <div class="content-section">
            <div class="section-title">管辖学生列表（前20名）</div>
            <?php if (empty($students)): ?>
                <p style="color: #999; text-align: center; padding: 20px;">暂无学生数据</p>
            <?php else: ?>
                <div class="student-list">
                    <table>
                        <thead>
                            <tr>
                                <th>学号</th>
                                <th>姓名</th>
                                <th>专业</th>
                                <th>班级</th>
                                <th>宿舍信息</th>
                                <th>联系电话</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><strong><?php echo h($s['student_id']); ?></strong></td>
                                    <td><?php echo h($s['real_name']); ?></td>
                                    <td><?php echo h($s['major']); ?></td>
                                    <td><?php echo h($s['class_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($s['room_id']): ?>
                                            <span class="room-info">
                                                <?php echo h($s['building_name']); ?>-<?php echo h($s['room_number']); ?>-<?php echo $s['bed_number']; ?>床
                                            </span>
                                        <?php else: ?>
                                            <span class="no-room">未分配</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo h($s['phone'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 宿舍楼统计 -->
        <div class="content-section">
            <div class="section-title">宿舍楼概况</div>
            <?php if (empty($buildingStats)): ?>
                <p style="color: #999; text-align: center; padding: 20px;">暂无宿舍数据</p>
            <?php else: ?>
                <div class="building-grid">
                    <?php foreach ($buildingStats as $b): ?>
                        <div class="building-card">
                            <div class="building-name"><?php echo h($b['building_name']); ?></div>
                            <div class="building-info">
                                <div>房间数：<?php echo $b['room_count']; ?>间</div>
                                <div>已入住：<?php echo $b['occupancy']; ?>人</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>智慧宿舍管理系统 - 教师端</p>
            <p>当前学期：<?php echo getCurrentSemester(); ?></p>
        </div>
    </div>
</body>
</html>