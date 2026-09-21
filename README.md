# hmic 下载站

hmic 是一个轻量、开箱即用的文件下载站。基于 PHP + ThinkPHP 8，无需数据库，数据以 JSON 文件存储，自带网页式安装向导，下载解压即可部署。

## 功能

- 前台目录浏览、搜索、下载
- 令牌式下载 / 图片令牌代理
- 目录密码保护（解锁令牌：2 小时有效、绑定访问 IP，未解锁不泄露条目）
- IPv4 / IPv6 双栈站点切换
- 访问统计与访客地图（ECharts + 本地地图数据）
- 后台管理：仪表盘、文件管理、首页与外观、可视化编辑、跑马灯公告、系统日志、账号与后台路径设置
- 安装向导：6 步完成初始化，自动生成配置文件

## 环境要求

- PHP >= 8.1（推荐 8.5）
- 必需扩展：`json`、`pcre`、`mbstring`、`openssl`、`session`
- 可写目录：`data/`、`app/`、`runtime/`（安装向导会自动创建并检测）
- Web 服务器：nginx / Apache 均可，无需数据库

## 目录结构

```
index.php            根入口（安装分发 + 转发 public/ 单入口）
public/              Web 单入口目录
  index.php          ThinkPHP 单入口 + 未安装守卫
  page.html          前台首页模板
  assets/            前端静态资源
  data/ -> ../data   相对软链（图片 / 其他数据）
install/             网页安装向导
application/         ThinkPHP 应用目录
common.php           共享业务函数
route/app.php        路由定义
config/ / app/ / data/ / runtime/ / vendor/
```

## 快速开始

1. 上传并解压网站文件。
2. 运行目录设置为**站点根目录** 推荐；设为 `public/`。
3. 访问域名，进入安装向导，按步骤完成安装。
4. 安装完成后，到后台「系统设置」**修改后台路径**（默认 `/admin`）与管理员密码。

## 伪静态配置

本程序设计为「不配置伪静态也能运行」。需要规范路由或安全隔离时，按你的运行目录选择一份配置：

### 一、站点根目录为运行目录

nginx `server` 块内：

```nginx
# ThinkPHP-compatible routing
# Route /d/{token} to download.php?token={token} - hides real file path
location ~ ^/d/([a-f0-9]+)$ {
    rewrite ^/d/([a-f0-9]+)$ /download.php?token=$1 last;
}

location = /mas.ps1 {
    rewrite ^ /mas_ps1.php last;
}

location / {
    try_files $uri $uri/ /index.php?$args;
}
# Sticker images public service (data/jpg/sticker) -> stk.php
location ^~ /stk/ {
    rewrite ^/stk/(.+)$ /stk.php?f=$1 last;
}
```

Apache（站点根 `.htaccess`）：

```apache
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```

### 二、public/ 为运行目录

nginx `server` 块内：

```nginx
# ThinkPHP-compatible routing
# Route /d/{token} to download.php?token={token} - hides real file path
location ~ ^/d/([a-f0-9]+)$ {
    rewrite ^/d/([a-f0-9]+)$ /download.php?token=$1 last;
}

location = /mas.ps1 {
    rewrite ^ /mas_ps1.php last;
}

location / {
    try_files $uri $uri/ /index.php?$args;
}
# Sticker images public service (data/jpg/sticker) -> stk.php
location ^~ /stk/ {
    rewrite ^/stk/(.+)$ /stk.php?f=$1 last;
}
```

Apache（`public/.htaccess`）：

```apache
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```

## 安全配置建议

- 安装完成后，修改默认后台路径（动态前缀，改后旧地址立即失效）并设置强密码。
- 敏感目录与文件建议在 nginx 屏蔽（示例）：

```nginx
location ~* ^/(data|runtime)/ { deny all; }
location ~* \.(json|lock|bak|bak-.*|log)$ { deny all; }
```

- 只给运行账户（如 `www`）写入 `data/`、`app/`、`runtime/` 的权限（755/775 即可）。
- 定期备份 `data/` 目录（全部站点配置与统计数据都在其中）。
- 向导仅在「未安装」（无 `data/Configuration/install.lock`）时可写；安装后一般无需改动 `install/`。

## 数据与配置

| 文件 | 作用 |
| --- | --- |
| `data/Configuration/install.lock` | 安装完成标记（存在且非空 = 已安装） |
| `data/Configuration/*.json` | 站点配置（名称、开关、账号哈希、统计等），后台修改即时生效 |
| `data/jpg/` | 前台图片素材 |
| `data/mas/` | 下载站配套脚本资源 |
| `data/logs/` | 访问 / 操作日志 |
| `runtime/` | 框架运行时缓存 |

遇到问题请联系（若有侵权）
邮箱:godsupremus@gmail.com