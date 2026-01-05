# 数据库设计文档

## 📊 数据库概览

### 数据库信息
- **数据库名**: `dormitory_system`
- **字符集**: `utf8mb4`
- **排序规则**: `utf8mb4_unicode_ci`

### 数据表列表
| 表名 | 说明 | 创建时间 |
|------|------|----------|
| `users` | 用户表 | ✓ |
| `students` | 学生信息表 | ✓ |
| `dormitory_buildings` | 宿舍楼表 | ✓ |
| `rooms` | 房间表 | ✓ |
| `room_assignments` | 宿舍分配表 | ✓ |
| `operation_logs` | 操作日志表 | ✓ |

---

## 🗂️ 详细表结构

### 1. users（用户表）
存储系统用户信息，包括管理员、教师、宿管和学生账号。

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role TINYINT NOT NULL COMMENT '1:管理员, 2:教师, 3:宿管, 4:学生',
    real_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    related_id INT COMMENT '关联ID（教师编号/宿管ID/学生ID）',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**字段说明**:
- `id`: 主键，自增
- `username`: 用户名，唯一
- `password`: 密码（加密存储）
- `role`: 角色（1=管理员, 2=教师, 3=宿管, 4=学生）
- `real_name`: 真实姓名
- `phone`: 联系电话
- `related_id`: 关联ID（用于关联教师、宿管或学生信息）

**示例数据**:
```sql
INSERT INTO users (username, password, role, real_name) VALUES
('admin', '$2y$10$...', 1, '系统管理员'),
('teacher01', '$2y$10$...', 2, '张老师'),
('keeper01', '$2y$10$...', 3, '李宿管'),
('2021001', '$2y$10$...', 4, '王学生');
```

---

### 2. students（学生信息表）
存储学生的基本信息。

```sql
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    real_name VARCHAR(50) NOT NULL,
    gender TINYINT NOT NULL COMMENT '1:男, 2:女',
    college VARCHAR(100) NOT NULL,
    major VARCHAR(100) NOT NULL,
    class VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    id_card VARCHAR(18) UNIQUE,
    admission_date DATE,
    status TINYINT DEFAULT 1 COMMENT '1:在读, 2:毕业, 3:休学',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_college (college),
    INDEX idx_major (major),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**字段说明**:
- `student_id`: 学号，唯一
- `real_name`: 姓名
- `gender`: 性别（1=男, 2=女）
- `college`: 学院
- `major`: 专业
- `class`: 班级
- `phone`: 联系电话
- `id_card`: 身份证号，唯一
- `admission_date`: 入学日期
- `status`: 状态（1=在读, 2=毕业, 3=休学）

**示例数据**:
```sql
INSERT INTO students (student_id, real_name, gender, college, major, class, phone, id_card, admission_date) VALUES
('2021001', '张三', 1, '计算机学院', '软件工程', '2021级1班', '13800138000', '110101200001011234', '2021-09-01'),
('2021002', '李四', 2, '计算机学院', '网络工程', '2021级2班', '13900139000', '110101200001022345', '2021-09-01');
```

---

### 3. dormitory_buildings（宿舍楼表）
存储宿舍楼信息。

```sql
CREATE TABLE dormitory_buildings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    building_name VARCHAR(50) NOT NULL,
    building_code VARCHAR(20) UNIQUE NOT NULL,
    address VARCHAR(200),
    floor_count INT NOT NULL,
    gender TINYINT NOT NULL COMMENT '1:男, 2:女, 3:混合',
    status TINYINT DEFAULT 1 COMMENT '1:正常, 2:维修, 3:停用',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_building_code (building_code),
    INDEX idx_gender (gender)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**字段说明**:
- `building_name`: 楼栋名称（如"1号楼"）
- `building_code`: 楼栋编码（如"B01"），唯一
- `address`: 详细地址
- `floor_count`: 楼层数
- `gender`: 适用性别（1=男, 2=女, 3=混合）
- `status`: 状态（1=正常, 2=维修, 3=停用）

**示例数据**:
```sql
INSERT INTO dormitory_buildings (building_name, building_code, address, floor_count, gender) VALUES
('1号楼', 'B01', '校园北区1号', 6, 1),
('2号楼', 'B02', '校园北区2号', 6, 2),
('3号楼', 'B03', '校园南区1号', 8, 3);
```

---

### 4. rooms（房间表）
存储房间信息。

```sql
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    building_id INT NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    floor INT NOT NULL,
    bed_count INT NOT NULL,
    current_occupancy INT DEFAULT 0,
    status TINYINT DEFAULT 1 COMMENT '1:正常, 2:维修, 3:停用',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_room (building_id, room_number),
    INDEX idx_building_id (building_id),
    INDEX idx_floor (floor),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**字段说明**:
- `building_id`: 所属楼栋ID
- `room_number`: 房间号（如"101"）
- `floor`: 楼层
- `bed_count`: 总床位数
- `current_occupancy`: 当前入住人数
- `status`: 状态（1=正常, 2=维修, 3=停用）
- `description`: 备注说明

**示例数据**:
```sql
INSERT INTO rooms (building_id, room_number, floor, bed_count, current_occupancy) VALUES
(1, '101', 1, 4, 2),
(1, '102', 1, 4, 4),
(1, '201', 2, 4, 0);
```

---

### 5. room_assignments（宿舍分配表）
存储学生宿舍分配关系。

```sql
CREATE TABLE room_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    student_id INT NOT NULL,
    bed_number INT NOT NULL,
    move_in_date DATE NOT NULL,
    move_out_date DATE,
    status TINYINT DEFAULT 1 COMMENT '1:在住, 2:已退宿',
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_room_id (room_id),
    INDEX idx_student_id (student_id),
    INDEX idx_status (status),
    INDEX idx_move_in_date (move_in_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**字段说明**:
- `room_id`: 房间ID
- `student_id`: 学生ID
- `bed_number`: 床位号
- `move_in_date`: 入住日期
- `move_out_date`: 退宿日期
- `status`: 状态（1=在住, 2=已退宿）
- `created_by`: 创建人（用户ID）

**示例数据**:
```sql
INSERT INTO room_assignments (room_id, student_id, bed_number, move_in_date, created_by) VALUES
(1, 1, 1, '2021-09-01', 1),
(1, 2, 2, '2021-09-01', 1);
```

---

### 6. operation_logs（操作日志表）
存储系统操作日志。

```sql
CREATE TABLE operation_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_module (module),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**字段说明**:
- `user_id`: 操作用户ID
- `module`: 操作模块（如"student", "room", "assignment"）
- `action`: 操作类型（如"create", "update", "delete", "login"）
- `description`: 操作描述
- `ip_address`: 用户IP地址
- `user_agent`: 用户浏览器信息

**示例数据**:
```sql
INSERT INTO operation_logs (user_id, module, action, description, ip_address) VALUES
(1, 'user', 'login', '管理员登录', '192.168.1.100'),
(1, 'student', 'create', '添加学生: 张三', '192.168.1.100'),
(1, 'assignment', 'batch', '批量分配: 5名学生', '192.168.1.100');
```

---

## 🔗 表关系图

```
users
  ↓ (created_by)
operation_logs

students
  ↓ (student_id)
room_assignments ← (room_id) → rooms ← (building_id) → dormitory_buildings

users (related_id) → students (学生账号)
users (related_id) → dormitory_buildings (宿管管辖楼栋)
```

---

## 📈 索引说明

### 必要索引
所有表的主键都已自动创建索引。

### 业务索引
- `users.username`: 唯一索引，加速登录查询
- `users.role`: 普通索引，加速角色筛选
- `students.student_id`: 唯一索引，加速学号查询
- `students.college/major`: 普通索引，加速学院专业筛选
- `dormitory_buildings.building_code`: 唯一索引
- `rooms.building_id + room_number`: 联合唯一索引
- `room_assignments.room_id/student_id`: 普通索引，加速分配查询

---

## 🎯 常用查询示例

### 1. 查询房间入住率
```sql
SELECT
    r.*,
    b.building_name,
    (r.current_occupancy / r.bed_count * 100) as occupancy_rate
FROM rooms r
JOIN dormitory_buildings b ON r.building_id = b.id
WHERE r.status = 1;
```

### 2. 查询空床位
```sql
SELECT
    b.building_name,
    r.room_number,
    (r.bed_count - r.current_occupancy) as available_beds
FROM rooms r
JOIN dormitory_buildings b ON r.building_id = b.id
WHERE r.current_occupancy < r.bed_count
AND r.status = 1;
```

### 3. 查询学生分配详情
```sql
SELECT
    s.student_id,
    s.real_name,
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

### 4. 统计各学院入住人数
```sql
SELECT
    s.college,
    COUNT(*) as student_count
FROM room_assignments ra
JOIN students s ON ra.student_id = s.id
WHERE ra.status = 1
GROUP BY s.college;
```

### 5. 查询未分配学生
```sql
SELECT s.*
FROM students s
LEFT JOIN room_assignments ra ON s.id = ra.student_id AND ra.status = 1
WHERE ra.id IS NULL AND s.status = 1;
```

---

## 🛡️ 数据完整性约束

### 外键关系
```sql
-- 房间表外键
ALTER TABLE rooms ADD CONSTRAINT fk_rooms_building
FOREIGN KEY (building_id) REFERENCES dormitory_buildings(id)
ON DELETE CASCADE;

-- 分配表外键
ALTER TABLE room_assignments ADD CONSTRAINT fk_assignments_room
FOREIGN KEY (room_id) REFERENCES rooms(id)
ON DELETE CASCADE;

ALTER TABLE room_assignments ADD CONSTRAINT fk_assignments_student
FOREIGN KEY (student_id) REFERENCES students(id)
ON DELETE CASCADE;
```

### 唯一约束
- `users.username`: 防止重复用户名
- `students.student_id`: 防止重复学号
- `students.id_card`: 防止重复身份证
- `dormitory_buildings.building_code`: 防止重复楼栋编码
- `rooms.building_id + room_number`: 防止同楼栋重复房间号

---

## 📝 数据库维护

### 定期维护任务
```sql
-- 1. 优化表
OPTIMIZE TABLE users, students, dormitory_buildings, rooms, room_assignments;

-- 2. 分析表
ANALYZE TABLE users, students, dormitory_buildings, rooms, room_assignments;

-- 3. 备份数据库
mysqldump -u root -p dormitory_system > backup_$(date +%Y%m%d).sql
```

### 数据清理
```sql
-- 清理3个月前的日志
DELETE FROM operation_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);

-- 归档退宿记录
UPDATE room_assignments SET status = 2 WHERE move_out_date < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

---

**版本**: v1.1.0
**更新时间**: 2026-01-05
**维护者**: 开发团队
