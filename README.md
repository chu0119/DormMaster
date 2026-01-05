# 宿舍大师 (DormMaster)

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-777bb3.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1.svg)](https://www.mysql.com/)
[![Version](https://img.shields.io/badge/version-1.1.0-green.svg)]()

**DormMaster** - 一款基于 PHP + MySQL 的现代化智能宿舍管理系统

> 为宿舍管理而生，让管理更简单、更智能、更高效

一个功能完整的宿舍公寓楼管理系统，支持多角色权限管理、批量操作、数据可视化和 CSV 编码自动转换功能。

## ✨ 功能特性

### 🏢 宿舍管理
- **楼栋管理**：添加、编辑、删除宿舍楼
- **房间管理**：批量添加房间，支持楼层范围设置
- **床位管理**：实时显示房间占用情况

### 👥 学生管理
- **学生信息**：完整的学生档案管理
- **CSV导入**：支持 GBK/UTF-8 编码自动转换
- **批量操作**：快速导入大量学生数据

### 🔑 多角色系统
- **管理员**：系统配置、用户管理、数据统计
- **教师**：查看分配情况、申请调整
- **宿管**：管理所负责楼栋、查看入住情况
- **学生**：查看个人宿舍信息

### 📊 数据可视化
- **ECharts 集成**：饼图、柱状图、折线图
- **实时统计**：入住率、空床位、分配率
- **多维分析**：按楼栋、楼层、性别统计

### 🎯 批量分配
- **智能搜索**：按学号、姓名、学院、专业搜索
- **双栏布局**：左侧可选学生，右侧已选列表
- **实时容量**：自动计算剩余床位，超限警告
- **便捷操作**：全选、清空、单独移除

## 🚀 快速开始

### 项目名称

| 语言 | 名称 | 英文名 | 缩写 |
|------|------|--------|------|
| 中文 | 宿舍大师 | DormMaster | DM |
| 英文 | Dormitory Master System | DormMaster | DM |

### 环境要求

- PHP >= 7.4
- MySQL >= 5.7
- Apache/Nginx
- PDO 扩展
- GD 扩展（用于图表）

### 安装步骤

1. **下载项目**
```bash
# 方式一：Git 克隆（推荐）
git clone https://github.com/yourusername/DormMaster.git
cd DormMaster

# 方式二：下载压缩包
# 访问 https://github.com/yourusername/DormMaster/releases
# 下载并解压
```

2. **配置数据库**
```bash
# 复制配置文件模板
cp config/config.example.php config/config.php

# 编辑配置文件
vim config/config.php
```

修改数据库连接信息：
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'dormitory');
define('DB_USER', 'root');
define('DB_PASS', 'password');
```

3. **初始化数据库**
```bash
# 访问安装页面
http://your-domain/install.php
```

或手动导入：
```bash
mysql -u root -p dormitory < database.sql
```

4. **设置权限**
```bash
# 确保 logs 目录可写
chmod 755 logs/
chmod 644 logs/*.log
```

5. **访问系统**
```
# 首页（自动跳转到登录页）
http://your-domain/

# 登录页面
http://your-domain/login.php

# 管理后台
http://your-domain/admin/
```

### 默认账号

- **管理员**：admin / admin123

## 📁 项目结构

```
DormMaster/
├── config/
│   ├── config.example.php      # 配置文件模板
│   └── config.php              # 实际配置（需创建）
├── app/
│   ├── Database.php            # 数据库操作类
│   ├── Auth.php                # 认证与权限管理
│   ├── helpers.php             # 辅助函数（含编码转换）
│   └── Models/
│       ├── DormitoryBuilding.php
│       ├── Room.php
│       └── Student.php
├── admin/                      # 管理后台
│   ├── index.php               # 管理首页
│   ├── buildings.php           # 楼栋管理
│   ├── rooms.php               # 房间管理
│   ├── students.php            # 学生管理
│   ├── users.php               # 用户管理
│   ├── assignments.php         # 宿舍分配（批量）
│   ├── import.php              # CSV导入
│   ├── statistics.php          # 统计图表
│   └── logout.php              # 登出
├── teacher/                    # 教师角色
│   └── index.php
├── housekeeper/                # 宿管角色
│   └── index.php
├── student/                    # 学生角色
│   └── index.php
├── logs/                       # 日志目录（需创建）
│   ├── operation.log
│   ├── error.log
│   └── import.log
├── database.sql                # 数据库结构
├── install.php                 # 安装向导
├── login.php                   # 登录页面
├── index.php                   # 主入口
├── README.md                   # 本文件
├── INSTALL.md                  # 详细安装指南
├── USAGE.md                    # 使用手册
├── CHANGELOG.md                # 更新日志
└── LICENSE                     # 许可证
```

## 📖 使用指南

### 1. 系统初始化
首次使用请访问 `install.php` 按向导完成安装。

### 2. 用户登录
- 访问 `login.php`
- 输入用户名和密码
- 根据角色自动跳转到对应首页

### 3. 数据导入
1. 准备 CSV 文件（10列，UTF-8 或 GBK 编码）
2. 进入管理后台 → 学生管理 → 批量导入
3. 上传文件，系统自动检测编码并转换
4. 查看导入结果

### 4. 宿舍分配
1. 进入管理后台 → 宿舍分配
2. 点击"批量分配"
3. 选择目标房间
4. 搜索并选择学生
5. 确认分配

### 5. 数据统计
1. 进入管理后台 → 数据统计
2. 查看各类图表
3. 导出数据（可选）

## 🔧 核心功能详解

### CSV 导入编码转换
系统支持 GBK、GB2312、UTF-8 编码的 CSV 文件自动转换：
```php
// 自动检测并转换编码
importCsv($filePath, $callback, 'auto');
```

### 批量分配学生选择器
- **实时搜索**：输入即搜索，无需点击
- **双栏布局**：一目了然，操作便捷
- **容量检查**：实时计算，超限警告
- **已选管理**：右侧列表，单独移除

### 多角色权限控制
```php
$auth->requireRole([1]); // 仅管理员
$auth->requireRole([1, 2]); // 管理员和教师
```

### 日志系统
- 操作日志：记录所有关键操作
- 错误日志：记录系统异常
- 导入日志：记录 CSV 导入详情

## 🛠️ 开发指南

### 代码规范
- 使用 PSR-12 编码风格
- 所有 SQL 查询使用预处理语句
- 所有用户输入必须验证和过滤
- 所有输出必须转义

### 添加新功能
1. 在 `app/Models/` 创建模型类
2. 在 `admin/` 或对应角色目录创建页面
3. 更新权限配置
4. 添加日志记录

### 安全考虑
- CSRF 保护所有 POST 请求
- 密码使用 `password_hash()` 加密
- SQL 使用预处理防止注入
- XSS 防护：所有输出使用 `h()` 函数转义

## 🐛 常见问题

### Q: CSV 导入失败，提示编码错误
**A:** 系统会自动检测编码，如果失败请：
1. 使用 UTF-8 编码保存 CSV
2. 或使用 GBK 编码，系统会自动转换
3. 查看 `logs/import.log` 获取详细错误

### Q: 批量分配时看不到学生列表
**A:**
1. 确保有未分配的学生
2. 检查 `students` 表中 `status = 1`
3. 查看 `room_assignments` 表，确认学生未被分配

### Q: 图表不显示
**A:**
1. 确保安装了 GD 扩展
2. 检查浏览器是否支持 Canvas
3. 查看浏览器控制台错误

### Q: 如何重置管理员密码
**A:** 执行以下 SQL：
```sql
UPDATE users SET password = '$2y$10$...' WHERE username = 'admin';
```
使用 `password_hash('新密码', PASSWORD_DEFAULT)` 生成新密码的哈希值。

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

## 📄 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 🙏 致谢

- [ECharts](https://echarts.apache.org/) - 数据可视化库
- [PHP](https://www.php.net/) - 后端语言
- [MySQL](https://www.mysql.com/) - 数据库

## 📞 联系方式

如有问题或建议，请通过以下方式联系：
- 提交 Issue
- 发送邮件至：your-email@example.com

---

**版本**: v1.1.0
**最后更新**: 2026-01-05
**状态**: ✅ 生产环境就绪