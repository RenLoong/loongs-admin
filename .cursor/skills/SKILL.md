---
name: LOONGS Admin 后端
description: >-
  在 loongs-admin（平台方后台后端）项目根下开发 apps/Admin、OAuth/CORS、UA 分流入口、
  权限与租户相关约定、./loongs 启停。项目根即本仓库根（含 loongs、apps/、config/），
  不是嵌套的 server/ 子路径。不要用本技能改 Flutter/uni 前端仓库。
---

# LOONGS Admin 后端

作用范围：**当前仓库根**（`RenLoong/loongs-admin` / 本机 admin 后端目录：含 `./loongs`、`apps/Admin/`、`config/`、`.env`）。

产品：**平台方**管理 SaaS 租户 / 套餐 / 运营；租户自己的后台不在本仓库。规划见上级 monorepo 的 `README.md`（若存在）或本仓文档。

## 何时用

- 改 `apps/Admin`（模块、路由、中间件、Render、权限）
- 改 `config/admin.php` / CORS / OAuth 相关 `.env`
- ClientEntry、`/admin/web`、`/admin/h5` 静态入口
- `./loongs` 启停、admin:install

## 硬约束

1. 不要动用户 `production/` 目录；不要动其 **9501/9502** 冒烟栈。本项目默认 **21000–21002**。
2. **`apps/` 只放真实 app**（当前 `Admin/`）；路由任意命名 `.php`，框架按目录加载，禁止硬编码单个 `route.php`。
3. 跨 app 只能 RPC；Admin 内模块可直接调用本 app 类。
4. 租户库：`Orm::using` / `Model::on` 按次连接，禁止模型缓存连接；租户能力长期在 `loongs/saas`。
5. OAuth：`loongs/oauth`，JWE；MySQL 持久化；刷新轮换；授权码强制 PKCE S256。
6. 用户自管重启时，Agent **勿擅自** `./loongs restart`，除非明确要求。

## 端口

| 进程 | 端口 |
|------|------|
| HTTP | **21000**（API + `public/web`、`public/h5`） |
| RPC | 21001 |
| WebSocket | 21002 |

`APP_NAME=admin`。CLI：在项目根 `./loongs <command>`。

## apps/Admin

### 模块（现状）

`Modules/`：`Auth`、`Dashboard`、`System`、`Message`、`Render`、`Sample` …  
新增模块：目录 + `routes.php`（及可选 `middleware.php`）。

### 客户端入口

- `Support/ClientEntry.php` + `routes/client.php`
- `GET /`、`GET /admin`：按 UA / `Sec-CH-UA-Mobile` → `/admin/web/` 或 `/admin/h5/`
- `GET /admin/web`、`GET /admin/h5`：对应 `public/web|h5/index.html`
- 静态：`ServeAppPublicMiddleware` → `apps/Admin/public/{asset}`

缺构建时提示跑 **独立前端仓** 的打包脚本（client / mobile），产物拷到 `apps/Admin/public/web` 或 `h5`。

### OAuth / CORS

- `config/admin.php` 读 `.env`
- **`ADMIN_WEB_ORIGINS`**：逗号分隔源；每源注册 `{origin}/callback.html` 与 `{origin}/admin/web/callback.html`
- **`OAUTH_ALLOWED_ORIGINS`**：issuer 允许源
- 客户端 id：`admin-pc`；桌面 loopback：`http://127.0.0.1/callback`（任意端口）
- 改 origins 后需重启 HTTP，否则 `redirect_uri does not match`

### 安装

`./loongs admin:install` 等以本仓命令为准；改 `OAUTH_TOKEN_PEPPER` 会使已有令牌失效。

## 常见故障（后端侧）

| 现象 | 处理 |
|------|------|
| `redirect_uri does not match` | 写入 `ADMIN_WEB_ORIGINS` / allowed，重启 21000 |
| Swoole 7105 | 响应勿重复 Content-Length（框架侧） |
| MySQL 起不来（宝塔） | 检查 `/etc/init.d/mysqld`、basedir/datadir |

## Agent 习惯

- 不提交 `.env` / 密钥；`.gitignore` 已忽略 `vendor/`、`composer.lock`、`composer.dev.json`、`runtime/*`、`bin/smoke_*`。
- 框架通用约定见 loongs 应用「框架应用与 RPC」技能；本文件只写 Admin 差异。
