# 贡献指南

感谢您对智慧宿舍管理系统的贡献！🎉

## 📋 目录

- [如何贡献](#如何贡献)
- [开发环境设置](#开发环境设置)
- [代码规范](#代码规范)
- [提交规范](#提交规范)
- [Pull Request 流程](#pull-request-流程)
- [报告 Bug](#报告-bug)
- [功能建议](#功能建议)
- [代码审查](#代码审查)

---

## 如何贡献

有多种方式可以贡献：

### 1. 报告 Bug
发现问题？请提交 Issue。

### 2. 提出新功能
有好的想法？欢迎提出建议。

### 3. 提交代码
修复 Bug 或实现新功能。

### 4. 改进文档
让文档更清晰易懂。

### 5. 分享项目
向他人推荐本项目。

---

## 开发环境设置

### 前置要求
- PHP >= 7.4
- MySQL >= 5.7
- Git

### 步骤

1. **Fork 项目**
```bash
# 访问 https://github.com/yourusername/dormitory-system
# 点击 Fork 按钮
```

2. **克隆仓库**
```bash
git clone https://github.com/YOUR_USERNAME/dormitory-system.git
cd dormitory-system
```

3. **添加上游仓库**
```bash
git remote add upstream https://github.com/ORIGINAL_OWNER/dormitory-system.git
```

4. **创建分支**
```bash
# 为你的修改创建新分支
git checkout -b feature/your-feature-name

# 或修复 Bug
git checkout -b fix/your-bug-fix
```

5. **配置环境**
```bash
# 复制配置文件
cp config/config.example.php config/config.php

# 编辑配置文件，填入数据库信息
```

6. **安装依赖**
```bash
# 本项目无 Composer 依赖，直接使用
```

7. **运行测试**
```bash
# 确保所有功能正常
```

---

## 代码规范

### PHP 代码风格
遵循 PSR-12 标准。

#### 文件结构
```php
<?php
/**
 * 文件描述
 */

namespace App\Models;

use App\Database;

class Student
{
    // 类定义
}
```

#### 命名规范
- **类名**: `PascalCase`（大驼峰）
- **方法名**: `camelCase`（小驼峰）
- **变量名**: `camelCase`
- **常量**: `UPPER_CASE`
- **文件名**: `PascalCase.php`

#### 代码示例
```php
// ✅ 正确
public function getStudentById(int $id): ?array
{
    $sql = "SELECT * FROM students WHERE id = ?";
    return $this->db->getRow($sql, [$id]);
}

// ❌ 错误
public function get_student_by_id($id) {
    $sql = "SELECT * FROM students WHERE id = " . $id; // SQL 注入风险
    return $this->db->query($sql);
}
```

### HTML/CSS 规范
```html
<!-- ✅ 正确 -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">标题</h2>
    </div>
</div>

<!-- ❌ 错误 -->
<div class="card"><h2>标题</h2></div> <!-- 缺少结构 -->
```

### JavaScript 规范
```javascript
// ✅ 正确
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');

    searchInput.addEventListener('input', function(e) {
        const keyword = e.target.value.trim();
        // 处理逻辑
    });
});

// ❌ 错误
window.onload = function() { // 不推荐
    var search = document.getElementById('search'); // var 不推荐
    // ...
}
```

---

## 提交规范

### Commit Message 格式
```
<类型>: <描述>

[可选的正文]

[可选的脚注]
```

### 类型
- `feat`: 新功能
- `fix`: Bug 修复
- `docs`: 文档更新
- `style`: 代码格式调整（不影响功能）
- `refactor`: 代码重构
- `test`: 测试相关
- `chore`: 构建/工具相关

### 示例

```
feat: 添加批量分配学生选择器

- 实现双栏布局设计
- 添加实时搜索功能
- 支持容量检查和警告
- 优化用户体验

Closes #123
```

```
fix: 修复 CSV 导入 GBK 编码错误

修复了 Windows 默认 GBK 编码的 CSV 文件导入时出现的乱码问题。

- 新增 convertEncoding 函数
- 自动检测文件编码
- 支持 GBK/GB2312/UTF-8

Fixes #456
```

```
docs: 更新安装指南

- 添加 Docker 部署说明
- 优化宝塔面板安装步骤
- 补充常见问题解答
```

---

## Pull Request 流程

### 1. 准备工作
```bash
# 同步最新代码
git fetch upstream
git checkout main
git merge upstream/main

# 创建特性分支
git checkout -b feature/your-feature
```

### 2. 开发与测试
- 编写代码
- 添加测试
- 运行测试
- 确保所有测试通过

### 3. 提交代码
```bash
git add .
git commit -m "feat: 添加新功能"
git push origin feature/your-feature
```

### 4. 创建 PR
1. 访问 GitHub
2. 进入你的仓库
3. 点击 "Compare & pull request"
4. 填写 PR 模板

### 5. PR 模板
```markdown
## 描述
<!-- 描述你的改动 -->

## 类型
<!-- 选择一个 -->
- [ ] Bug 修复
- [ ] 新功能
- [ ] 文档更新
- [ ] 代码重构

## 相关 Issue
<!-- 关联的 Issue 编号 -->

## 测试
<!-- 描述你如何测试这些改动 -->

## 检查清单
- [ ] 代码遵循 PSR-12 规范
- [ ] 所有测试通过
- [ ] 文档已更新
- [ ] 没有引入新的安全问题
```

---

## 报告 Bug

### 创建 Issue
访问 https://github.com/yourusername/dormitory-system/issues

### Bug 报告模板
```markdown
## 环境信息
- PHP 版本:
- MySQL 版本:
- 浏览器:
- 操作系统:

## 问题描述
<!-- 清晰描述问题 -->

## 复现步骤
1.
2.
3.

## 期望行为
<!-- 期望发生什么 -->

## 实际行为
<!-- 实际发生了什么 -->

## 截图
<!-- 如果有，添加截图 -->

## 其他信息
<!-- 其他相关信息 -->
```

---

## 功能建议

### 创建 Feature Request Issue
```markdown
## 功能描述
<!-- 描述你想要的功能 -->

## 使用场景
<!-- 这个功能解决什么问题 -->

## 实现建议
<!-- 如果有，提供实现思路 -->

## 优先级
<!-- 低/中/高 -->
```

---

## 代码审查

### 审查清单

#### 安全性
- [ ] SQL 使用预处理语句
- [ ] 所有输入都经过验证
- [ ] 密码使用 hash 存储
- [ ] CSRF 令牌验证
- [ ] XSS 防护

#### 功能性
- [ ] 代码逻辑正确
- [ ] 边界情况处理
- [ ] 错误处理完善
- [ ] 性能考虑

#### 代码质量
- [ ] 遵循代码规范
- [ ] 变量命名清晰
- [ ] 注释适当
- [ ] 无重复代码

#### 文档
- [ ] README 更新
- [ ] 代码注释完整
- [ ] 新功能有说明

---

## 项目结构

```
dormitory-system/
├── .gitignore              # Git 忽略文件
├── LICENSE                 # 许可证
├── README.md               # 项目说明
├── INSTALL.md              # 安装指南
├── USAGE.md                # 使用手册
├── CHANGELOG.md            # 更新日志
├── config/
│   └── config.example.php  # 配置模板
├── app/
│   ├── Database.php        # 数据库类
│   ├── Auth.php            # 认证类
│   ├── helpers.php         # 辅助函数
│   └── Models/             # 数据模型
├── admin/                  # 管理后台
├── teacher/                # 教师角色
├── housekeeper/            # 宿管角色
├── student/                # 学生角色
├── logs/                   # 日志目录
├── docs/                   # 文档
│   ├── CONTRIBUTING.md     # 贡献指南（本文件）
│   ├── PROJECT_OVERVIEW.md # 项目概览
│   ├── DATABASE_SCHEMA.md  # 数据库设计
│   └── QUICK_REFERENCE.md  # 快速参考
└── database.sql            # 数据库结构
```

---

## 交流与讨论

### 提交 Issue
- Bug 报告
- 功能建议
- 问题咨询

### Pull Request
- 代码贡献
- 文档改进

### 邮件联系
your-email@example.com

---

## 行为准则

### 我们期望
- 尊重他人
- 建设性讨论
- 包容友好
- 专业负责

### 不可接受
- 侮辱攻击
- 恶意代码
- 垃圾信息
- 违法内容

---

## 感谢贡献

感谢所有贡献者的付出！🙏

您的每一个贡献都让这个项目变得更好。

---

**版本**: v1.1.0
**最后更新**: 2026-01-05
