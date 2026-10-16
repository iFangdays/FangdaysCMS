# FangdaysCMS

FangdaysCMS 是独立开发的 PHP 内容管理系统，目标是在功能上逐步覆盖 PbootCMS 的主要 CMS 能力。后端与授权码独立实现；默认前台主题适配了 PbootCMS 自带样式，来源与许可见 [THIRD_PARTY.md](THIRD_PARTY.md)。功能差距见 [ROADMAP.md](ROADMAP.md)。

## 当前功能

- 快速安装：一条命令配置 SQLite、管理员、初始栏目、文章、单页与离线授权；也可用安装向导选择 MySQL。
- 后台：分组侧栏导航与概览、列表/单页/跳转栏目管理及文章数量、栏目 SEO 设置、单页栏目内容编辑与发布、文章按关键词/栏目/状态/置顶/推荐/头条检索、草稿与发布、批量发布/转草稿/删除/复制/移动、本页排序保存、授权码激活。文章可设置副标题、作者、来源、标题颜色、跳转链接、发布时间、排序值、SEO 关键词与描述，并管理多图标题、顺序和单个附件。
- 前台：首页、栏目页、单页栏目、文章页、独立单页、标签页、搜索页；跳转栏目与跳转文章使用已配置链接，栏目及文章 SEO 元信息进入页面。已公开文章按置顶、推荐、头条、排序值和发布时间排序；旧文章正文继续按纯文本转义，新富文本正文经服务端白名单清理后展示。
- 授权：签发端用 Ed25519 私钥生成 `FD1` 授权码；网站只保存公钥并在本地验签，检查产品、密钥 ID、域名和有效期。无有效授权时前台停止提供内容，后台仍可登录并激活。
- 授权中心：在签发机本地打开独立管理页，按客户和域名签发、复制、检索授权码，导入旧授权并维护备注；记录存放在签发机专用 SQLite 中，不依赖客户站点是否安装。
- 内容组织：多级栏目、标签、文章草稿与发布、公开或会员可见范围、搜索和每页 20 条的分页；父栏目可汇总子栏目文章并显示面包屑。内容模型可绑定栏目，设置文本、多行、单选和多选扩展字段，文章与单页栏目编辑后在前台展示。列表栏目可按单选、多选扩展字段组合筛选，分页保留筛选条件。
- 会员：邮箱注册、登录、资料与密码修改；管理员可停用或恢复会员。停用后既有会话也无法继续阅读会员内容。
- 后台权限：固定的站点所有者角色、可编辑的角色权限、后台账号创建/编辑/删除。权限在路由执行前检查。
- 站点设置：管理员可修改站点名称、页面简介和页脚文字；单页可单独排序并进入前台导航。
- 内容运营：后台可维护按分组、排序的轮播图和友情链接，编辑公司信息与纯文本自定义标签。首页展示第 1 组轮播图，页脚展示第 1 组友链及公司名称、电话；页脚文字可使用 `{label:home_slogan}`、`{pboot:companyphone}` 等纯文本占位符。默认主题的轮播和友链片段使用 `{pboot:slide gid=1 num=20}`、`{pboot:link gid=1 num=30}` 循环，可用 `start` 调整起始位置，循环内支持常见字段及非空判断。轮播图片和友链 Logo 从媒体库选择，被引用时不能删除。
- 富文本与媒体图片：文章、独立单页和单页栏目复用 PbootCMS 的 UEditor 资源，可编辑段落、文字样式、列表与链接，并选择已有图片或上传新图片插入正文。服务端以 HTML Purifier 清理富文本；旧单页保持纯文本展示，首次通过编辑器保存时转换为富文本。后台上传 JPG、PNG、WebP、GIF 图片用于封面、正文和多图；上传文件会校验 MIME、图片尺寸与大小，使用随机文件名。被正文引用的图片不能直接删除。文章附件支持 PDF、TXT、ZIP、DOCX、XLSX、PPTX，最大 20 MB，存放在非公开目录；下载时检查文章发布状态与会员权限。
- 升级：已有 SQLite/MySQL 安装会增加新表并保留原文章和管理员；管理员账号升级后默认归入站点所有者角色。

旧站升级时保留已有角色授权；若现有编辑角色需要进入「内容运营」，站点所有者可在「角色权限」中授予 `operations.manage`。新安装站点的默认编辑角色包含该权限。

自定义标签目前只支持纯文本。`templates/default/` 中的可信 HTML 片段支持轮播/友链的 `gid`、`num`、`start` 和 `[slide:*]`、`[link:*]` 常见字段，以及 `{label:*}`、`{pboot:company*}` 与循环内 `{pboot:if([slide:link])}` 形式的非空判断；尚无后台模板编辑、任意主题选择或完整 Pboot 标签解析。单页的多图和附件映射、评论、完整模型类型及扩展字段筛选等后续模块尚未实现。公开注册暂不提供邮箱验证、找回密码和跨会话限流；正式开放注册前应按站点需求补齐这些能力。

编辑器无法加载时保留正文输入框。旧文章和旧单页初次在编辑器中保存后转为富文本；富文本仅允许常见排版元素及 http(s)/mailto 链接，脚本、事件属性和危险 URL 会被过滤。正文图片由具备 `media.manage` 权限的后台账号上传；其他有对应编辑权限的账号仍可选用媒体库已有图片。

文章列表可选中本页最多 20 篇文章，指定列表栏目后复制或移动。副本保留正文、发布状态、可见范围、标签、封面、文章属性与扩展字段，并自动生成唯一 URL 别名；移动保留原 URL 别名。目标栏目的模型不同时，原扩展字段仍保存在数据库中，但只显示目标模型中已有的字段值。文章列表可直接调整本页排序值，数值越小越靠前；置顶、推荐和头条仍优先于排序值。将已发布文章的发布时间设为未来时，后台显示“待发布”，到时自动在前台列表与详情开放，无需定时任务。发布时间按北京时间（`Asia/Shanghai`）输入和显示，与 PbootCMS 原版一致。

文章的跳转链接仅接受 http(s) 地址或站内绝对路径；会员可见文章即使配置跳转，直接访问文章地址仍须先通过会员权限检查。文章属性数据通过增量迁移添加，未设置新属性的旧文章保持原有展示。

栏目可以指定上级，列表栏目页展示下级入口并汇总后代文章。单页栏目保存后在「编辑内容」中填写并发布正文；草稿单页不进入前台导航，直接访问返回 404。跳转栏目只接受 http(s) 地址或站内绝对路径。已有直属文章的栏目不能改为单页或跳转类型，需先移动文章。删除含子栏目的栏目会被拒绝；删除叶子栏目后，其文章保留为未分类。

## 环境

PHP 8.2+，启用 `sodium`、`json`、`mbstring`、`fileinfo`、`DOM`、`libxml`、`PDO`，以及 `pdo_sqlite` 或 `pdo_mysql`。Web 服务器的**文档根目录必须指向 `public/`**；`config/` 和 `var/` 不可作为公开目录。`public/uploads/` 必须对 PHP 进程可写，并在 Web 服务器中禁止该目录执行脚本。MySQL 使用已存在的空数据库，安装器负责建表。

本项目当前位于原 PbootCMS 目录内。如果原站仍把该父目录作为 Web 根目录，务必在其 Web 服务器中拒绝外部访问整个 `/FangdaysCMS/` 路径；FangdaysCMS 使用单独站点或虚拟主机，将根目录指向 `FangdaysCMS/public/`。项目内敏感目录附带 Apache `.htaccess` 拒绝规则，但 Nginx 不读取这些规则，需要在 Nginx 配置中单独拦截。不要依靠 PHP 路由保护可直接下载的数据库文件。

无需下载 Composer 包；`autoload.php` 可直接加载项目类。富文本清理库 HTML Purifier 的已许可源码随项目提供，来源见 [THIRD_PARTY.md](THIRD_PARTY.md)。`composer.json` 留作后续依赖管理和 IDE 识别。

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
| `templates/default/` | 默认前台轮播与友链的 Pboot 风格 HTML 标签片段 |
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

`tests/run.php` 覆盖 SQLite 快速安装、旧库升级、内容、多图关联、富文本清理、单页、站点设置、内容运营、会员、权限、分页和授权。`tests/http_smoke.py` 在临时副本上走完安装、后台管理、图片与附件上传、多图与富文本发布、轮播、友链、公司信息、自定义标签、访问控制、会员访问及授权激活。`tests/issuer_smoke.py` 使用临时私钥与数据库验证授权中心签发、导入、登录、CSRF 和域名验签。MySQL 专项测试 `tests/mysql.php` 只允许连接名称以 `_test` 结尾的**空测试数据库**，需通过 `FANGDAYS_MYSQL_HOST`、`FANGDAYS_MYSQL_PORT`、`FANGDAYS_MYSQL_NAME`、`FANGDAYS_MYSQL_USER` 和 `FANGDAYS_MYSQL_PASSWORD` 指定连接。

## 从首期版本升级

部署新代码前，先备份 `config/app.php`、`var/` 和 MySQL 数据库（如使用 MySQL）。然后在项目目录执行：

```sh
php bin/migrate.php
```

该命令为原有站点增加标签、会员、角色权限、文章可见范围、单页、站点设置、图片封面、多图与附件所需的表。首次访问也会自动执行同一升级。SQLite 升级在事务内完成；MySQL 建表使用可重复执行的语句，若中途失败，排除原因后可重试。升级不会删除原有数据。
