# LOONGS Admin（平台方后台 · 后端）

基于 [loongs/framework](https://github.com/RenLoong/loongs-framework) 等包的 **平台方** 管理系统后端：管理 SaaS 租户、套餐与平台运营。租户自己的业务后台不在本仓库。

| 项 | 说明 |
|----|------|
| 仓库 | [RenLoong/loongs-admin](https://github.com/RenLoong/loongs-admin) |
| 包名 | `loongs/admin` |
| PHP | `^8.4` + Swoole 6 |
| 入口 | 项目根 `./loongs`（无 `.php` 后缀） |
| 默认端口 | HTTP **21000** · RPC **21001** · WebSocket **21002** |
| `APP_NAME` | `admin` |

姊妹前端（独立仓，打包产物放入本仓 `apps/Admin/public/`）：

- PC（Flutter）：[loongs-admin-client](https://github.com/RenLoong/loongs-admin-client) → `public/web`
- 移动（uni-app）：[loongs-admin-mobile](https://github.com/RenLoong/loongs-admin-mobile) → `public/h5`

> **项目根即本仓库根**（含 `loongs`、`apps/`、`config/`）。不要假设外层还有一层叫 `server/` 的目录——`composer create-project` / 克隆后当前目录就是根。

## 要求

- PHP 8.4+，扩展：`swoole`（^6）、`pdo_mysql`、`openssl`、`redis`（若用 Redis 缓存）等
- MySQL 8（平台库默认名 `admin_platform`）
- Composer 2

## 快速开始

```bash
git clone git@github.com:RenLoong/loongs-admin.git
cd loongs-admin

# 依赖从 Packagist 安装（不要提交 composer.lock；裸机用 update/install）
composer update

# 依赖均从 Packagist 解析（含 loongs/uploads、loongs/language 等）。中国大陆若 repo.packagist.org 不可达，可临时：
#   composer config -g repos.packagist composer https://mirrors.cloud.tencent.com/composer/

cp .env.example .env
# 编辑 .env：数据库、端口、ADMIN_WEB_ORIGINS / OAUTH_* 等

./loongs admin:install          # 建表、种子、OAuth 客户端；可生成 OAUTH_TOKEN_PEPPER
./loongs start                  # 前台；守护加 -d
./loongs status
```

健康检查：`http://127.0.0.1:21000/health`  
浏览器打开同一端口：按 UA 跳到 `/admin/web/` 或 `/admin/h5/`（需先放入对应前端构建）。

常用命令见 [`COMMANDS.md`](COMMANDS.md)，或以 `./loongs list` / `./loongs help <cmd>` 为准。

```bash
./loongs stop|restart|reload|status
./loongs admin:install [--username=admin] [--reset-password]
./loongs admin:menus
./loongs rpc:show|rpc:switch|rpc:set|rpc:reset   # 见框架 RPC 文档
```

### 本机 monorepo 开发

若在含 `composer/<pkg>` 的 monorepo 旁用 path 覆盖框架包，可用本地的 `composer.dev.json`（**已 gitignore，勿入库**）。独立克隆只需 Packagist，不要依赖机器上的绝对 path 仓库配置。

## 目录结构

```text
.
├── loongs                 # CLI 入口
├── apps/
│   └── Admin/             # 唯一业务 app
│       ├── Modules/       # Auth / Dashboard / System / Message / Render / Sample …
│       ├── Support/       # ClientEntry、OAuth、Api …
│       ├── public/        # web/、h5/ 静态产物
│       ├── routes/        # 含 client.php 等（全部自动加载）
│       ├── menu.json
│       └── database/
├── config/                # admin.php、cors、database、rpc、process …
├── routes/                # 进程级（如 /health）
├── runtime/               # 运行时（gitignore）
├── .env.example
└── COMMANDS.md
```

## 配置要点

复制 `.env.example` 为 `.env`，至少关注：

| 变量 | 含义 |
|------|------|
| `APP_NAME` | 须为字母数字下划线；默认 `admin` |
| `HTTP_PORT` / `RPC_PORT` / `WS_PORT` | 默认 21000 / 21001 / 21002 |
| `DB_*` | 平台库连接 |
| `OAUTH_ISSUER` | 默认 `http://127.0.0.1:21000` |
| `ADMIN_WEB_ORIGINS` | 逗号分隔浏览器源（含局域网 IP）。每源自动注册 `{origin}/callback.html` 与 `{origin}/admin/web/callback.html` |
| `OAUTH_ALLOWED_ORIGINS` | 允许作为 issuer 的访问源（可与上者对齐） |
| `OAUTH_TOKEN_PEPPER` | `admin:install` 可生成；**变更会使已有令牌全部失效** |
| `ADMIN_PC_REDIRECT_URIS` / `ADMIN_MOBILE_REDIRECT_URIS` | 额外 redirect，可选 |

改 origins 后需重启 HTTP，否则客户端会出现 `redirect_uri does not match`。

## 客户端入口与静态

- `GET /`、`GET /admin`：按 `Sec-CH-UA-Mobile` / UA 302 → `/admin/web/` 或 `/admin/h5/`
- `GET /admin/web`、`GET /admin/h5`：返回对应 `index.html`
- 静态文件由框架 `ServeAppPublicMiddleware` 提供：`apps/Admin/public/{asset}`

PC / H5 构建步骤见姊妹仓 README；产物分别放到 `apps/Admin/public/web` 与 `public/h5`。

## OAuth

- 包：`loongs/oauth`（OAuth 2.1）
- 授权码 + **强制 PKCE S256**；刷新令牌轮换；令牌为加密 **JWE**（非明文 JWT）
- 持久化：MySQL
- 公开客户端示例：`admin-pc`（含桌面 loopback `http://127.0.0.1/callback`，任意端口）

## 权限与租户（摘要）

- 平台端 RBAC：菜单 + 按钮 + 数据（行级）+ 字段；前后端都要校验
- 平台库 `admin_platform`；租户库按开通创建。访问租户库用 `Orm::using` / `Model::on`，**禁止**在模型上缓存连接
- 多租户能力长期落在 `loongs/saas`；`loongs/orm` 保持纯 ORM

## 开发约定

- `apps/` 只放真实 app 目录；跨 app 只能 RPC，禁止 `use` 另一 app
- 路由文件任意 `.php` 名，由 `AppDiscovery` 加载；不要在进程级 `routes/` 里手写 require app 路由
- 详细 Agent 约定：`.cursor/skills/SKILL.md`

## 相关链接

- 框架：https://github.com/RenLoong/loongs-framework  
- 示例应用仓：https://github.com/RenLoong/loongs  
- OAuth：https://github.com/RenLoong/loongs-oauth  
- ORM：https://github.com/RenLoong/loongs-orm  

## License

MIT
