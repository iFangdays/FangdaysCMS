# 第三方样式来源

- `public/assets/theme/aoyun.css`：从本机 PbootCMS 3.2.10 的 `template/default/css/aoyun.css` 复制，用于 FangdaysCMS 默认前台主题。该样式归原作者所有，遵循 PbootCMS 仓库所附 Apache License 2.0；许可全文见 `public/assets/theme/PbootCMS-LICENSE.txt`。FangdaysCMS 的 PHP 模板与 `templates/default/` HTML 片段由本项目重新编写，仅解析文档列出的 Pboot 风格运营标签子集。
- `public/assets/theme/bootstrap.min.css`：从本机 PbootCMS 默认模板所附 Bootstrap 4.4.1 复制。Bootstrap 版权声明保留在 CSS 文件开头，MIT 许可全文见 `public/assets/theme/Bootstrap-LICENSE.txt`。
- `public/assets/images/`：从本机 PbootCMS 默认模板复制六张小型图片，以保证 `aoyun.css` 的相对路径资源可用；同样保留 PbootCMS 的 Apache License 2.0 来源说明。

默认前台只加载上述样式与 CSS 所需的图片；原站后端程序、授权文件和内容数据未复制。后台富文本编辑器的浏览器资源另见下文。

## 后台界面资源

- `public/assets/pboot-admin/css/comm.css`、`css/login.css` 和 `images/bg.jpg`：原样复制本机 PbootCMS 3.2.10 的对应文件。后台外壳与内容页参考该版本的 `common/head.html`、`content/content.html`、`content/contentsort.html` 和 `system/home.html` 结构，PHP 路由及数据绑定由 FangdaysCMS 实现。许可见 `public/assets/pboot-admin/PbootCMS-LICENSE.txt`。
- `public/assets/pboot-admin/layui/`：从上述后台复制 Layui 2.5.7 CSS 和图标字体。MIT 许可见 `public/assets/pboot-admin/Layui-LICENSE.txt`。没有复制或执行 PbootCMS 后台脚本。
- `public/assets/pboot-admin/font-awesome/`：从上述后台复制 Font Awesome 4.7.0 CSS 与字体。CSS 按 MIT 许可，字体按 SIL OFL 1.1 许可；许可文本分别见 `public/assets/pboot-admin/FontAwesome-CSS-MIT.txt` 和 `public/assets/pboot-admin/FontAwesome-Font-OFL.txt`。

## 富文本编辑资源

- `public/assets/ueditor/`：从本机 PbootCMS 3.2.10 的 `core/extend/ueditor/` 选取浏览器端 UEditor 1.4.3 资源（压缩脚本、中文语言包、主题及链接对话框）。UEditor 原项目采用 MIT 许可，PbootCMS 的随附修改保留其 Apache License 2.0 来源说明。未复制或执行原目录的 PHP 上传后端；图片上传调用 FangdaysCMS 自身的鉴权、CSRF 与媒体校验流程。许可见 `public/assets/ueditor/UEditor-MIT.txt` 和 `public/assets/ueditor/PbootCMS-LICENSE.txt`。
- `third_party/htmlpurifier/library/`：从 [HTML Purifier 4.19.0](https://github.com/ezyang/htmlpurifier/tree/v4.19.0) 的发布标签复制 PHP 清理库，Git 提交 `b287d2a`；遵循 LGPL 2.1，许可见 `third_party/htmlpurifier/LICENSE`。用于保存与展示文章富文本时过滤危险标签、属性及 URL。

## 前台轮播资源

- `public/assets/splide/`：从 [Splide 4.1.4](https://github.com/Splidejs/splide) 的 npm 发布包复制压缩 CSS 和 JavaScript，用于首页轮播；无运行时依赖，MIT 许可见 `public/assets/splide/LICENSE`。图片、标题和链接仍由 FangdaysCMS 数据库管理。
