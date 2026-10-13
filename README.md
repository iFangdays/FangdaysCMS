# FangdaysCMS

FangdaysCMS 是独立开发的 PHP 内容管理系统，目标是在功能上逐步覆盖 PbootCMS 的主要 CMS 能力。后端与授权码独立实现；默认前台主题适配了 PbootCMS 自带样式，来源与许可见 [THIRD_PARTY.md](THIRD_PARTY.md)。功能差距见 [ROADMAP.md](ROADMAP.md)。

## 当前功能

- 快速安装：一条命令配置 SQLite、管理员、初始栏目、文章、单页与离线授权；也可用安装向导选择 MySQL。
- 后台：登录、栏目新增/编辑/删除、文章草稿与发布、授权码激活。
- 前台：首页、栏目页、文章页、单页、标签页、搜索页；使用响应式企业站主题，只展示已发布内容。正文按纯文本转义，避免编辑内容直接执行 HTML。
- 授权：签发端用 Ed25519 私钥生成 `FD1` 授权码；网站只保存公钥并在本地验签，检查产品、密钥 ID、域名和有效期。无有效授权时前台停止提供内容，后台仍可登录并激活。
- 授权中心：在签发机本地打开独立管理页，按客户和域名签发、复制、检索授权码，导入旧授权并维护备注；记录存放在签发机专用 SQLite 中，不依赖客户站点是否安装。
- 内容组织：栏目、标签、文章草稿与发布、公开或会员可见范围、搜索和每页 20 条的分页。
- 会员：邮箱注册、登录、资料与密码修改；管理员可停用或恢复会员。停用后既有会话也无法继续阅读会员内容。
- 后台权限：固定的站点所有者角色、可编辑的角色权限、后台账号创建/编辑/删除。权限在路由执行前检查。
- 站点设置：管理员可修改站点名称、页面简介和页脚文字；单页可单独排序并进入前台导航。
- 媒体图片：后台上传 JPG、PNG、WebP、GIF 图片并用于文章封面；上传文件会校验 MIME、图片尺寸与大小，使用随机文件名。被文章引用的图片不能直接删除。
- 升级：已有 SQLite/MySQL 安装会增加新表并保留原文章和管理员；管理员账号升级后默认归入站点所有者角色。

目前文章正文按纯文本展示，图片用文章封面呈现；正文内嵌富文本、评论、自定义模型等后续模块尚未实现。公开注册暂不提供邮箱验证、找回密码和跨会话限流；正式开放注册前应按站点需求补齐这些能力。

## 环境

PHP 8.2+，启用 `sodium`、`json`、`mbstring`、`fileinfo`、`PDO`，以及 `pdo_sqlite` 或 `pdo_mysql`。Web 服务器的**文档根目录必须指向 `public/`**；`config/` 和 `var/` 不可作为公开目录。`public/uploads/` 必须对 PHP 进程可写，并在 Web 服务器中禁止该目录执行脚本。MySQL 使用已存在的空数据库，安装器负责建表。

本项目当前位于原 PbootCMS 目录内。如果原站仍把该父目录作为 Web 根目录，务必在其 Web 服务器中拒绝外部访问整个 `/FangdaysCMS/` 路径；FangdaysCMS 使用单独站点或虚拟主机，将根目录指向 `FangdaysCMS/public/`。项目内敏感目录附带 Apache `.htaccess` 拒绝规则，但 Nginx 不读取这些规则，需要在 Nginx 配置中单独拦截。不要依靠 PHP 路由保护可直接下载的数据库文件。

无需下载 Composer 包；`autoload.php` 可直接加载项目类。`composer.json` 留作后续依赖管理和 IDE 识别。

## 本地启动

在项目目录执行以下命令。macOS MAMP 示例 PHP 路径为 `/Applications/MAMP/bin/php/php8.2.0/bin/php`（以本机实际安装目录为准），也可换成其他 PHP 8.2+ 可执行文件。快速安装会配置 SQLite、管理员、示例栏目、文章和单页，并生成且激活当前域名的离线授权码。

```sh
php bin/quickstart.php
php -S 127.0.0.1:8000 -t public public/router.php
```

浏览器打开 `http://127.0.0.1:8000/`。后台地址为 `/login`；安装命令只显示一次随机管理员密码。签发私钥保存在用户主目录的 `.fangdayscms/issuer.key`，位于项目与 Web 根目录之外；请单独备份。部署到其他域名时，可在安装前执行 `php bin/quickstart.php example.com "站点名称"`，并把 Web 服务器文档根目录指向 `public/`。生产环境应在可信签发机上保管私钥，不应长期存放在公开服务器。

已经安装的站点不会被快速安装覆盖。需要使用 MySQL，或自行提供签发密钥与授权码时，使用下面的手动安装流程。

## 手动安装与签发

```sh
php bin/license.php keygen "$HOME/fangdays-private.key"
php -S 127.0.0.1:8000 -t public public/router.php
```

第一条命令输出 `public_key=...`。**私钥只留在签发机器，且必须位于 FangdaysCMS 目录之外。** 浏览器打开 `http://127.0.0.1:8000/install`，填写站点域名 `127.0.0.1`、密钥 ID `main` 和刚生成的公钥。安装后登录后台。

签发与激活示例：

```sh
php bin/license.php issue "$HOME/fangdays-private.key" main 127.0.0.1 never publish > "$HOME/fangdays-license.code"
php bin/license.php activate config/app.php < "$HOME/fangdays-license.code"
php bin/license.php status config/app.php
```

也可以把授权码粘贴到后台“授权”页面。`never` 表示无到期日；可改为 `2027-12-31`，有效至该日 23:59:59 UTC。一个授权码可包含多个域名，以逗号分隔；`www` 域名需单独列出。国际化域名请使用 Punycode。`features` 是为后续功能分级保留的列表；首期前台以授权整体有效性作为访问条件。

## 授权中心：统一签发和查询客户域名

授权中心独立于任何客户站点运行，**只部署在你控制的签发机上**。它复用现有 Ed25519 私钥和 `FD1` 授权码格式。下例在已有私钥的机器上运行一次初始化；`first-demo` 应与已安装站点使用的密钥 ID 一致。初始化会尝试导入当前项目 `var/license.code`，因此现有 `localhost` 授权也会列入记录。

```sh
cd /path/to/FangdaysCMS
php bin/issuer.php setup "$HOME/fangdays-private.key" first-demo
php bin/issuer.php serve
```

初始化命令只显示一次随机管理密码，请保存到密码管理器。浏览器打开 `http://127.0.0.1:8890/`，登录后输入客户、域名、有效期，点击“生成并登记授权码”。然后把授权码交给客户，让客户在其站点后台 `/admin/license` 粘贴激活。**签发不要求客户站点已安装。**新客户站点安装时，在授权中心复制密钥 ID 与公钥填入安装页；不要把私钥或签发数据库发给客户。授权中心默认只监听本机回环地址，并要求管理密码；如需异地使用，可通过 SSH 隧道连接签发机，不要将本地管理端口直接暴露在公网。

记录和密码哈希保存在签发机的 `$HOME/.fangdayscms/issuer.sqlite`（可用 `FANGDAYS_ISSUER_HOME` 指定其他**项目外**的私有目录）。私钥仍位于初始化时提供的原路径，不写入数据库。请分别备份私钥与签发数据库。已有但未登记的授权码可在页面“导入以前签发的授权码”中粘贴，或运行 `php bin/issuer.php import /path/to/license.code '客户名称'`。命令行也可运行 `php bin/issuer.php issue example.com '客户名称' never`、`php bin/issuer.php list` 和 `php bin/issuer.php public-key`。忘记管理密码时，在签发机运行 `php bin/issuer.php reset-password`，保存一次性显示的新密码。

页面展示的是**已签发记录**及到期情况。离线客户站点不会向签发机回报，因此不能从这里实时判断是否已在客户服务器上激活；也不能对已交付的永久离线授权码远程即时撤销。若后续需要在线激活状态或即时撤销，须增加客户站点主动上报及在线验证机制，并明确告知客户。

## 结构

| 路径 | 用途 |
| --- | --- |
| `public/` | 唯一 Web 文档根目录与前台入口 |
| `src/Cms/` | 安装、数据库、后台会话、路由和内容仓储 |
| `src/Licensing/` | 独立授权码格式、签名验证与本地激活 |
| `views/` | 可替换的 PHP 页面模板 |
| `public/assets/theme/` | 默认前台主题样式及许可文件 |
| `public/uploads/` | 后台上传的图片；只允许静态访问 |
| `bin/quickstart.php` | SQLite、示例内容与本地授权一键安装 |
| `bin/license.php` | 签发、验证、激活和查询命令 |
| `bin/issuer.php`、`issuer/` | 签发机专用的授权中心命令与本地管理页；不应部署到客户站点 |
| `bin/migrate.php` | 现有站点数据库升级命令 |
| `config/app.php` | 安装后生成的站点配置，不入库 |
| `var/` | SQLite 数据库和已激活授权码，不入库 |

授权码格式为 `FD1.base64url(payload).base64url(signature)`。私钥不部署到站点。公钥轮换时，可在 `config/app.php` 的 `license_public_keys` 中保留旧密钥 ID 并添加新公钥；签发时指定相应 ID。离线模式没有即时撤销能力，撤销需通过更新受信任公钥或后续引入签名撤销列表。

站点会把请求的 `Host` 与安装时配置的域名比较，并忽略 `X-Forwarded-Host`。正式部署还应在 Web 服务器中只接收已配置的站点域名。运行环境所有者能够修改本地程序，因此离线验签提供可验证的签发来源，不能替代服务器侧的访问控制。

## 验证

```sh
php tests/run.php
PHP_BINARY=/path/to/php python3 tests/http_smoke.py
PHP_BINARY=/path/to/php python3 tests/quickstart_smoke.py
PHP_BINARY=/path/to/php python3 tests/issuer_smoke.py
```

`tests/run.php` 覆盖 SQLite 快速安装、旧库升级、内容、单页、站点设置、会员、权限、分页和授权。`tests/http_smoke.py` 在临时副本上走完安装、后台管理、图片上传、会员访问及授权激活。`tests/issuer_smoke.py` 使用临时私钥与数据库验证授权中心签发、导入、登录、CSRF 和域名验签。MySQL 专项测试 `tests/mysql.php` 只允许连接名称以 `_test` 结尾的**空测试数据库**，需通过 `FANGDAYS_MYSQL_HOST`、`FANGDAYS_MYSQL_PORT`、`FANGDAYS_MYSQL_NAME`、`FANGDAYS_MYSQL_USER` 和 `FANGDAYS_MYSQL_PASSWORD` 指定连接。

## 从首期版本升级

部署新代码前，先备份 `config/app.php`、`var/` 和 MySQL 数据库（如使用 MySQL）。然后在项目目录执行：

```sh
php bin/migrate.php
```

该命令为原有站点增加标签、会员、角色权限、文章可见范围、单页、站点设置和图片封面所需的表。首次访问也会自动执行同一升级。SQLite 升级在事务内完成；MySQL 建表使用可重复执行的语句，若中途失败，排除原因后可重试。升级不会删除原有数据。
