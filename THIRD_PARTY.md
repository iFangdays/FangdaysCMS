# 第三方样式来源

- `public/assets/theme/aoyun.css`：从本机 PbootCMS 3.2.10 的 `template/default/css/aoyun.css` 复制，用于 FangdaysCMS 默认前台主题。该样式归原作者所有，遵循 PbootCMS 仓库所附 Apache License 2.0；许可全文见 `public/assets/theme/PbootCMS-LICENSE.txt`。FangdaysCMS 的 PHP 模板由本项目重新编写，不执行 PbootCMS 模板标签。
- `public/assets/theme/bootstrap.min.css`：从本机 PbootCMS 默认模板所附 Bootstrap 4.4.1 复制。Bootstrap 版权声明保留在 CSS 文件开头，MIT 许可全文见 `public/assets/theme/Bootstrap-LICENSE.txt`。
- `public/assets/images/`：从本机 PbootCMS 默认模板复制六张小型图片，以保证 `aoyun.css` 的相对路径资源可用；同样保留 PbootCMS 的 Apache License 2.0 来源说明。

前台只加载上述样式与 CSS 所需的图片；原站程序、授权文件、JavaScript 和内容数据未复制。

## 后台界面资源

- `public/assets/pboot-admin/css/comm.css`、`css/login.css` 和 `images/bg.jpg`：原样复制本机 PbootCMS 3.2.10 的对应文件。后台外壳与内容页参考该版本的 `common/head.html`、`content/content.html`、`content/contentsort.html` 和 `system/home.html` 结构，PHP 路由及数据绑定由 FangdaysCMS 实现。许可见 `public/assets/pboot-admin/PbootCMS-LICENSE.txt`。
- `public/assets/pboot-admin/layui/`：从上述后台复制 Layui 2.5.7 CSS 和图标字体。MIT 许可见 `public/assets/pboot-admin/Layui-LICENSE.txt`。没有复制或执行 PbootCMS 后台脚本。
- `public/assets/pboot-admin/font-awesome/`：从上述后台复制 Font Awesome 4.7.0 CSS 与字体。CSS 按 MIT 许可，字体按 SIL OFL 1.1 许可；许可文本分别见 `public/assets/pboot-admin/FontAwesome-CSS-MIT.txt` 和 `public/assets/pboot-admin/FontAwesome-Font-OFL.txt`。
