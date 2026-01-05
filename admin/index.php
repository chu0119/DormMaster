<?php
/**
 * 管理端首页
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Models/DormitoryBuilding.php';
require_once __DIR__ . '/../app/Models/Room.php';

$auth = new Auth();
$auth->requireRole([1]); // 仅管理员

$buildingModel = new DormitoryBuilding();
$roomModel = new Room();

// 获取统计数据
$buildingStats = $buildingModel->getStats();
$roomStats = $roomModel->getStats();

// 获取最近的活动日志
$db = Database::getInstance();
$recentLogs = $db->getAll("
    SELECT l.*, u.username, u.real_name
    FROM operation_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 10
");

// 获取系统配置
$systemName = getConfig('system_name', SYSTEM_NAME);
$systemVersion = SYSTEM_VERSION;

// 获取当前用户信息
$currentUser = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理端 - <?php echo h($systemName); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }

        .stat-card .subtext {
            font-size: 12px;
            color: #999;
        }

        .stat-card.success { border-left-color: #10b981; }
        .stat-card.warning { border-left-color: #f59e0b; }
        .stat-card.danger { border-left-color: #ef4444; }
        .stat-card.info { border-left-color: #3b82f6; }

        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .nav-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .nav-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .nav-item .icon {
            font-size: 32px;
            margin-bottom: 8px;
            display: block;
        }

        .nav-item .label {
            font-weight: 600;
            font-size: 14px;
        }

        .content-section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .log-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .log-item {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-time {
            color: #999;
            white-space: nowrap;
            font-family: monospace;
        }

        .log-user {
            color: #667eea;
            font-weight: 600;
            white-space: nowrap;
        }

        .log-action {
            color: #10b981;
            font-weight: 600;
            background: #d1fae5;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
        }

        .log-content {
            color: #333;
            flex: 1;
        }

        .building-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }

        .building-card {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }

        .building-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .building-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .building-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }

        .gender-badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .gender-male { background: #dbeafe; color: #1e40af; }
        .gender-female { background: #fce7f3; color: #9d174d; }
        .gender-mixed { background: #e0e7ff; color: #4338ca; }

        .building-stats {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }

        .building-stat {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 13px;
            margin-top: 40px;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }

            .container {
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .nav-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <span>🏠</span>
            <?php echo h($systemName); ?> - 管理端
        </h1>
        <div class="header-info">
            <div class="user-info">
                👤 <?php echo h($currentUser['real_name']); ?> (管理员)
            </div>
            <a href="logout.php" class="logout-btn">退出登录</a>
        </div>
    </div>

    <div class="container">
        <!-- 统计卡片 -->
        <div class="stats-grid">
            <div class="stat-card info">
                <h3>宿舍楼总数</h3>
                <div class="value"><?php echo count($buildingStats); ?></div>
                <div class="subtext">栋运营中的宿舍楼</div>
            </div>
            <div class="stat-card success">
                <h3>总房间数</h3>
                <div class="value"><?php echo $roomStats['total_rooms'] ?? 0; ?></div>
                <div class="subtext">可用房间总数</div>
            </div>
            <div class="stat-card warning">
                <h3>总床位数</h3>
                <div class="value"><?php echo $roomStats['total_beds'] ?? 0; ?></div>
                <div class="subtext">可容纳学生总数</div>
            </div>
            <div class="stat-card danger">
                <h3>已入住</h3>
                <div class="value"><?php echo $roomStats['current_occupancy'] ?? 0; ?></div>
                <div class="subtext">当前入住学生数</div>
            </div>
        </div>

        <!-- 快捷导航 -->
        <div class="content-section">
            <div class="section-title">
                <span>⚡ 快捷操作</span>
            </div>
            <div class="nav-grid">
                <a href="buildings.php" class="nav-item">
                    <span class="icon">🏢</span>
                    <span class="label">宿舍楼管理</span>
                </a>
                <a href="rooms.php" class="nav-item">
                    <span class="icon">🚪</span>
                    <span class="label">房间管理</span>
                </a>
                <a href="students.php" class="nav-item">
                    <span class="icon">👨‍🎓</span>
                    <span class="label">学生管理</span>
                </a>
                <a href="templates.php" class="nav-item">
                    <span class="icon">📋</span>
                    <span class="label">模板管理</span>
                </a>
                <a href="assignments.php" class="nav-item">
                    <span class="icon">🔑</span>
                    <span class="label">入住分配</span>
                </a>
                <a href="statistics.php" class="nav-item">
                    <span class="icon">📊</span>
                    <span class="label">数据统计</span>
                </a>
                <a href="users.php" class="nav-item">
                    <span class="icon">👥</span>
                    <span class="label">用户管理</span>
                </a>
                <a href="import_export.php" class="nav-item">
                    <span class="icon">📤</span>
                    <span class="label">导入导出</span>
                </a>
            </div>
        </div>

        <!-- 宿舍楼概览 -->
        <div class="content-section">
            <div class="section-title">
                <span>🏢 宿舍楼概览</span>
                <a href="buildings.php" class="btn btn-primary btn-sm">管理宿舍楼</a>
            </div>
            <div class="building-list">
                <?php if (empty($buildingStats)): ?>
                    <p style="color: #999; text-align: center; padding: 20px;">暂无宿舍楼数据，请先添加宿舍楼</p>
                <?php else: ?>
                    <?php foreach ($buildingStats as $building): ?>
                        <div class="building-card">
                            <div class="building-header">
                                <div class="building-name"><?php echo h($building['building_name']); ?></div>
                                <span class="gender-badge <?php
                                    echo $building['gender_type'] == 1 ? 'gender-male' :
                                         ($building['gender_type'] == 2 ? 'gender-female' : 'gender-mixed');
                                ?>">
                                    <?php echo getGenderName($building['gender_type']); ?>
                                </span>
                            </div>
                            <div style="font-size: 12px; color: #666; margin-bottom: 8px;">
                                编码: <?php echo h($building['building_code']); ?>
                            </div>
                            <div class="building-stats">
                                <div class="building-stat">📦 <?php echo $building['room_count']; ?>间</div>
                                <div class="building-stat">🛏️ <?php echo $building['total_beds']; ?>床</div>
                                <div class="building-stat">👥 <?php echo $building['current_occupancy']; ?>人</div>
                                <div class="building-stat">✅ <?php echo $building['available_beds']; ?>空位</div>
                            </div>
                            <?php
                                $occupancyRate = calculateOccupancyRate($building['current_occupancy'], $building['total_beds']);
                            ?>
                            <div style="margin-top: 8px; font-size: 11px; color: #666;">
                                入住率: <?php echo $occupancyRate; ?>%
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 最近活动日志 -->
        <div class="content-section">
            <div class="section-title">
                <span>📝 最近活动日志</span>
                <a href="logs.php" class="btn btn-primary btn-sm">查看全部</a>
            </div>
            <div class="log-list">
                <?php if (empty($recentLogs)): ?>
                    <p style="color: #999; text-align: center; padding: 20px;">暂无活动记录</p>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <div class="log-item">
                            <span class="log-time"><?php echo formatTime($log['created_at'], 'H:i'); ?></span>
                            <span class="log-user"><?php echo h($log['real_name'] ?? $log['username']); ?></span>
                            <span class="log-action"><?php echo h($log['action']); ?></span>
                            <span class="log-content"><?php echo h($log['content']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 系统信息 -->
        <div class="content-section">
            <div class="section-title">ℹ️ 系统信息</div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 13px;">
                <div><strong>系统版本:</strong> <?php echo $systemVersion; ?></div>
                <div><strong>当前学期:</strong> <?php echo getCurrentSemester(); ?></div>
                <div><strong>当前时间:</strong> <?php echo date('Y-m-d H:i:s'); ?></div>
                <div><strong>数据库:</strong> <?php echo DB_NAME; ?></div>
            </div>
        </div>

        <div class="footer">
            <p>© <?php echo date('Y'); ?> <?php echo h($systemName); ?> | 版本 <?php echo $systemVersion; ?></p>
            <p style="margin-top: 5px; font-size: 11px;">Powered by PHP + MySQL | Designed for Dormitory Management</p>
        </div>
    </div>
</body>
</html>