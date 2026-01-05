# 安装指南

本指南将帮助你快速部署智慧宿舍管理系统。

## 📋 前置要求

### 服务器环境
- **PHP**: >= 7.4 (推荐 8.0+)
- **MySQL**: >= 5.7 (推荐 8.0+)
- **Web服务器**: Apache 或 Nginx
- **PHP扩展**:
  - PDO_MySQL
  - GD (用于图表)
  - MBString
  - OpenSSL

### 推荐环境
- **宝塔面板**: 一键安装 PHP + MySQL + Nginx
- **XAMPP/WAMP**: 本地开发环境
- **Docker**: 容器化部署

## 🚀 快速安装

### 方式一：使用安装向导（推荐）

#### 1. 上传文件
将所有项目文件上传到网站根目录：
```
/var/www/html/dormitory/
├── config/
├── app/
├── admin/
├── teacher/
├── housekeeper/
├── student/
├── install.php
├── login.php
├── index.php
├── database.sql
└── ...
```

#### 2. 创建数据库
在 MySQL 中创建数据库：
```sql
CREATE DATABASE dormitory_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 3. 访问安装页面
打开浏览器访问：
```
http://your-domain.com/install.php
```

#### 4. 填写配置信息
在安装页面填写：
- 数据库主机：`localhost`
- 数据库名：`dormitory_system`
- 数据库用户名：`root`
- 数据库密码：`your_password`
- 管理员密码：`admin123`（自定义）

#### 5. 完成安装
点击"开始安装"，系统会自动：
- 创建数据表
- 插入初始数据
- 生成配置文件
- 创建管理员账号

#### 6. 删除安装文件
安装完成后，**务必删除**：
```bash
rm install.php
```

### 方式二：手动安装

#### 1. 准备环境
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install php php-mysql php-gd php-mbstring mysql-server nginx

# CentOS/RHEL
sudo yum install php php-mysql php-gd php-mbstring mysql-server nginx

# Windows
# 使用 XAMPP 或 WAMP
```

#### 2. 下载项目
```bash
cd /var/www/html
git clone https://github.com/yourusername/dormitory-system.git
cd dormitory-system
```

#### 3. 配置数据库
```bash
# 登录 MySQL
mysql -u root -p

# 创建数据库
CREATE DATABASE dormitory_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

#### 4. 导入数据库结构
```bash
mysql -u root -p dormitory_system < database.sql
```

#### 5. 配置应用
```bash
# 复制配置模板
cp config/config.example.php config/config.php

# 编辑配置文件
vim config/config.php
```

修改以下配置：
```php
<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'dormitory_system');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');

// 系统配置
define('SYSTEM_NAME', '智慧宿舍管理系统');
define('SYSTEM_VERSION', '1.1.0');
define('SYSTEM_DEBUG', false);

// 上传配置
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024);
```

#### 6. 设置权限
```bash
# 创建日志目录
mkdir -p logs

# 设置权限
chmod 755 logs/
chmod 644 logs/*.log 2>/dev/null || true

# 确保配置文件可读
chmod 644 config/config.php

# 确保上传目录可写（如果需要）
chmod 755 uploads/ 2>/dev/null || true
```

#### 7. 配置 Web 服务器

**Nginx 配置** (`/etc/nginx/sites-available/dormitory`):
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/dormitory-system;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

**Apache 配置** (`.htaccess`):
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

#### 8. 重启服务
```bash
# Nginx
sudo systemctl restart nginx

# Apache
sudo systemctl restart apache2

# PHP-FPM
sudo systemctl restart php8.0-fpm
```

### 方式三：Docker 部署

#### 1. 创建 Dockerfile
```dockerfile
FROM php:8.0-apache

# 安装扩展
RUN docker-php-ext-install pdo_mysql gd mbstring

# 复制项目文件
COPY . /var/www/html/

# 设置权限
RUN chown -R www-data:www-data /var/www/html

# 启用 mod_rewrite
RUN a2enmod rewrite

EXPOSE 80
```

#### 2. 创建 docker-compose.yml
```yaml
version: '3.8'

services:
  web:
    build: .
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: dormitory_system
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

#### 3. 启动服务
```bash
docker-compose up -d
```

## 🔧 宝塔面板安装

### 1. 创建网站
1. 登录宝塔面板
2. 点击"网站" → "添加网站"
3. 填写域名，选择 PHP 8.0+
4. 创建 MySQL 数据库

### 2. 上传文件
1. 进入网站根目录
2. 上传所有项目文件
3. 解压（如果有压缩包）

### 3. 配置数据库
1. 在宝塔中进入数据库管理
2. 点击"导入"，选择 `database.sql`
3. 或访问安装向导自动创建

### 4. 设置权限
1. 在文件管理器中
2. 右键 `logs/` 目录 → 权限 → 设置为 755

### 5. 完成安装
访问 `http://你的域名/install.php` 按向导完成

## 📱 验证安装

### 1. 访问首页
```
http://your-domain.com/
```
应该自动跳转到登录页面

### 2. 登录测试
- 用户名：`admin`
- 密码：`admin123`

### 3. 检查功能
1. 访问管理后台
2. 添加一个测试宿舍楼
3. 批量添加房间
4. 添加测试学生
5. 尝试 CSV 导入
6. 查看统计图表

### 4. 检查日志
```
http://your-domain.com/admin/logs.php
```
确保日志正常记录

## ⚠️ 常见问题

### 问题1：安装页面无法访问
**原因**：PHP 未正确配置
**解决**：
```bash
# 检查 PHP 是否安装
php -v

# 检查 Nginx/Apache 配置
# 确保 .php 文件能被正确解析
```

### 问题2：数据库连接失败
**原因**：配置信息错误
**解决**：
```bash
# 检查数据库是否创建
mysql -u root -p -e "SHOW DATABASES;"

# 检查用户权限
mysql -u root -p -e "GRANT ALL ON dormitory_system.* TO 'user'@'localhost';"
```

### 问题3：权限错误
**原因**：目录权限不足
**解决**：
```bash
# Linux/Mac
chmod -R 755 logs/
chown -R www-data:www-data /var/www/html/dormitory/

# Windows
# 右键属性 → 安全 → 设置完全控制
```

### 问题4：中文乱码
**原因**：字符集不匹配
**解决**：
```sql
-- 检查数据库字符集
SHOW VARIABLES LIKE 'character_set%';

-- 修改为 utf8mb4
ALTER DATABASE dormitory_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 问题5：CSV 导入失败
**原因**：编码问题或格式错误
**解决**：
1. 确保 CSV 为 UTF-8 或 GBK 编码
2. 检查列数是否为 10 列
3. 查看 `logs/import.log` 获取详细错误

## 🔍 安装后检查清单

- [ ] 配置文件已创建 (`config/config.php`)
- [ ] 数据库已导入并包含数据表
- [ ] 日志目录可写 (`logs/`)
- [ ] 能访问登录页面
- [ ] 能使用 admin/admin123 登录
- [ ] 能添加宿舍楼
- [ ] 能批量添加房间
- [ ] 能添加学生
- [ ] 能导入 CSV 文件
- [ ] 能查看统计图表
- [ ] 能查看系统日志

## 📝 安全建议

### 1. 修改默认密码
登录后立即修改管理员密码：
- 进入"用户管理"
- 编辑 admin 用户
- 设置强密码

### 2. 删除安装文件
```bash
rm install.php
```

### 3. 限制访问权限
- 不要将系统暴露在公网
- 使用 VPN 或内网访问
- 配置防火墙规则

### 4. 定期备份
- 数据库备份
- 配置文件备份
- 上传文件备份

### 5. 更新系统
定期检查 GitHub 获取最新版本

## 🆘 技术支持

如果安装遇到问题：

1. **查看错误日志**
   - PHP 错误日志
   - Nginx/Apache 错误日志
   - 系统日志：`logs/error.log`

2. **检查环境**
   - 访问 `check_environment.php`
   - 查看 PHP 版本和扩展

3. **联系开发者**
   - 提交 Issue
   - 邮件咨询

---

**版本**: v1.1.0
**最后更新**: 2026-01-05
