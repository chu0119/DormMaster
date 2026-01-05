# 项目概览

## 📖 项目简介

**宿舍大师 (DormMaster)** 是一个基于 PHP + MySQL 开发的现代化宿舍公寓楼管理系统。

### 项目目标
- 简化宿舍管理流程
- 提高管理效率
- 提供数据可视化支持
- 支持多角色协同工作

### 技术栈
- **后端**: PHP 7.4+
- **数据库**: MySQL 5.7+
- **前端**: HTML5 + CSS3 + JavaScript
- **图表库**: ECharts 5.0
- **安全**: CSRF 防护、密码加密、SQL 预处理

## 🏗️ 系统架构

### 整体架构
```
┌─────────────────────────────────────────┐
│           用户界面层 (UI)                │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐  │
│  │ 管理员  │ │ 教师    │ │ 宿管    │  │
│  │ 界面    │ │ 界面    │ │ 界面    │  │
│  └─────────┘ └─────────┘ └─────────┘  │
└─────────────────────────────────────────┘
                   ↓
┌─────────────────────────────────────────┐
│           业务逻辑层 (PHP)               │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐  │
│  │ 控制器  │ │ 业务服  │ │ 权限验  │  │
│  │         │ │ 务器    │ │ 证器    │  │
│  └─────────┘ └─────────┘ └─────────┘  │
└─────────────────────────────────────────┘
                   ↓
┌─────────────────────────────────────────┐
│           数据访问层 (PDO)               │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐  │
│  │ 用户    │ │ 宿舍    │ │ 学生    │  │
│  │ 模型    │ │ 模型    │ │ 模型    │  │
│  └─────────┘ └─────────┘ └─────────┘  │
└─────────────────────────────────────────┘
                   ↓
┌─────────────────────────────────────────┐
│           数据存储层 (MySQL)             │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐  │
│  │ 用户表  │ │ 宿舍表  │ │ 学生表  │  │
│  │         │ │         │ │         │  │
│  └─────────┘ └─────────┘ └─────────┘  │
└─────────────────────────────────────────┘
```

### 数据流
```
用户请求 → 路由解析 → 权限验证 → 业务处理 → 数据访问 → 数据库 → 返回结果 → 渲染视图
```

## 📂 目录结构详解

### 根目录
```
dormitory-system/
├── config/              # 配置文件
├── app/                 # 核心代码
├── admin/              # 管理后台
├── teacher/            # 教师角色
├── housekeeper/        # 宿管角色
├── student/            # 学生角色
├── logs/               # 日志目录
├── docs/               # 文档
├── database.sql        # 数据库结构
├── install.php         # 安装向导
├── login.php           # 登录页面
├── index.php           # 入口文件
├── README.md           # 项目说明
├── INSTALL.md          # 安装指南
├── USAGE.md            # 使用手册
├── CHANGELOG.md        # 更新日志
├── LICENSE             # 许可证
└── .gitignore          # Git 忽略文件
```

### app/ 目录详解
```
app/
├── Database.php         # 数据库操作类
│   ├── getInstance()    # 单例模式获取实例
│   ├── getAll()         # 查询多条数据
│   ├── getRow()         # 查询单条数据
│   ├── getOne()         # 查询单个值
│   ├── insert()         # 插入数据
│   ├── update()         # 更新数据
│   ├── delete()         # 删除数据
│   └── query()          # 执行 SQL
│
├── Auth.php             # 认证与权限类
│   ├── login()          # 用户登录
│   ├── logout()         # 用户登出
│   ├── isLoggedIn()     # 检查登录状态
│   ├── getUserId()      # 获取当前用户 ID
│   ├── requireRole()    # 验证角色权限
│   └── logOperation()   # 记录操作日志
│
├── helpers.php          # 辅助函数
│   ├── h()              # HTML 转义
│   ├── getPost()        # 获取 POST 数据
│   ├── getGet()         # 获取 GET 数据
│   ├── verifyCsrfToken()# 验证 CSRF 令牌
│   ├── generateCsrfToken()# 生成 CSRF 令牌
│   ├── redirect()       # 重定向
│   ├── importCsv()      # CSV 导入（含编码转换）
│   └── convertEncoding()# 编码转换
│
└── Models/              # 数据模型
    ├── DormitoryBuilding.php  # 宿舍楼模型
    ├── Room.php               # 房间模型
    └── Student.php            # 学生模型
```

### admin/ 目录详解
```
admin/
├── index.php           # 管理首页（仪表盘）
├── buildings.php       # 宿舍楼管理
├── rooms.php           # 房间管理
├── students.php        # 学生管理
├── users.php           # 用户管理
├── assignments.php     # 宿舍分配（批量）
├── import.php          # CSV 导入
├── statistics.php      # 统计图表
└── logout.php          # 登出
```

## 🗄️ 数据库设计

### 核心表结构

#### 1. users（用户表）
```sql
id, username, password, role, real_name, phone, created_at, updated_at
```
- 存储系统用户信息
- role: 1=管理员, 2=教师, 3=宿管, 4=学生

#### 2. students（学生表）
```sql
id, student_id, real_name, gender, college, major, class, phone, id_card, admission_date, status, created_at
```
- 存储学生基本信息
- status: 在读/毕业/休学

#### 3. dormitory_buildings（宿舍楼表）
```sql
id, building_name, building_code, address, floor_count, gender, status, created_at
```
- 存储宿舍楼信息
- gender: 1=男, 2=女, 3=混合

#### 4. rooms（房间表）
```sql
id, building_id, room_number, floor, bed_count, current_occupancy, status, created_at
```
- 存储房间信息
- status: 1=正常, 2=维修, 3=停用

#### 5. room_assignments（宿舍分配表）
```sql
id, room_id, student_id, bed_number, move_in_date, status, created_by, created_at
```
- 存储分配关系
- status: 1=在住, 2=已退宿

## 🔐 安全机制

### 1. CSRF 防护
- 所有 POST 请求生成令牌
- 提交时验证令牌有效性
- 防止跨站请求伪造

### 2. 密码安全
- 使用 `password_hash()` 加密
- 支持 Bcrypt 算法
- 自动处理加盐

### 3. SQL 注入防护
- 使用 PDO 预处理语句
- 参数绑定
- 禁止直接拼接 SQL

### 4. XSS 防护
- 输出使用 `h()` 函数转义
- 防止 HTML/JS 注入

### 5. 权限控制
- 角色-based 访问控制
- 页面级权限验证
- 操作级权限检查

## 📊 核心功能流程

### 1. 批量分配流程
```
选择房间 → 搜索学生 → 选择学生 → 容量检查 → 确认分配 → 写入数据库 → 更新房间占用
```

### 2. CSV 导入流程
```
上传文件 → 检测编码 → 转换编码 → 解析 CSV → 验证数据 → 批量插入 → 记录日志
```

### 3. 统计图表流程
```
查询数据 → 聚合统计 → 格式化 → ECharts 渲染 → 展示图表
```

## 🎯 性能优化

### 1. 数据库优化
- 使用索引加速查询
- 避免 N+1 查询问题
- 合理使用缓存

### 2. 代码优化
- 单例模式减少实例化
- 懒加载资源
- 减少重复计算

### 3. 前端优化
- 合并 CSS/JS
- 使用 CDN 加速
- 图片压缩

## 🧪 测试策略

### 单元测试
- 数据库操作测试
- 业务逻辑测试
- 安全防护测试

### 集成测试
- 用户登录流程
- 宿舍分配流程
- CSV 导入流程

### UI 测试
- 表单验证
- 响应式布局
- 浏览器兼容性

## 🚀 部署方案

### 开发环境
- XAMPP/WAMP
- 手动配置

### 测试环境
- 宝塔面板
- 自动化部署

### 生产环境
- 云服务器
- 负载均衡
- CDN 加速

## 📈 未来规划

### 短期（1-3个月）
- [ ] 宿舍调宿申请流程
- [ ] 报修管理系统
- [ ] 宿舍公告发布

### 中期（3-6个月）
- [ ] 微信小程序接口
- [ ] 数据可视化大屏
- [ ] 智能分配算法

### 长期（6-12个月）
- [ ] 短信通知功能
- [ ] 邮件提醒功能
- [ ] 移动端 APP

## 🤝 贡献指南

### 提交规范
```
feat: 新功能
fix: Bug 修复
docs: 文档更新
style: 代码格式
refactor: 代码重构
test: 测试相关
chore: 构建/工具
```

### 开发流程
1. Fork 项目
2. 创建分支
3. 提交代码
4. Pull Request
5. 代码审查
6. 合并

## 📞 联系方式

- **项目地址**: https://github.com/yourusername/dormitory-system
- **问题反馈**: GitHub Issues
- **邮件咨询**: your-email@example.com

---

**版本**: v1.1.0
**更新时间**: 2026-01-05
**维护者**: 开发团队
