# 发布说明 - v1.1.0

## 🎉 宿舍大师 (DormMaster) v1.1.0 发布

**项目名称**: 宿舍大师 / DormMaster
**发布日期**: 2026-01-05
**版本类型**: 功能增强版本
**状态**: ✅ 生产环境就绪

---

## 📦 发布内容

### 核心功能
- ✅ 多角色权限系统（管理员、教师、宿管、学生）
- ✅ 宿舍楼和房间管理
- ✅ 学生信息管理
- ✅ 批量导入导出（支持 GBK/UTF-8）
- ✅ 宿舍分配（单个和批量）
- ✅ 数据统计与可视化
- ✅ 安装向导

### 新增功能（v1.1.0）
- ✅ **批量分配学生选择器**：双栏布局，实时搜索，容量检查
- ✅ **CSV 编码自动转换**：支持 GBK/GB2312/UTF-8
- ✅ **日志系统**：操作日志、错误日志、导入日志
- ✅ **在线日志查看器**：查看、下载、清空日志

### 优化改进
- ✅ 修复变量插值错误
- ✅ 增强安全防护
- ✅ 优化代码质量
- ✅ 改进用户体验

---

## 🚀 快速开始

### 1. 下载项目
```bash
git clone https://github.com/yourusername/DormMaster.git
cd DormMaster
```

### 2. 配置数据库
```bash
cp config/config.example.php config/config.php
# 编辑 config/config.php 填入数据库信息
```

### 3. 访问安装向导
```
http://your-domain.com/install.php
```

### 4. 登录系统
```
用户名: admin
密码: admin123
```

---

## 📊 版本对比

| 功能 | v1.0.0 | v1.1.0 |
|------|--------|--------|
| 基础管理 | ✅ | ✅ |
| 批量导入 | ✅ | ✅ + 编码转换 |
| 宿舍分配 | ✅ | ✅ + 优化UI |
| 数据统计 | ✅ | ✅ |
| 日志系统 | ❌ | ✅ |
| 安全防护 | ✅ | ✅ + 增强 |

---

## 🔧 升级指南

### 从 v1.0.0 升级

1. **备份数据**
```bash
mysqldump -u root -p dormitory_system > backup.sql
```

2. **更新代码**
```bash
git pull origin main
```

3. **更新配置**
```bash
# 如果有新配置项，添加到 config/config.php
```

4. **更新数据库（可选）**
```sql
-- 如果有新表或字段
source database.sql
```

5. **清除缓存**
```bash
# 删除 logs/ 目录下的旧日志（可选）
rm logs/*.log
```

---

## 📋 安装要求

### 最低要求
- PHP >= 7.4
- MySQL >= 5.7
- Web 服务器（Apache/Nginx）

### 推荐配置
- PHP 8.0+
- MySQL 8.0+
- Nginx
- 宝塔面板

### PHP 扩展
- PDO_MySQL
- GD（图表）
- MBString
- OpenSSL

---

## 🗂️ 目录结构

```
dormitory-system/
├── README.md              # 项目说明
├── INSTALL.md             # 安装指南
├── USAGE.md               # 使用手册
├── CHANGELOG.md           # 更新日志
├── LICENSE                # 许可证
├── .gitignore             # Git忽略
├── config/
│   └── config.example.php # 配置模板
├── app/                   # 核心代码
│   ├── Database.php
│   ├── Auth.php
│   ├── helpers.php
│   └── Models/
├── admin/                 # 管理后台
├── teacher/               # 教师角色
├── housekeeper/           # 宿管角色
├── student/               # 学生角色
├── logs/                  # 日志目录
├── uploads/               # 上传目录
├── docs/                  # 文档
│   ├── PROJECT_OVERVIEW.md
│   ├── DATABASE_SCHEMA.md
│   ├── QUICK_REFERENCE.md
│   ├── CONTRIBUTING.md
│   └── README.md
└── database.sql           # 数据库结构
```

---

## 🎯 主要特性详解

### 1. 批量分配学生选择器
- **双栏布局**：左侧可选学生，右侧已选列表
- **实时搜索**：输入关键词即时筛选
- **容量检查**：实时计算剩余床位，超限警告
- **便捷操作**：全选、清空、单独移除

### 2. CSV 编码自动转换
- **自动检测**：GBK/GB2312/UTF-8
- **智能转换**：GBK 自动转 UTF-8
- **错误处理**：详细日志记录
- **兼容性**：支持 Windows 默认 CSV

### 3. 日志系统
- **操作日志**：记录所有关键操作
- **错误日志**：记录系统异常
- **导入日志**：记录 CSV 导入详情
- **在线查看**：无需下载即可查看

---

## 🐛 已知问题

### 无
当前版本无已知问题。

---

## 📞 技术支持

### 获取帮助
1. **文档**: 查看 README.md, INSTALL.md, USAGE.md
2. **日志**: 访问 `admin/logs.php` 查看系统日志
3. **Issue**: 在 GitHub 提交 Issue

### 报告问题
请提供：
- 错误截图
- 操作步骤
- PHP 版本
- 浏览器类型
- 日志内容

---

## 🤝 贡献

欢迎贡献代码、文档或建议！

- **提交 Bug**: GitHub Issues
- **贡献代码**: Pull Request
- **改进文档**: 直接编辑提交

详细请查看 [CONTRIBUTING.md](docs/CONTRIBUTING.md)

---

## 📄 许可证

本项目采用 MIT 许可证 - 详见 [LICENSE](LICENSE)

---

## 🙏 致谢

感谢所有使用者和贡献者的支持！

---

## 🔖 版本历史

### v1.1.0 (2026-01-05)
- ✅ 新增批量分配学生选择器
- ✅ 新增 CSV 编码自动转换
- ✅ 新增日志系统和查看器
- ✅ 修复变量插值错误
- ✅ 增强安全防护
- ✅ 优化代码质量

### v1.0.0 (2026-01-04)
- ✅ 基础功能完成
- ✅ 多角色系统
- ✅ 宿舍管理
- ✅ 学生管理
- ✅ 数据统计
- ✅ 安装向导

---

**发布状态**: ✅ 生产环境就绪
**维护者**: 开发团队
**项目地址**: https://github.com/yourusername/DormMaster
