# Phase 0 仓库审计报告（REPOSITORY_AUDIT）

> 审计日期：2026-10-11（Asia/Shanghai）  
> 审计范围：只读侦察，**未改业务代码、未重启服务、未伪造测试、未创建七大业务 App**。  
> 业务仓检出：`/www/wwwroot/loong-swoole/uuims/server/`（Git：`RenLoong/loongs-uuims`，HEAD `b0b04ea`）  
> 规格参照：`/www/wwwroot/loong-swoole/projects/PROJECT.md`  
> 框架包：`vendor/loongs/framework` → path 安装 `/www/wwwroot/loong-swoole/composer/framework`（HEAD `3f05472`）

---

## 1. 结论摘要（必须先读）

| 项 | 结论 |
|----|------|
| `apps/` 是否仅 Uuims | **是**。`/www/wwwroot/loong-swoole/uuims/server/apps/` 下仅有目录 `Uuims/`。 |
| 七大业务模块（ERP/CRM/WMS/MES/HRM/SCM/Mall） | **均未实现**：无 `apps/Erp`、`apps/Crm`、`apps/Wms`、`apps/Mes`、`apps/Hrm`、`apps/Scm`、`apps/Mall`。规格是目标态，不是已有能力。 |
| 仓库角色 | **平台方 UUIMS 后端**（租户/套餐/平台运营管理），非企业业务后台。 |
| Phase 建议 | 仍处 **Phase 0**：本报告即 `docs/implementation/REPOSITORY_AUDIT.md`；下一步再做最小 App 样板验证，**禁止批量创建七 App**。 |

---

## 2. 仓库根与技术栈（可核对路径）

### 2.1 根目录要点

| 路径 | 说明 |
|------|------|
| `/www/wwwroot/loong-swoole/uuims/server/loongs` | CLI 入口（无 `.php` 后缀）；`LOONGS_BASE_PATH=__DIR__`，加载 `vendor/autoload.php`，运行 `Loongs\Console\Kernel` |
| `/www/wwwroot/loong-swoole/uuims/server/composer.json` | 包名 `loongs/uuims`；`type=project`；PHP `^8.4`；PSR-4：`App\` → `apps/`，`Server\` → `src/` |
| `/www/wwwroot/loong-swoole/uuims/server/README.md` | 官方说明：端口、安装、目录、OAuth、权限摘要 |
| `/www/wwwroot/loong-swoole/uuims/server/COMMANDS.md` | 命令速查（内容偏框架通用模板；以 `./loongs list` / `help` 为准） |
| `/www/wwwroot/loong-swoole/uuims/server/.cursor/skills/SKILL.md` | Agent 硬约束（端口 21000–21002、apps 约定、勿擅自 restart） |
| `/www/wwwroot/loong-swoole/uuims/server/.env.example` | 默认 `APP_NAME=uuims`；`HTTP_PORT=21000` / `RPC_PORT=21001` / `WS_PORT=21002`；库名默认 `user_platform` |
| `/www/wwwroot/loong-swoole/uuims/server/config/` | `app.php`、`database.php`、`process.php`、`rpc.php`、`uuims.php`、`cors.php`、`console.php`、`uploads.php` 等 |
| `/www/wwwroot/loong-swoole/uuims/server/routes/` | 进程级：`/health`、`POST /rpc`（`Server\Http\Controllers\HealthController`） |
| `/www/wwwroot/loong-swoole/uuims/server/apps/Uuims/` | **唯一业务 App** |

### 2.2 Composer 依赖（`composer.json` require）

- `loongs/framework`、`loongs/orm`、`loongs/oauth`、`loongs/cache`、`loongs/helper`、`loongs/language`、`loongs/render`、`loongs/uploads`（均为 `dev-main`）
- 本机 `loongs/framework` 为 **path** 安装：`../../composer/framework` → `/www/wwwroot/loong-swoole/composer/framework`

### 2.3 端口与进程（`.env.example` / `config/process.php`）

| 进程 | 默认端口 | 说明 |
|------|----------|------|
| HTTP | **21000** | API + `apps/Uuims/public/{web,h5}` |
| RPC | **21001** | RPC 服务端 |
| WebSocket | **21002** | WS |
| Queue / Crontab / Custom | 默认关闭 | `.env.example` 中 `QUEUE_ENABLED=false` 等 |

`config/process.php` 代码默认值仍写 9501/9502（框架模板遗留）；**以 `.env` / `.env.example` 的 21000–21002 为准**（README / SKILL 一致）。

健康检查：`GET http://127.0.0.1:21000/health`（`Server\Http\Controllers\HealthController`）。

---

## 3. App 发现与扩展机制（框架真实类名）

框架命名空间根：`Loongs\`（`vendor/loongs/framework/src/`）。

### 3.1 `Loongs\App\AppDiscovery`

路径：`.../vendor/loongs/framework/src/App/AppDiscovery.php`

约定（源码注释原文要点）：

- 扫描 `{basePath}/apps/*`（一级目录 = App 名）
- `apps/<Name>/routes.php`：注册 App 路由（`setCurrentApp`；module=null）
- `apps/<Name>/Modules/<Module>/routes.php`：注册模块路由（**只扫一层 Modules，不递归嵌套**）
- 可选 `apps/<Name>/rpc.php`：向 `Loongs\Rpc\Server\HandlerRegistry` 注册 RPC handler

方法：`listApps()`、`listModules($app)`、`registerRoutes(...)`、`registerRpcHandlers(...)`。

**现状**：`Uuims` **无** `rpc.php`。进程级 `routes/route.php` 另有 `POST /rpc` 走 `Loongs\Rpc\Server\RpcServer`。

### 3.2 `Loongs\App\AppMiddlewareResolver`

- `apps/<Name>/middleware.php` → App 级中间件列表
- `apps/<Name>/Modules/<Module>/middleware.php` → 模块级

Uuims 现状：

- App：`LocaleMiddleware`（`Loongs\Language\Integration\Framework\LocaleMiddleware`）+ `App\Uuims\Middleware\ApiExceptionMiddleware`
- 模块示例：System / Sample / Dashboard / Message / Render 使用 `App\Uuims\Middleware\UuimsAuthMiddleware`

### 3.3 `Loongs\App\AppConfig` / `AppProcessDiscovery`

- `AppConfig`：加载 `apps/<Name>/config/*.php`（**不**并入全局 Repository；排除 `processes.php`）
- `AppProcessDiscovery`：`apps/<Name>/config/processes.php` → 进程名自动加前缀 `strtolower(App).key`（如 `user.queue`）；handler 必须在 `App\{Name}\` 命名空间下

Uuims 现状：`apps/Uuims/config/` 仅有 `console.php`（无 `processes.php`）。

### 3.4 静态前端：`Loongs\Middleware\ServeAppPublicMiddleware`

- `GET /{appSlug}/{asset...}` → `apps/{App}/public/{asset}`
- 亦可落到模块 `public/`（首段 asset 为 module slug 时）
- Uuims 前端产物：`apps/Uuims/public/web/`（Flutter PC，含 `spa.html`、`version.json`）、`apps/Uuims/public/h5/`（uni-app）
- 入口注入：`App\Uuims\Support\ClientEntry` + `apps/Uuims/routes/client.php`（`/`、`/uuims` UA 分流 → `/uuims/web/` 或 `/uuims/h5/`）

### 3.5 控制台命令发现：`Loongs\Console\Kernel`

- 内置：`start|stop|restart|reload|status`、`rpc:show|switch|set|reset`
- 额外命令来自：
  - `config/console.php` → 含 `Loongs\OAuth\Console\InstallCommand`（`./loongs oauth:install`）
  - `apps/*/config/console.php` → Uuims 注册：
    - `App\Uuims\Console\InstallCommand` → **`./loongs uuims:install`**
    - `App\Uuims\Console\MenusCommand` → **`./loongs uuims:menus`**

### 3.6 菜单 / 权限 / Render 页面

| 能力 | 类 / 路径 | 说明 |
|------|-----------|------|
| 菜单合并 | `App\Uuims\Support\MenuRegistry` | 扫描所有 `apps/*/menu.json`，校验 path/perms/page 唯一性 |
| 菜单文件 | `apps/Uuims/menu.json` | 平台概览、系统管理（管理员/角色/菜单/部门/字段等）、消息等 |
| 权限服务 | `App\Uuims\Services\PermissionService` | 权限码缓存；角色表 `role_permission.code` |
| 鉴权中间件 | `UuimsAuthMiddleware`、`PermissionMiddleware` | 路由上 `P::need('system:uuims:list')` 等 |
| 服务端页面 | `Loongs\Render\PageRegistry::discoverApps` | 发现 `apps/*/Pages` 下 `#[AsPage]`；Uuims 经 `App\Uuims\Render\PageService` 暴露 |
| ORM 模型 | `App\Uuims\Models\Uuims` 等 | 继承 `Loongs\Orm\Model\Model`；表前缀来自 `DB_PREFIX`（默认 `lgs_`） |

---

## 4. Uuims App 现状（已有能力，非七业务）

### 4.1 Modules（一级）

| 模块 | 关键路径 | 职责摘要 |
|------|----------|----------|
| Auth | `Modules/Auth/` | 登录页 `LoginPage`、资料 `ProfileController`、微信 `WechatController` |
| Dashboard | `Modules/Dashboard/` | 概览 `OverviewController` |
| System | `Modules/System/` | 管理员/角色/菜单/部门/字段 API（`/uuims/api/system/...`） |
| Message | `Modules/Message/` | 站内消息 |
| Render | `Modules/Render/` | 服务端页面描述 API |
| Sample | `Modules/Sample/` | **样例租户 API**（内存夹具，不落库；注释写明后续替换） |

### 4.2 数据库安装路径

- 命令：`./loongs uuims:install [--username=uuims] [--reset-password]`
- 类：`App\Uuims\Console\InstallCommand`
- Schema：`apps/Uuims/database/platform/schema.sql`（`CREATE TABLE IF NOT EXISTS`，`{{prefix}}` 替换）
- 平台表（逻辑名，物理名 = `DB_PREFIX` + 逻辑名）：

  `depts`、`users`、`roles`、`user_role`、`role_permission`、`user_wechat`、`role_data_scope`、`resource_fields`、`role_field`、`user_messages`

- 另调用 `Loongs\OAuth\Storage\OrmStorage::installOn` 安装 OAuth 表；可单独 `./loongs oauth:install`
- 种子：部门、可控字段、超级管理员（初始密码写入 `runtime/user_initial_password`，不回显）、OAuth 公开客户端（`uuims-pc` / `uuims-mobile`，见 `App\Uuims\Support\OAuth`）
- 菜单**不进库**：来自 `apps/*/menu.json`；`uuims:install` 会升级遗留 `menus`/`role_menu` 表到 `role_permission` 码

### 4.3 ORM / DB 约定（README + `config/database.php`）

- 默认连接库名示例：`user_platform`（`.env.example`）
- 表前缀默认 `lgs_`
- 租户库：规格/README 要求用 `Orm::using` / `Model::on` 按次连接，**禁止模型缓存连接**
- 引导：`routes/bootstrap.php` 调用 `Loongs\Orm\Orm::configure(...)`

### 4.4 RPC 配置

- 文件：`config/rpc.php`
- 热切换：`./loongs rpc:show|switch|set|reset`（`Loongs\Rpc\HotReload\RpcServiceManager`）
- 当前示例服务键含 `demo`（`register_demo_handlers`）；**无业务 App RPC 契约**

### 4.5 测试

- 本仓 `apps/` / `server` 根下**未见**已入库的 PHPUnit/Pest 业务测试套件（vendor 与 runtime 除外）
- `.gitignore` 提及本地 `bin/smoke_*`（不入库）
- **不得伪造测试通过**

---

## 5. 安装 / 启停 / 常用命令（以仓库为准）

在 `/www/wwwroot/loong-swoole/uuims/server/`：

```bash
composer update          # 或缺 vendor 时
cp .env.example .env     # 已存在则勿覆盖生产密钥
./loongs uuims:install   # 建库表、种子、OAuth 客户端
./loongs uuims:menus     # 校验 apps/*/menu.json 与 Pages 一致性
./loongs start           # 前台；守护加 -d
./loongs status|stop|restart|reload
./loongs rpc:show
./loongs list
```

宝塔 PHP 若禁用函数，可用：`/www/server/php/84/bin/php -d disable_functions= loongs ...`（见 `COMMANDS.md`）。

**本审计未执行** `install` / `start` / `restart`。

---

## 6. 新增最小 App 样板前的扩展点 Top 5

按「先验证再批量建 App」优先级：

1. **App 目录约定（`Loongs\App\AppDiscovery`）**  
   新建 `apps/<Name>/`（建议 PascalCase，如 `Demo`），提供 `routes.php`；可选 `Modules/<Module>/routes.php`、`middleware.php`、`rpc.php`。命名空间 `App\<Name>\`（composer PSR-4 `App\` → `apps/`）。**无需改框架**即可被发现。

2. **菜单与权限码（`apps/<Name>/menu.json` + `MenuRegistry`）**  
   任意 App 的 `menu.json` 会在 worker 启动时合并；perms/path/page 跨 App 唯一。角色授权仍写 `role_permission.code`。新 App 先交一份合法 `menu.json`，用 `./loongs uuims:menus` 校验。

3. **控制台安装命令（`apps/<Name>/config/console.php`）**  
   注册 `#[AsCommand]` 的 `Loongs\Console\Command` 子类（参照 `App\Uuims\Console\InstallCommand`）：用 `Loongs\Orm\Support\DatabaseEnsurer` + 自有 `database/**/schema.sql` + 可选 `OrmStorage`。**不要**在请求路径建表。

4. **RPC 契约（`apps/<Name>/rpc.php` + `config/rpc.php` services）**  
   跨 App **禁止** `use` 对方内部类 / 直写对方表。样板应注册至少 1 个 handler，并在 `config/rpc.php` 的 `services` 中声明 transport（local/loopback/remote），用 `rpc:show` 验证。Uuims 当前无 `rpc.php`，此路径需样板首次跑通。

5. **静态与页面（`ServeAppPublicMiddleware` + `PageRegistry` + 可选 `ClientEntry` 模式）**  
   - 静态：`apps/<Name>/public/` → URL `/{slug}/...`  
   - 服务端页：`apps/<Name>/Pages/` + `menu.json` 的 `page`/`page_type`  
   - 若需独立 SPA，参照 `ClientEntry` + `routes/*.php`（由 App `routes.php` 内 `glob` 加载）

**样板建议范围（Phase 1，非本审计任务）**：单一只读健康/示例 API + 一条 menu + 一次 install schema + 一个 RPC echo；**不要**一次生成 ERP…Mall。

---

## 7. 与规格的对照（规划 ≠ 已有）

| PROJECT.md 表述 | 仓库实况 |
|-----------------|----------|
| 基于 `loongs-uuims` 扩展 | 检出即为该仓；`apps/` 仅 Uuims |
| 端口 21000/21001/21002 | `.env.example` / README / SKILL 一致 |
| 七业务 App | **不存在** |
| Phase 0 产出本审计 | 本文件 |
| 跨 App 仅 RPC | 框架支持 `rpc.php` + `RpcClient`；业务侧契约未建 |
| 复用权限/菜单/OAuth/Render | 能力在 Uuims + loongs/* 包中，业务 App 应复用模式而非复制平台表语义 |

---

## 8. 未知项 / 待核实（标注，未静默拍板）

1. **多 App 同进程 vs 分进程部署**：`AppProcessDiscovery` 支持 App 自有 http/rpc 进程，但生产拓扑（同 `APP_NAME=uuims` 进程内挂多 App，或独立服务）未在本机运行态验证。  
2. **租户库开通与 `loongs/saas`**：README 写租户能力长期在 `loongs/saas`；当前 `composer.json` **未 require** `loongs/saas`，企业侧「公司/租户库」落地路径未实装。  
3. **Uuims 无 `rpc.php`**：跨 App 调用 Uuims 能力（鉴权/用户映射）的正式 handler 清单未定义。  
4. **`COMMANDS.md` 与实仓偏差**：文中示例端口/路径偏旧模板；业务命令以 `apps/Uuims/config/console.php` 与 `./loongs list` 为准。  
5. **自动化测试基线**：无仓内标准测试套件路径；样板交付前需选定真实可跑的测试入口（不可伪称通过）。  
6. **前端姊妹仓构建流水线**：产物已在 `public/web`、`public/h5`，但本审计未核对 `loongs-uuims-client` / `loongs-uuims-mobile` 的构建命令与版本对齐策略。  
7. **P0 业务决策**（商品权威、Mall→ERP 建单时机、仓库权威、库存策略、前端技术选型等）仍待用户拍板；与仓库审计正交，不阻塞本文件，但阻塞 Phase 2 建模。

---

## 9. 本周可执行下一步（建议，不开工代码）

1. 评审并归档本 `REPOSITORY_AUDIT.md`。  
2. 用户拍板后：仅做一个 **最小 Demo App 样板**（验证发现/路由/menu/install/RPC），配套真实命令输出记录。  
3. 并行维护 `docs/implementation/DECISIONS.md`（业务 P0），与审计分离。

---

## 10. 审计方法说明

- 访问方式：用户机经 `wsl -e bash -lc` 只读查看上述路径与框架源码。  
- 未执行：`git clone`、业务代码修改、服务启停、测试伪造、创建业务 App。  
