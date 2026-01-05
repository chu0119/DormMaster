-- 宿舍公寓楼管理系统数据库结构
-- 适用于MySQL 5.7+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. 系统配置表
DROP TABLE IF EXISTS `sys_config`;
CREATE TABLE `sys_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(50) NOT NULL COMMENT '配置键',
  `config_value` text COMMENT '配置值',
  `config_name` varchar(100) DEFAULT NULL COMMENT '配置名称',
  `config_desc` varchar(255) DEFAULT NULL COMMENT '配置说明',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

-- 2. 用户表
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码（加密存储）',
  `real_name` varchar(50) DEFAULT NULL COMMENT '真实姓名',
  `role` tinyint(4) NOT NULL COMMENT '角色：1-管理员，2-教师，3-宿管，4-学生',
  `phone` varchar(20) DEFAULT NULL COMMENT '联系电话',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-正常，0-禁用',
  `last_login` datetime DEFAULT NULL COMMENT '最后登录时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 3. 学生信息表
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL COMMENT '学号',
  `real_name` varchar(50) NOT NULL COMMENT '姓名',
  `gender` tinyint(1) DEFAULT NULL COMMENT '性别：1-男，2-女',
  `college` varchar(100) DEFAULT NULL COMMENT '学院',
  `major` varchar(100) DEFAULT NULL COMMENT '专业',
  `class_name` varchar(50) DEFAULT NULL COMMENT '班级',
  `phone` varchar(20) DEFAULT NULL COMMENT '联系电话',
  `id_card` varchar(20) DEFAULT NULL COMMENT '身份证号',
  `entrance_date` date DEFAULT NULL COMMENT '入学日期',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-在读，2-毕业，3-休学',
  `user_id` int(11) DEFAULT NULL COMMENT '关联用户ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_student_id` (`student_id`),
  KEY `idx_college` (`college`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='学生信息表';

-- 4. 宿舍楼表
DROP TABLE IF EXISTS `dormitory_buildings`;
CREATE TABLE `dormitory_buildings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `building_name` varchar(50) NOT NULL COMMENT '楼栋名称（如：1号楼）',
  `building_code` varchar(20) NOT NULL COMMENT '楼栋编码',
  `address` varchar(255) DEFAULT NULL COMMENT '地址',
  `floor_count` int(11) DEFAULT 0 COMMENT '楼层数',
  `room_count` int(11) DEFAULT 0 COMMENT '总房间数',
  `gender_type` tinyint(1) DEFAULT 1 COMMENT '适用性别：1-男生，2-女生，3-混合',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `manager_id` int(11) DEFAULT NULL COMMENT '宿管ID',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-正常，0-停用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_building_code` (`building_code`),
  KEY `idx_manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='宿舍楼表';

-- 5. 宿舍模板表
DROP TABLE IF EXISTS `room_templates`;
CREATE TABLE `room_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(50) NOT NULL COMMENT '模板名称',
  `bed_count` int(11) NOT NULL COMMENT '床位数',
  `room_type` varchar(20) DEFAULT NULL COMMENT '房间类型（如：标准间、四人间）',
  `area` decimal(6,2) DEFAULT NULL COMMENT '面积（平方米）',
  `has_balcony` tinyint(1) DEFAULT 0 COMMENT '是否有阳台：0-无，1-有',
  `has_bathroom` tinyint(1) DEFAULT 0 COMMENT '是否有独立卫生间：0-无，1-有',
  `has_ac` tinyint(1) DEFAULT 0 COMMENT '是否有空调：0-无，1-有',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='宿舍模板表';

-- 6. 宿舍房间表
DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `building_id` int(11) NOT NULL COMMENT '楼栋ID',
  `floor` int(11) NOT NULL COMMENT '楼层',
  `room_number` varchar(20) NOT NULL COMMENT '房间号（如：101、201）',
  `room_name` varchar(50) DEFAULT NULL COMMENT '房间名称',
  `template_id` int(11) DEFAULT NULL COMMENT '模板ID',
  `bed_count` int(11) NOT NULL COMMENT '床位数',
  `current_occupancy` int(11) DEFAULT 0 COMMENT '当前入住人数',
  `gender_type` tinyint(1) DEFAULT 1 COMMENT '适用性别：1-男生，2-女生',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-正常，2-维修中，3-停用',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_room_unique` (`building_id`, `floor`, `room_number`),
  KEY `idx_building_floor` (`building_id`, `floor`),
  KEY `idx_template_id` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='宿舍房间表';

-- 7. 宿舍分配表
DROP TABLE IF EXISTS `room_assignments`;
CREATE TABLE `room_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL COMMENT '房间ID',
  `student_id` int(11) NOT NULL COMMENT '学生ID',
  `bed_number` int(11) DEFAULT NULL COMMENT '床位号',
  `move_in_date` date NOT NULL COMMENT '入住日期',
  `move_out_date` date DEFAULT NULL COMMENT '退宿日期',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-在住，2-已退宿',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_by` int(11) DEFAULT NULL COMMENT '操作人',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room_id` (`room_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='宿舍分配表';

-- 8. 教师管辖关系表
DROP TABLE IF EXISTS `teacher_relations`;
CREATE TABLE `teacher_relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL COMMENT '教师用户ID',
  `building_id` int(11) DEFAULT NULL COMMENT '管辖楼栋ID（为空则管辖所有）',
  `college` varchar(100) DEFAULT NULL COMMENT '管辖学院',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_building` (`teacher_id`, `building_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='教师管辖关系表';

-- 9. 宿舍巡查记录表
DROP TABLE IF EXISTS `inspection_records`;
CREATE TABLE `inspection_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL COMMENT '房间ID',
  `inspector_id` int(11) NOT NULL COMMENT '巡查人ID',
  `inspection_date` date NOT NULL COMMENT '巡查日期',
  `score` int(11) DEFAULT NULL COMMENT '评分（0-100）',
  `issues` text COMMENT '发现问题',
  `notes` text COMMENT '备注',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-正常，2-需整改',
  `photos` text COMMENT '照片（JSON格式存储URL）',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room_date` (`room_id`, `inspection_date`),
  KEY `idx_inspector` (`inspector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='宿舍巡查记录表';

-- 10. 申请记录表
DROP TABLE IF EXISTS `application_records`;
CREATE TABLE `application_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL COMMENT '学生ID',
  `type` tinyint(2) NOT NULL COMMENT '申请类型：1-调宿，2-退宿，3-换床，4-其他',
  `reason` text NOT NULL COMMENT '申请理由',
  `status` tinyint(1) DEFAULT 0 COMMENT '状态：0-待审核，1-通过，2-拒绝',
  `reviewer_id` int(11) DEFAULT NULL COMMENT '审核人ID',
  `review_note` text COMMENT '审核意见',
  `review_date` datetime DEFAULT NULL COMMENT '审核时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_type` (`student_id`, `type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='申请记录表';

-- 11. 数据统计表
DROP TABLE IF EXISTS `statistics`;
CREATE TABLE `statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stat_date` date NOT NULL COMMENT '统计日期',
  `type` varchar(50) NOT NULL COMMENT '统计类型',
  `data` json NOT NULL COMMENT '统计数据（JSON）',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_date_type` (`stat_date`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='数据统计表';

-- 12. 操作日志表
DROP TABLE IF EXISTS `operation_logs`;
CREATE TABLE `operation_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '操作用户ID',
  `module` varchar(50) NOT NULL COMMENT '操作模块',
  `action` varchar(50) NOT NULL COMMENT '操作动作',
  `content` text COMMENT '操作内容',
  `ip_address` varchar(50) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` text COMMENT '用户代理',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_action` (`user_id`, `action`),
  KEY `idx_module` (`module`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表';

-- 插入初始数据
-- 管理员账号：admin / admin123
INSERT INTO `users` (`username`, `password`, `real_name`, `role`, `status`)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系统管理员', 1, 1);

-- 默认宿舍模板
INSERT INTO `room_templates` (`template_name`, `bed_count`, `room_type`, `area`, `has_balcony`, `has_bathroom`, `has_ac`, `description`) VALUES
('四人间', 4, '标准四人间', 20.00, 1, 1, 1, '标准四人间，带阳台、独立卫生间和空调'),
('六人间', 6, '标准六人间', 25.00, 1, 1, 1, '标准六人间，带阳台、独立卫生间和空调'),
('八人间', 8, '标准八人间', 30.00, 1, 1, 0, '标准八人间，带阳台和独立卫生间');

-- 系统配置
INSERT INTO `sys_config` (`config_key`, `config_value`, `config_name`, `config_desc`) VALUES
('system_name', '智慧宿舍管理系统', '系统名称', '系统显示名称'),
('system_version', '1.0.0', '系统版本', '当前系统版本号'),
('max_upload_size', '10', '最大上传大小', '文件上传最大限制（MB）'),
('allow_registration', '0', '允许注册', '是否允许用户自助注册：0-否，1-是');

SET FOREIGN_KEY_CHECKS = 1;