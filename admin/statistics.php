<?php
/**
 * 数据统计页面
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/helpers.php';

$auth = new Auth();
$auth->requireRole([1]);

$db = Database::getInstance();

// 获取统计数据
$stats = [
    'buildings' => $db->getOne("SELECT COUNT(*) FROM dormitory_buildings WHERE status = 1"),
    'rooms' => $db->getOne("SELECT COUNT(*) FROM rooms WHERE status = 1"),
    'total_beds' => $db->getOne("SELECT SUM(bed_count) FROM rooms WHERE status = 1"),
    'occupied_beds' => $db->getOne("SELECT SUM(current_occupancy) FROM rooms WHERE status = 1"),
    'students' => $db->getOne("SELECT COUNT(*) FROM students WHERE status = 1"),
    'assigned_students' => $db->getOne("SELECT COUNT(DISTINCT student_id) FROM room_assignments WHERE status = 1"),
    'maintenance_rooms' => $db->getOne("SELECT COUNT(*) FROM rooms WHERE status = 2"),
    'disabled_rooms' => $db->getOne("SELECT COUNT(*) FROM rooms WHERE status = 3")
];

// 按楼栋统计
$buildingStats = $db->getAll("
    SELECT
        b.id,
        b.building_name,
        b.building_code,
        b.gender_type,
        COUNT(DISTINCT r.id) as room_count,
        COALESCE(SUM(r.bed_count), 0) as total_beds,
        COALESCE(SUM(r.current_occupancy), 0) as occupied_beds,
        COALESCE(SUM(r.bed_count) - SUM(r.current_occupancy), 0) as available_beds
    FROM dormitory_buildings b
    LEFT JOIN rooms r ON b.id = r.building_id AND r.status = 1
    WHERE b.status = 1
    GROUP BY b.id
    ORDER BY b.building_code
");

// 按楼层统计（第一个楼栋）
$firstBuilding = $db->getRow("SELECT id, building_name FROM dormitory_buildings WHERE status = 1 ORDER BY id LIMIT 1");
$floorStats = [];
if ($firstBuilding) {
    $floorStats = $db->getAll("
        SELECT
            floor,
            COUNT(*) as room_count,
            COALESCE(SUM(bed_count), 0) as total_beds,
            COALESCE(SUM(current_occupancy), 0) as occupied_beds,
            COALESCE(SUM(bed_count) - SUM(current_occupancy), 0) as available_beds
        FROM rooms
        WHERE building_id = ? AND status = 1
        GROUP BY floor
        ORDER BY floor
    ", [$firstBuilding['id']]);
}

// 按学院统计学生
$collegeStats = $db->getAll("
    SELECT
        college,
        COUNT(*) as student_count,
        COALESCE(SUM(CASE WHEN ra.id IS NOT NULL THEN 1 ELSE 0 END), 0) as assigned_count
    FROM students s
    LEFT JOIN room_assignments ra ON s.id = ra.student_id AND ra.status = 1
    WHERE s.status = 1
    GROUP BY college
    ORDER BY student_count DESC
    LIMIT 10
");

// 性别统计
$genderStats = $db->getRow("
    SELECT
        COALESCE(SUM(CASE WHEN gender = 1 THEN 1 ELSE 0 END), 0) as male,
        COALESCE(SUM(CASE WHEN gender = 2 THEN 1 ELSE 0 END), 0) as female
    FROM students
    WHERE status = 1
");

// 入住率计算
$occupancyRate = $stats['total_beds'] > 0 ? round($stats['occupied_beds'] / $stats['total_beds'] * 100, 2) : 0;

// 最近活动
$recentActivities = $db->getAll("
    SELECT l.*, u.username, u.real_name
    FROM operation_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 15
");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据统计 - 管理端</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; min-height: 100vh; }

        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 22px; font-weight: 600; }
        .header-actions { display: flex; gap: 10px; }
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; background: white; color: #667eea; }
        .btn:hover { background: #f0f0f0; transform: translateY(-2px); }

        .container { max-width: 1400px; margin: 0 auto; padding: 30px; }

        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card-header { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
        .stat-item { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea; text-align: center; }
        .stat-item .label { font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 6px; }
        .stat-item .value { font-size: 24px; font-weight: 700; color: #333; }
        .stat-item.success { border-left-color: #10b981; }
        .stat-item.warning { border-left-color: #f59e0b; }
        .stat-item.danger { border-left-color: #ef4444; }

        .progress-bar { width: 100%; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin-top: 8px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 0.3s; }

        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; font-weight: 600; color: #666; }
        tr:hover { background: #f8f9fa; }

        .gender-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .gender-male { background: #dbeafe; color: #1e40af; }
        .gender-female { background: #fce7f3; color: #9d174d; }
        .gender-mixed { background: #e0e7ff; color: #4338ca; }

        .log-list { max-height: 400px; overflow-y: auto; }
        .log-item { padding: 8px; border-bottom: 1px solid #f0f0f0; font-size: 11px; display: flex; gap: 10px; }
        .log-time { color: #999; white-space: nowrap; }
        .log-user { color: #667eea; font-weight: 600; white-space: nowrap; }
        .log-action { color: #10b981; background: #d1fae5; padding: 2px 6px; border-radius: 3px; font-size: 10px; white-space: nowrap; }
        .log-content { color: #333; flex: 1; }

        .chart-placeholder { background: #f8f9fa; padding: 20px; text-align: center; color: #999; border-radius: 8px; border: 2px dashed #e0e0e0; }
        .chart-placeholder .icon { font-size: 48px; margin-bottom: 10px; opacity: 0.5; }

        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }

        @media (max-width: 1024px) {
            .two-col, .three-col { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 10px; padding: 15px; }
            .container { padding: 15px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 数据统计</h1>
        <div class="header-actions">
            <a href="index.php" class="btn">返回首页</a>
        </div>
    </div>

    <div class="container">
        <!-- 核心统计 -->
        <div class="card">
            <div class="card-header">核心数据概览</div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="label">宿舍楼</div>
                    <div class="value"><?php echo $stats['buildings']; ?></div>
                </div>
                <div class="stat-item">
                    <div class="label">房间总数</div>
                    <div class="value"><?php echo $stats['rooms']; ?></div>
                </div>
                <div class="stat-item">
                    <div class="label">总床位</div>
                    <div class="value"><?php echo $stats['total_beds']; ?></div>
                </div>
                <div class="stat-item success">
                    <div class="label">已入住</div>
                    <div class="value"><?php echo $stats['occupied_beds']; ?></div>
                </div>
                <div class="stat-item warning">
                    <div class="label">空床位</div>
                    <div class="value"><?php echo $stats['total_beds'] - $stats['occupied_beds']; ?></div>
                </div>
                <div class="stat-item">
                    <div class="label">入住率</div>
                    <div class="value"><?php echo $occupancyRate; ?>%</div>
                </div>
                <div class="stat-item">
                    <div class="label">学生总数</div>
                    <div class="value"><?php echo $stats['students']; ?></div>
                </div>
                <div class="stat-item success">
                    <div class="label">已分配</div>
                    <div class="value"><?php echo $stats['assigned_students']; ?></div>
                </div>
                <div class="stat-item danger">
                    <div class="label">维修中</div>
                    <div class="value"><?php echo $stats['maintenance_rooms']; ?></div>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">整体入住率</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $occupancyRate; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- 楼栋统计 -->
        <div class="card">
            <div class="card-header">楼栋入住情况</div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>楼栋</th>
                            <th>编码</th>
                            <th>类型</th>
                            <th>房间</th>
                            <th>总床位</th>
                            <th>已入住</th>
                            <th>空余</th>
                            <th>入住率</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buildingStats as $b): ?>
                            <?php
                                $rate = $b['total_beds'] > 0 ? round($b['occupied_beds'] / $b['total_beds'] * 100, 1) : 0;
                                $genderClass = $b['gender_type'] == 1 ? 'gender-male' : ($b['gender_type'] == 2 ? 'gender-female' : 'gender-mixed');
                            ?>
                            <tr>
                                <td><strong><?php echo h($b['building_name']); ?></strong></td>
                                <td><?php echo h($b['building_code']); ?></td>
                                <td><span class="gender-badge <?php echo $genderClass; ?>"><?php echo getGenderName($b['gender_type']); ?></span></td>
                                <td><?php echo $b['room_count']; ?></td>
                                <td><?php echo $b['total_beds']; ?></td>
                                <td><?php echo $b['occupied_beds']; ?></td>
                                <td><?php echo $b['available_beds']; ?></td>
                                <td>
                                    <div><?php echo $rate; ?>%</div>
                                    <div class="progress-bar" style="height: 4px; margin-top: 2px;">
                                        <div class="progress-fill" style="width: <?php echo $rate; ?>%; height: 100%;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="two-col">
            <!-- 楼层统计 -->
            <div class="card">
                <div class="card-header">楼层分布（<?php echo $firstBuilding ? $firstBuilding['building_name'] : '无数据'; ?>）</div>
                <?php if (!empty($floorStats)): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>楼层</th>
                                    <th>房间</th>
                                    <th>床位</th>
                                    <th>入住</th>
                                    <th>入住率</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($floorStats as $f): ?>
                                    <?php $rate = $f['total_beds'] > 0 ? round($f['occupied_beds'] / $f['total_beds'] * 100, 1) : 0; ?>
                                    <tr>
                                        <td><?php echo $f['floor']; ?>层</td>
                                        <td><?php echo $f['room_count']; ?></td>
                                        <td><?php echo $f['total_beds']; ?></td>
                                        <td><?php echo $f['occupied_beds']; ?></td>
                                        <td><?php echo $rate; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: #999; text-align: center; padding: 20px;">暂无楼层数据</p>
                <?php endif; ?>
            </div>

            <!-- 性别统计 -->
            <div class="card">
                <div class="card-header">学生性别分布</div>
                <div class="stats-grid" style="grid-template-columns: 1fr 1fr;">
                    <div class="stat-item" style="border-left-color: #1e40af;">
                        <div class="label">男生</div>
                        <div class="value"><?php echo $genderStats['male']; ?></div>
                        <div style="font-size: 11px; color: #666; margin-top: 4px;">
                            <?php echo $stats['students'] > 0 ? round($genderStats['male'] / $stats['students'] * 100, 1) : 0; ?>%
                        </div>
                    </div>
                    <div class="stat-item" style="border-left-color: #9d174d;">
                        <div class="label">女生</div>
                        <div class="value"><?php echo $genderStats['female']; ?></div>
                        <div style="font-size: 11px; color: #666; margin-top: 4px;">
                            <?php echo $stats['students'] > 0 ? round($genderStats['female'] / $stats['students'] * 100, 1) : 0; ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 学院统计 -->
        <div class="card">
            <div class="card-header">学院学生分布（Top 10）</div>
            <?php if (!empty($collegeStats)): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>学院</th>
                                <th>学生数</th>
                                <th>已分配</th>
                                <th>分配率</th>
                                <th>未分配</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collegeStats as $c): ?>
                                <?php
                                    $rate = $c['student_count'] > 0 ? round($c['assigned_count'] / $c['student_count'] * 100, 1) : 0;
                                    $unassigned = $c['student_count'] - $c['assigned_count'];
                                ?>
                                <tr>
                                    <td><strong><?php echo h($c['college']); ?></strong></td>
                                    <td><?php echo $c['student_count']; ?></td>
                                    <td><?php echo $c['assigned_count']; ?></td>
                                    <td>
                                        <div><?php echo $rate; ?>%</div>
                                        <div class="progress-bar" style="height: 4px; margin-top: 2px;">
                                            <div class="progress-fill" style="width: <?php echo $rate; ?>%; height: 100%;"></div>
                                        </div>
                                    </td>
                                    <td style="color: <?php echo $unassigned > 0 ? '#ef4444' : '#999'; ?>">
                                        <?php echo $unassigned; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #999; text-align: center; padding: 20px;">暂无学院数据</p>
            <?php endif; ?>
        </div>

        <!-- 最近活动 -->
        <div class="card">
            <div class="card-header">最近系统活动</div>
            <div class="log-list">
                <?php if (empty($recentActivities)): ?>
                    <p style="color: #999; text-align: center; padding: 20px;">暂无活动记录</p>
                <?php else: ?>
                    <?php foreach ($recentActivities as $log): ?>
                        <div class="log-item">
                            <span class="log-time"><?php echo formatTime($log['created_at'], 'm-d H:i'); ?></span>
                            <span class="log-user"><?php echo h($log['real_name'] ?? $log['username']); ?></span>
                            <span class="log-action"><?php echo h($log['action']); ?></span>
                            <span class="log-content"><?php echo h($log['content']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 图表占位符（可扩展） -->
        <div class="card">
            <div class="card-header">数据可视化（扩展功能）</div>
            <div class="chart-placeholder">
                <div class="icon">📈</div>
                <p>此处可集成图表库（如ECharts、Chart.js）展示可视化数据</p>
                <p style="font-size: 11px; margin-top: 5px;">包括：入住率趋势图、楼栋对比图、学院分布饼图等</p>
            </div>
        </div>
    </div>
</body>
</html>