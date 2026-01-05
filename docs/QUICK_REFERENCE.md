# 快速参考指南

## 🚀 5分钟快速上手

### 1. 安装系统
```bash
# 访问安装页面
http://your-domain.com/install.php

# 填写数据库信息
# 完成安装
```

### 2. 登录系统
```
用户名: admin
密码: admin123
```

### 3. 添加第一个宿舍楼
1. 进入 **管理后台** → **宿舍管理** → **楼栋管理**
2. 点击 **添加楼栋**
3. 填写：
   - 名称：1号楼
   - 编码：B01
   - 楼层数：6
   - 性别：男
4. 点击保存

### 4. 批量添加房间
1. 进入 **房间管理** → **批量添加**
2. 选择：1号楼
3. 设置：1-6层，每层10间，4床位
4. 点击创建

### 5. 添加学生
1. 进入 **学生管理** → **添加学生**
2. 填写基本信息
3. 保存

### 6. 分配宿舍
1. 进入 **宿舍分配** → **批量分配**
2. 选择房间
3. 搜索并选择学生
4. 确认分配

## 📋 常用操作速查

### 宿舍管理
| 操作 | 路径 | 关键步骤 |
|------|------|----------|
| 添加楼栋 | 宿舍管理 → 楼栋管理 → 添加 | 填写基本信息 |
| 批量添加房间 | 宿舍管理 → 房间管理 → 批量添加 | 设置楼层范围 |
| 查看房间 | 宿舍管理 → 房间管理 | 搜索/筛选 |
| 修改状态 | 房间管理 → 编辑 | 选择状态 |

### 学生管理
| 操作 | 路径 | 关键步骤 |
|------|------|----------|
| 添加学生 | 学生管理 → 添加学生 | 填写完整信息 |
| 批量导入 | 学生管理 → 批量导入 | 上传CSV文件 |
| 搜索学生 | 学生管理 → 列表页 | 输入关键词 |
| 编辑信息 | 学生管理 → 编辑 | 修改字段 |

### 宿舍分配
| 操作 | 路径 | 关键步骤 |
|------|------|----------|
| 单个分配 | 宿舍分配 → 单个分配 | 选学生+房间+床位 |
| 批量分配 | 宿舍分配 → 批量分配 | 搜索+选择+确认 |
| 查看分配 | 宿舍分配 → 分配记录 | 搜索/筛选 |
| 退宿 | 分配记录 → 退宿 | 点击退宿按钮 |

### 数据统计
| 操作 | 路径 | 说明 |
|------|------|------|
| 查看统计 | 数据统计 → 概览 | 总体数据 |
| 楼栋分析 | 数据统计 → 楼栋统计 | 各楼栋入住率 |
| 学生分析 | 数据统计 → 学生统计 | 按学院/专业 |

## 🔧 常用代码片段

### 1. 数据库查询
```php
// 查询多条数据
$students = $db->getAll("SELECT * FROM students WHERE status = 1");

// 查询单条数据
$room = $db->getRow("SELECT * FROM rooms WHERE id = ?", [$roomId]);

// 查询单个值
$count = $db->getOne("SELECT COUNT(*) FROM students");
```

### 2. 权限验证
```php
// 仅管理员
$auth->requireRole([1]);

// 管理员和教师
$auth->requireRole([1, 2]);

// 所有角色
$auth->requireRole([1, 2, 3, 4]);
```

### 3. 安全输出
```php
// HTML 转义
echo h($userData);

// CSRF 令牌
echo '<input type="hidden" name="csrf_token" value="' . h($csrfToken) . '">';

// 验证令牌
if (!verifyCsrfToken()) {
    die('无效的令牌');
}
```

### 4. CSV 导入
```php
// 自动编码转换导入
$result = importCsv($filePath, function($data, $headers, $rowNum) {
    // 处理每行数据
    return true; // 返回 true 继续，false 停止
}, 'auto'); // auto, gbk, utf-8
```

### 5. 日志记录
```php
// 记录操作日志
$auth->logOperation($userId, 'assignment', 'batch', '批量分配 5 名学生');

// 记录错误
error_log(date('Y-m-d H:i:s') . " [ERROR] " . $message . "\n", 3, 'logs/error.log');
```

## 📊 数据库常用查询

### 查询空房间
```sql
SELECT r.*, b.building_name
FROM rooms r
JOIN dormitory_buildings b ON r.building_id = b.id
WHERE r.current_occupancy < r.bed_count
AND r.status = 1;
```

### 查询未分配学生
```sql
SELECT s.*
FROM students s
LEFT JOIN room_assignments ra ON s.id = ra.student_id AND ra.status = 1
WHERE ra.id IS NULL AND s.status = 1;
```

### 查询楼栋入住率
```sql
SELECT
    b.building_name,
    SUM(r.bed_count) as total_beds,
    SUM(r.current_occupancy) as occupied,
    ROUND(SUM(r.current_occupancy) / SUM(r.bed_count) * 100, 2) as rate
FROM dormitory_buildings b
JOIN rooms r ON b.id = r.building_id
GROUP BY b.id;
```

### 查询学生分配详情
```sql
SELECT
    s.real_name,
    s.student_id,
    b.building_name,
    r.room_number,
    ra.bed_number,
    ra.move_in_date
FROM room_assignments ra
JOIN students s ON ra.student_id = s.id
JOIN rooms r ON ra.room_id = r.id
JOIN dormitory_buildings b ON r.building_id = b.id
WHERE ra.status = 1;
```

## 🎨 CSS 类速查

### 按钮样式
```html
<button class="btn btn-primary">主要按钮</button>
<button class="btn btn-success">成功按钮</button>
<button class="btn btn-danger">危险按钮</button>
<button class="btn btn-sm">小按钮</button>
```

### 提示框
```html
<div class="alert success">成功消息</div>
<div class="alert error">错误消息</div>
<div class="alert info">提示信息</div>
```

### 卡片
```html
<div class="card">
    <div class="card-header">
        <div class="card-title">标题</div>
    </div>
    <div class="card-body">内容</div>
</div>
```

## 🔍 故障排除

### 问题：页面显示空白或错误
**检查**：
1. PHP 错误日志
2. `config/config.php` 是否存在
3. 数据库连接是否正常

### 问题：CSV 导入失败
**检查**：
1. 文件编码（UTF-8 或 GBK）
2. 列数是否为 10
3. 查看 `logs/import.log`

### 问题：批量分配看不到学生
**检查**：
1. 是否有未分配学生
2. `students.status = 1`
3. `room_assignments` 中无记录

### 问题：图表不显示
**检查**：
1. GD 扩展是否安装
2. 是否有统计数据
3. 浏览器控制台错误

## 📞 技术支持

### 获取帮助
1. 查看 README.md
2. 查看 INSTALL.md
3. 查看 USAGE.md
4. 检查系统日志：`admin/logs.php`

### 报告问题
提供以下信息：
- 错误信息截图
- 操作步骤
- PHP 版本
- 浏览器类型
- 日志文件内容

---

**版本**: v1.1.0
**更新时间**: 2026-01-05
