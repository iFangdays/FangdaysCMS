"""End-to-end smoke test using a disposable project copy and PHP's local server."""

import http.cookiejar
import base64
import json
import os
import pathlib
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


SOURCE = pathlib.Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BINARY") or shutil.which("php")
if not PHP:
    raise SystemExit("Set PHP_BINARY to a PHP 8.2+ executable")


def command(*args):
    return subprocess.run([PHP, *map(str, args)], check=True, text=True, capture_output=True).stdout.strip()


def csrf(html):
    found = re.search(r'name="_csrf" value="([a-f0-9]+)"', html)
    assert found, "CSRF field missing"
    return found.group(1)


with tempfile.TemporaryDirectory(prefix="fangdays-http-") as workspace:
    root = pathlib.Path(workspace)
    site = root / "site"
    shutil.copytree(SOURCE, site, ignore=shutil.ignore_patterns("app.php", "*.sqlite", "license.code"))
    shutil.rmtree(site / "config")
    private_key = root / "issuer.key"
    public_key = command(site / "bin/license.php", "keygen", private_key).split("=", 1)[1]

    with socket.socket() as probe:
        probe.bind(("127.0.0.1", 0))
        port = probe.getsockname()[1]

    session_directory = root / "sessions"
    session_directory.mkdir(mode=0o700)

    server = subprocess.Popen(
        [PHP, "-d", f"session.save_path={session_directory}", "-S", f"127.0.0.1:{port}", "-t", str(site / "public"), str(site / "public/router.php")],
        cwd=site,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    base = f"http://127.0.0.1:{port}"
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(path, fields=None, host=None, client=None):
        payload = urllib.parse.urlencode(fields, doseq=True).encode() if fields is not None else None
        req = urllib.request.Request(base + path, data=payload)
        if host:
            req.add_header("Host", host)
        try:
            response = (client or opener).open(req, timeout=5)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, response.read().decode("utf-8")

    try:
        for _ in range(30):
            try:
                status, install = request("/install")
                break
            except urllib.error.URLError:
                time.sleep(0.1)
        else:
            raise AssertionError("PHP server did not start")
        assert status == 200 and "创建 FangdaysCMS" in install

        status, login = request(
            "/install",
            {
                "_csrf": csrf(install),
                "site_name": "Fangdays 测试站",
                "site_domain": "127.0.0.1",
                "admin_username": "owner",
                "admin_password": "strong-test-password-123",
                "license_key_id": "main",
                "license_public_key": public_key,
                "database_driver": "sqlite",
            },
        )
        assert status == 200 and "后台登录" in login, login[:400]
        assert "schema version 20 is ready" in command(site / "bin/migrate.php")
        status, unlicensed = request("/")
        assert status == 503 and "尚未获得有效授权" in unlicensed

        status, dashboard = request(
            "/login", {"_csrf": csrf(login), "username": "owner", "password": "strong-test-password-123"}
        )
        assert status == 200 and "管理概览" in dashboard and 'id="admin-navigation"' in dashboard
        assert "/assets/pboot-admin/layui/css/layui.css" in dashboard

        status, categories = request("/admin/categories/new")
        assert status == 200
        status, category_list = request(
            "/admin/categories", {"_csrf": csrf(categories), "name": "新闻", "slug": "news", "sort_order": "1"}
        )
        assert status == 200 and "新闻" in category_list
        status, category_form = request("/admin/categories/1/edit")
        assert status == 200 and 'name="is_visible"' in category_form
        status, hidden_list = request("/admin/categories/1/edit", {
            "_csrf": csrf(category_form), "name": "新闻", "slug": "news", "sort_order": "1", "is_visible": "0"
        })
        assert status == 200 and "隐藏" in hidden_list
        status, hidden_form = request("/admin/categories/1/edit")
        assert status == 200 and 'value="0" selected' in hidden_form
        status, invalid_visibility = request("/admin/categories/1/edit", {
            "_csrf": csrf(hidden_form), "name": "新闻", "slug": "news", "sort_order": "1", "is_visible": "maybe"
        })
        assert status == 422 and "显示状态" in invalid_visibility
        status, restored_list = request("/admin/categories/1/edit", {
            "_csrf": csrf(hidden_form), "name": "新闻", "slug": "news", "sort_order": "1", "is_visible": "1"
        })
        assert status == 200
        status, bad_category = request("/admin/categories", {"_csrf": csrf(categories), "name": "错误栏目", "slug": "bad", "sort_order": "abc"})
        assert status == 422 and "栏目排序必须是整数" in bad_category

        status, new_tag = request("/admin/tags/new")
        assert status == 200
        status, tag_list = request("/admin/tags", {"_csrf": csrf(new_tag), "name": "专题", "slug": "topic"})
        assert status == 200 and "专题" in tag_list
        tag_id = re.search(r'/admin/tags/(\d+)/edit', tag_list).group(1)

        status, new_post = request("/admin/posts/new")
        assert "ueditor.all.min.js" in new_post and 'id="post-editor"' in new_post
        assert request("/assets/ueditor/ueditor.all.min.js")[0] == 200
        assert status == 200
        status, media_form = request("/admin/media")
        assert status == 200
        boundary = "fangdays-test-boundary"
        png = base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jR5kAAAAASUVORK5CYII=")
        body = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"_csrf\"\r\n\r\n{csrf(media_form)}\r\n"
                f"--{boundary}\r\nContent-Disposition: form-data; name=\"image\"; filename=\"cover.png\"\r\nContent-Type: image/png\r\n\r\n").encode() + png + f"\r\n--{boundary}--\r\n".encode()
        upload_request = urllib.request.Request(base + "/admin/media", data=body, headers={"Content-Type": f"multipart/form-data; boundary={boundary}"})
        with opener.open(upload_request, timeout=5) as response:
            media_list = response.read().decode("utf-8")
        assert "图片已上传" in media_list
        image_id = re.search(r'/admin/media/(\d+)/delete', media_list).group(1)
        status, category_image_form = request("/admin/categories/1/edit")
        assert status == 200 and 'name="thumbnail_media_id"' in category_image_form and 'name="banner_media_id"' in category_image_form
        category_image_fields = {
            "_csrf": csrf(category_image_form), "name": "新闻", "slug": "news", "sort_order": "1",
            "model_id": "1", "type": "list", "thumbnail_media_id": image_id, "banner_media_id": image_id,
        }
        status, category_image_saved = request("/admin/categories/1/edit", category_image_fields)
        assert status == 200 and "栏目已保存" in category_image_saved
        status, bad_category_image = request("/admin/categories/1/edit", {**category_image_fields, "name": "不应保存", "banner_media_id": "999999"})
        assert status == 422 and "栏目图片不存在" in bad_category_image
        assert 'value="新闻"' in request("/admin/categories/1/edit")[1]
        invalid_upload = urllib.request.Request(base + "/admin/media", data=body.replace(png, b"not-an-image"), headers={"Content-Type": f"multipart/form-data; boundary={boundary}"})
        try:
            opener.open(invalid_upload, timeout=5)
            raise AssertionError("Invalid image was accepted")
        except urllib.error.HTTPError as error:
            assert error.code == 422 and "图片格式或尺寸无效" in error.read().decode("utf-8")
        status, post_list = request(
            "/admin/posts",
            {
                "_csrf": csrf(new_post),
                "title": "第一篇文章",
                "slug": "first-post",
                "category_id": "1",
                "body": "<script>alert(1)</script>",
                "status": "published",
                "visibility": "public",
                "tag_ids[]": tag_id,
                "cover_media_id": image_id,
            },
        )
        assert status == 200 and "第一篇文章" in post_list
        status, still_media = request(f"/admin/media/{image_id}/delete", {"_csrf": csrf(media_list)})
        assert status == 200 and "图片正被文章封面使用" in still_media
        status, new_page = request("/admin/pages/new")
        assert status == 200 and 'id="single-editor"' in new_page and 'name="body_format"' in new_page
        assert 'enctype="multipart/form-data"' in new_page and 'name="cover_media_id"' in new_page and 'id="gallery-add"' in new_page and 'name="attachment"' in new_page
        status, bad_new_page = request("/admin/pages", {
            "_csrf": csrf(new_page), "title": "坏单页", "slug": "bad-page", "body": "不能保存",
            "status": "published", "menu_order": "10", "cover_media_id": "999999",
        })
        assert status == 422 and "封面图片不存在" in bad_new_page
        assert "bad-page" not in request("/admin/pages")[1]
        status, page_list = request("/admin/pages", {
            "_csrf": csrf(new_page), "title": "关于我们", "slug": "about", "body": "站点简介",
            "status": "published", "menu_order": "10", "cover_media_id": image_id,
            "gallery_media_ids[]": image_id, "gallery_titles[]": "关于我们图片",
        })
        assert status == 200 and "关于我们" in page_list
        page_id = re.search(r'/admin/pages/(\d+)/edit', page_list).group(1)
        status, settings_form = request("/admin/settings")
        assert status == 200
        status, saved_settings = request("/admin/settings", {
            "_csrf": csrf(settings_form), "site_name": "新的测试站", "description": "站点简介", "footer_text": "自定义页脚"
        })
        assert status == 200 and "站点设置已保存" in saved_settings
        status, member_post_form = request("/admin/posts/new")
        status, member_post_list = request(
            "/admin/posts",
            {
                "_csrf": csrf(member_post_form), "title": "会员专享", "slug": "members-only",
                "category_id": "1", "body": "内部正文", "status": "published",
                "visibility": "members", "tag_ids[]": tag_id,
            },
        )
        assert status == 200 and "会员专享" in member_post_list
        status, filtered = request("/admin/posts?q=%E7%AC%AC%E4%B8%80%E7%AF%87&status=published&category_id=1")
        assert status == 200 and "第一篇文章" in filtered and "会员专享" not in filtered
        assert 'name="q"' in filtered and 'name="category_id"' in filtered
        status, category_admin = request("/admin/categories")
        assert status == 200 and "2 篇" in category_admin and "/admin/posts?category_id=1" in category_admin

        status, batch_form = request("/admin/posts/new")
        status, batch_list = request("/admin/posts", {
            "_csrf": csrf(batch_form), "title": "待批量操作", "slug": "bulk-smoke",
            "category_id": "1", "body": "批量测试", "status": "draft", "visibility": "public"
        })
        assert status == 200 and "待批量操作" in batch_list
        batch_id = re.search(r'待批量操作.*?/admin/posts/(\d+)/edit', batch_list, re.S).group(1)
        status, drafts = request("/admin/posts?status=draft&q=%E5%BE%85%E6%89%B9%E9%87%8F")
        assert status == 200 and "待批量操作" in drafts and "第一篇文章" not in drafts
        status, no_selection = request("/admin/posts/bulk", {"_csrf": csrf(drafts), "action": "delete"})
        assert status == 200 and "操作失败：请选择本页文章" in no_selection
        status, published = request("/admin/posts/bulk", {
            "_csrf": csrf(drafts), "post_ids[]": batch_id, "action": "publish"
        })
        assert status == 200 and "已处理 1 篇文章" in published
        status, published_filter = request("/admin/posts?status=published&q=%E5%BE%85%E6%89%B9%E9%87%8F")
        assert status == 200 and "待批量操作" in published_filter
        status, deleted = request("/admin/posts/bulk", {
            "_csrf": csrf(published_filter), "post_ids[]": batch_id, "action": "delete"
        })
        assert status == 200 and "已处理 1 篇文章" in deleted
        assert "待批量操作" not in request("/admin/posts?q=%E5%BE%85%E6%89%B9%E9%87%8F")[1]
        status, still_unlicensed = request("/post/first-post")
        assert status == 503

        code = command(site / "bin/license.php", "issue", private_key, "main", "127.0.0.1", "never", "publish")
        status, license_form = request("/admin/license")
        assert status == 200
        status, activated = request("/admin/license", {"_csrf": csrf(license_form), "code": code})
        assert status == 200 and "授权码已激活" in activated
        assert '"valid":true' in command(site / "bin/license.php", "status", site / "config/app.php")

        status, front = request("/")
        assert status == 200 and "第一篇文章" in front and "/page/about" in front and "自定义页脚" in front and "/uploads/" in front
        status, visible_form = request("/admin/categories/1/edit")
        status, hidden_list = request("/admin/categories/1/edit", {
            "_csrf": csrf(visible_form), "name": "新闻", "slug": "news", "sort_order": "1", "is_visible": "0"
        })
        assert status == 200 and 'href="/category/news"' not in request("/")[1]
        assert request("/category/news")[0] == 200 and request("/post/first-post")[0] == 200
        status, hidden_form = request("/admin/categories/1/edit")
        status, restored_list = request("/admin/categories/1/edit", {
            "_csrf": csrf(hidden_form), "name": "新闻", "slug": "news", "sort_order": "1", "is_visible": "1"
        })
        assert status == 200 and 'href="/category/news"' in request("/")[1]
        assert request("/category/news")[1].count('class="site-category-banner"') == 1
        assert request("/category/news")[1].count('class="site-category-thumbnail"') == 1
        status, about = request("/page/about")
        assert status == 200 and "站点简介" in about and 'aria-label="单页多图"' in about and "关于我们图片" in about
        status, page_edit = request(f"/admin/pages/{page_id}/edit")
        assert status == 200 and 'id="single-media-picker"' in page_edit and f'<option value="{image_id}" selected' in page_edit
        status, rich_page = request(f"/admin/pages/{page_id}/edit", {
            "_csrf": csrf(page_edit), "title": "关于我们", "slug": "about",
            "body": "<p><strong>富文本单页</strong><script>alert(1)</script></p>",
            "body_format": "html", "status": "published", "menu_order": "10", "cover_media_id": image_id,
            "gallery_media_ids[]": image_id, "gallery_titles[]": "关于我们图片",
        })
        assert status == 200 and "单页已保存" in rich_page
        public_page = request("/page/about")[1]
        assert "<strong>富文本单页</strong>" in public_page and "<script>alert(1)</script>" not in public_page
        status, duplicate_page_gallery = request(f"/admin/pages/{page_id}/edit", {
            "_csrf": csrf(page_edit), "title": "关于我们", "slug": "about", "body": "不应覆盖", "status": "published", "menu_order": "10",
            "gallery_media_ids[]": [image_id, image_id], "gallery_titles[]": ["一", "二"],
        })
        assert status == 422 and "多图包含无效、重复" in duplicate_page_gallery
        assert "不应覆盖" not in request("/page/about")[1]
        page_pdf = b"%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n"
        page_fields = {"_csrf": csrf(page_edit), "title": "关于我们", "slug": "about", "body": "附件说明", "status": "published", "menu_order": "10", "cover_media_id": image_id}
        page_parts = b"".join((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode() for key, value in page_fields.items())
        page_parts += (f'--{boundary}\r\nContent-Disposition: form-data; name="attachment"; filename="about.pdf"\r\nContent-Type: application/pdf\r\n\r\n').encode() + page_pdf + f'\r\n--{boundary}--\r\n'.encode()
        with opener.open(urllib.request.Request(base + f"/admin/pages/{page_id}/edit", data=page_parts, headers={"Content-Type": f"multipart/form-data; boundary={boundary}"}), timeout=5) as response:
            assert "单页已保存" in response.read().decode("utf-8")
        assert "下载附件：about.pdf" in request("/page/about")[1]
        with opener.open(base + "/page/about/attachment", timeout=5) as response:
            assert response.read() == page_pdf and response.headers["X-Content-Type-Options"] == "nosniff"
        status, page_draft = request(f"/admin/pages/{page_id}/edit", {**page_fields, "status": "draft"})
        assert status == 200 and request("/page/about")[0] == 404 and request("/page/about/attachment")[0] == 404
        status, page_republished = request(f"/admin/pages/{page_id}/edit", {**page_fields, "status": "published"})
        assert status == 200 and "下载附件：about.pdf" in request("/page/about")[1]
        status, page_attachment_removed = request(f"/admin/pages/{page_id}/edit", {**page_fields, "remove_attachment": "1"})
        assert status == 200 and request("/page/about/attachment")[0] == 404
        assert request("/assets/theme/bootstrap.min.css")[0] == 200
        assert request("/assets/theme/aoyun.css")[0] == 200
        status, slide_form = request("/admin/slides/new")
        assert status == 200 and "轮播图" in slide_form
        assert request("/assets/splide/splide.min.js")[0] == 200
        status, slide_list = request("/admin/slides", {
            "_csrf": csrf(slide_form), "group_id": "1", "media_id": image_id,
            "title": "运营横幅", "subtitle": "重要消息", "link": "/search", "sort_order": "2"
        })
        assert status == 200 and "运营横幅" in slide_list
        slide_id = re.search(r'/admin/slides/(\d+)/edit', slide_list).group(1)
        status, slide_home = request("/")
        assert status == 200 and '<h2>运营横幅</h2>' in slide_home and 'href="/search">了解更多</a>' in slide_home and "splide.min.js" in slide_home
        assert "[slide:title]" not in slide_home and "{pboot:slide" not in slide_home
        status, slide_edit = request(f"/admin/slides/{slide_id}/edit")
        status, unsafe_slide = request(f"/admin/slides/{slide_id}/edit", {
            "_csrf": csrf(slide_edit), "group_id": "1", "media_id": image_id,
            "title": "恶意横幅", "subtitle": "", "link": "javascript:alert(1)", "sort_order": "2"
        })
        assert status == 422 and "链接只支持" in unsafe_slide
        assert "运营横幅" in request("/")[1] and "恶意横幅" not in request("/")[1]
        status, moved_slide = request(f"/admin/slides/{slide_id}/edit", {
            "_csrf": csrf(slide_edit), "group_id": "2", "media_id": image_id,
            "title": "第二组横幅", "subtitle": "", "link": "", "sort_order": "1"
        })
        assert status == 200 and "第二组横幅" in moved_slide
        assert "splide.min.js" not in request("/")[1]
        status, link_form = request("/admin/links/new")
        status, link_list = request("/admin/links", {
            "_csrf": csrf(link_form), "group_id": "1", "media_id": image_id,
            "name": "合作网站", "link": "https://example.com/", "sort_order": "3"
        })
        assert status == 200 and "合作网站" in link_list
        link_id = re.search(r'/admin/links/(\d+)/edit', link_list).group(1)
        linked_home = request("/")[1]
        assert 'href="https://example.com/" target="_blank" rel="noopener noreferrer"' in linked_home and "合作网站" in linked_home
        assert "[link:name]" not in linked_home and "{pboot:link" not in linked_home
        status, company_form = request("/admin/company")
        status, saved_company = request("/admin/company", {
            "_csrf": csrf(company_form), "name": "测试企业", "phone": "010-12345678", "email": "info@example.com"
        })
        assert status == 200 and "公司信息已保存" in saved_company
        status, label_form = request("/admin/labels/new")
        status, label_list = request("/admin/labels", {
            "_csrf": csrf(label_form), "name": "home_slogan", "description": "首页短句", "value": "<运营标语>"
        })
        assert status == 200 and "home_slogan" in label_list
        label_id = re.search(r'/admin/labels/(\d+)/edit', label_list).group(1)
        status, settings_form = request("/admin/settings")
        status, tagged_settings = request("/admin/settings", {
            "_csrf": csrf(settings_form), "site_name": "新的测试站", "description": "站点简介",
            "footer_text": "{label:home_slogan} {pboot:companyphone}"
        })
        assert status == 200 and "站点设置已保存" in tagged_settings
        tagged_home = request("/")[1]
        assert "&lt;运营标语&gt; 010-12345678" in tagged_home and "<运营标语>" not in tagged_home
        assert "测试企业" in tagged_home and "合作网站" in tagged_home
        status, media_in_use = request(f"/admin/media/{image_id}/delete", {"_csrf": csrf(slide_form)})
        assert status == 200 and "使用" in media_in_use
        status, deleted_slide = request(f"/admin/slides/{slide_id}/delete", {"_csrf": csrf(slide_list)})
        assert status == 200 and "轮播图已删除" in deleted_slide
        status, deleted_link = request(f"/admin/links/{link_id}/delete", {"_csrf": csrf(link_list)})
        assert status == 200 and "友情链接已删除" in deleted_link
        status, deleted_label = request(f"/admin/labels/{label_id}/delete", {"_csrf": csrf(label_list)})
        assert status == 200 and "自定义标签已删除" in deleted_label
        assert "合作网站" not in request("/")[1]
        assert "第一篇文章" in request("/?page=999")[1]
        guest = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        status, guest_home = request("/", client=guest)
        assert status == 200 and "会员专享" not in guest_home
        status, member_required = request("/post/members-only", client=guest)
        assert status == 403 and "登录后阅读" in member_required
        status, guest_tag = request("/tag/topic", client=guest)
        assert status == 200 and "第一篇文章" in guest_tag and "会员专享" not in guest_tag
        status, guest_search = request("/search?q=%E4%BC%9A%E5%91%98", client=guest)
        assert status == 200 and "会员专享" not in guest_search

        status, register = request("/member/register", client=guest)
        status, profile = request("/member/register", {
            "_csrf": csrf(register), "email": "reader@example.com", "display_name": "读者",
            "password": "reader-test-password-123"
        }, client=guest)
        assert status == 200 and "读者" in profile, (status, profile[:500])
        status, changed_password = request("/member/password", {
            "_csrf": csrf(profile), "current_password": "reader-test-password-123",
            "new_password": "reader-new-password-123"
        }, client=guest)
        assert status == 200 and "密码已更新" in changed_password
        status, member_article = request("/post/members-only", client=guest)
        assert status == 200 and "内部正文" in member_article
        status, member_tag = request("/tag/topic", client=guest)
        assert status == 200 and "会员专享" in member_tag
        status, members_admin = request("/admin/members")
        assert status == 200 and "reader@example.com" in members_admin
        member_id = re.search(r'/admin/members/(\d+)/status', members_admin).group(1)
        status, disabled = request(f"/admin/members/{member_id}/status", {
            "_csrf": csrf(members_admin), "status": "disabled"
        })
        assert status == 200 and "停用" in disabled
        status, blocked_member = request("/post/members-only", client=guest)
        assert status == 403 and "登录后阅读" in blocked_member
        status, enabled = request(f"/admin/members/{member_id}/status", {
            "_csrf": csrf(disabled), "status": "active"
        })
        assert status == 200 and "会员状态已更新" in enabled and "正常" in enabled
        status, member_login = request("/member/login", client=guest)
        status, old_password = request("/member/login", {
            "_csrf": csrf(member_login), "email": "reader@example.com", "password": "reader-test-password-123"
        }, client=guest)
        assert status == 422
        status, relogged = request("/member/login", {
            "_csrf": csrf(old_password), "email": "reader@example.com", "password": "reader-new-password-123"
        }, client=guest)
        assert status == 200 and "读者" in relogged

        status, role_form = request("/admin/roles/new")
        status, roles = request("/admin/roles", {
            "_csrf": csrf(role_form), "name": "审阅员", "slug": "reviewer", "permissions[]": "content.view"
        })
        assert status == 200 and "审阅员" in roles
        role_id = re.search(r'审阅员.*?/admin/roles/(\d+)/edit', roles, re.S).group(1)
        status, admins = request("/admin/admins")
        status, admin_list = request("/admin/admins", {
            "_csrf": csrf(admins), "username": "reviewer", "password": "reviewer-password-123", "role_id": role_id
        })
        assert status == 200 and "reviewer" in admin_list
        reviewer = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        status, reviewer_login = request("/login", client=reviewer)
        status, reviewer_dashboard = request("/login", {
            "_csrf": csrf(reviewer_login), "username": "reviewer", "password": "reviewer-password-123"
        }, client=reviewer)
        assert status == 200 and "审阅员" in reviewer_dashboard
        assert request("/admin/posts", client=reviewer)[0] == 200
        assert request("/admin/posts/bulk", {
            "_csrf": csrf(reviewer_dashboard), "post_ids[]": "1", "action": "delete"
        }, client=reviewer)[0] == 403
        assert request("/admin/tags", client=reviewer)[0] == 403
        assert request("/admin/models", client=reviewer)[0] == 403
        assert request("/admin/members", client=reviewer)[0] == 403
        assert request("/admin/slides", client=reviewer)[0] == 403
        assert request("/admin/company", client=reviewer)[0] == 403
        assert request("/admin/posts", {"_csrf": csrf(reviewer_dashboard), "title": "非法"}, client=reviewer)[0] == 403
        status, edit_role = request(f"/admin/roles/{role_id}/edit")
        status, changed_role = request(f"/admin/roles/{role_id}/edit", {
            "_csrf": csrf(edit_role), "name": "审阅员", "slug": "reviewer",
            "permissions[]": ["content.view", "members.manage"]
        })
        assert status == 200 and "角色权限已保存" in changed_role
        assert request("/admin/members", client=reviewer)[0] == 200
        reviewer_id = re.search(r'<td>reviewer</td>.*?/admin/admins/(\d+)/edit', admin_list, re.S).group(1)
        status, admin_edit = request(f"/admin/admins/{reviewer_id}/edit")
        status, updated_admin = request(f"/admin/admins/{reviewer_id}/edit", {
            "_csrf": csrf(admin_edit), "role_id": role_id, "new_password": "reviewer-new-password-123"
        })
        assert status == 200 and "后台账号已更新" in updated_admin
        status, wrong_host = request("/", host="other.example.com")
        assert status == 421 and "域名与站点配置不匹配" in wrong_host
        status, article = request("/post/first-post")
        assert status == 200 and "&lt;script&gt;" in article and "<script>alert(1)</script>" not in article
        status, category = request("/category/news")
        assert status == 200 and "第一篇文章" in category
        status, protected = request("/config/app.php")
        assert "password" not in protected and "license_public_keys" not in protected

        status, sort_list = request("/admin/posts")
        assert status == 200 and 'name="sort_order[1]"' in sort_list and 'value="sorting"' in sort_list
        status, sorted_list = request("/admin/posts/bulk", {
            "_csrf": csrf(sort_list), "action": "sorting", "sort_order[1]": "7"
        })
        assert status == 200 and "已保存 1 篇文章的排序" in sorted_list and 'name="sort_order[1]" min="0" max="1000000" required value="7"' in sorted_list
        status, reset_sort = request("/admin/posts/bulk", {
            "_csrf": csrf(sorted_list), "action": "sorting", "sort_order[1]": "255"
        })
        assert status == 200 and "已保存 1 篇文章的排序" in reset_sort
        status, scheduled_form = request("/admin/posts/new")
        assert 'name="published_at"' in scheduled_form and 'name="sort_order"' in scheduled_form
        status, scheduled_list = request("/admin/posts", {
            "_csrf": csrf(scheduled_form), "title": "定时测试文章", "slug": "scheduled-smoke",
            "category_id": "1", "body": "稍后公开", "status": "published", "visibility": "public",
            "published_at": "2099-01-01T00:00", "sort_order": "1"
        })
        assert status == 200 and "文章已保存" in scheduled_list and "待发布" in scheduled_list
        assert request("/post/scheduled-smoke")[0] == 404
        assert "定时测试文章" not in request("/")[1]
        scheduled_row = next(row for row in re.findall(r"<tr>(.*?)</tr>", scheduled_list, re.S) if "scheduled-smoke" in row)
        scheduled_id = re.search(r'name="post_ids\[\]" value="(\d+)"', scheduled_row).group(1)
        status, scheduled_edit = request(f"/admin/posts/{scheduled_id}/edit")
        assert status == 200 and "查看已发布页面" not in scheduled_edit
        status, published_list = request(f"/admin/posts/{scheduled_id}/edit", {
            "_csrf": csrf(scheduled_edit), "title": "定时测试文章", "slug": "scheduled-smoke",
            "category_id": "1", "body": "已经公开", "status": "published", "visibility": "public",
            "published_at": "2020-01-01T00:00", "sort_order": "1"
        })
        assert status == 200 and "文章已保存" in published_list
        assert "已经公开" in request("/post/scheduled-smoke")[1]
        status, deleted_scheduled = request(f"/admin/posts/{scheduled_id}/delete", {"_csrf": csrf(published_list)})
        assert status == 200 and "文章已删除" in deleted_scheduled

        status, model_form = request("/admin/models/new")
        assert status == 200 and "模型标识" in model_form
        status, default_single_form = request("/admin/models/2/edit")
        status, default_single_saved = request("/admin/models/2/edit", {
            "_csrf": csrf(default_single_form), "name": "单页模型", "slug": "__system_single",
            "content_template": "",
        })
        assert status == 200 and "内容模型已保存" in default_single_saved
        status, model_list = request("/admin/models", {
            "_csrf": csrf(model_form), "name": "产品模型", "slug": "product", "type": "list",
        })
        assert status == 200 and "产品模型" in model_list
        model_id = re.search(r'产品模型.*?/admin/models/(\d+)/edit', model_list, re.S).group(1)
        status, invalid_model = request(f"/admin/models/{model_id}/edit", {
            "_csrf": csrf(model_list), "name": "产品模型", "slug": "product", "type": "list",
            "list_template": "../private.html", "content_template": "detail-simple.html",
        })
        assert status == 422 and "请选择现有的 HTML 模板文件" in invalid_model
        status, field_form = request(f"/admin/models/{model_id}/fields/new")
        assert status == 200 and "新建扩展字段" in field_form
        status, model_edit = request(f"/admin/models/{model_id}/fields/new", {
            "_csrf": csrf(field_form), "name": "ext_color", "label": "颜色",
            "field_type": "select", "options_text": "红色,蓝色", "is_required": "1", "sort_order": "1"
        })
        assert status == 200 and "扩展字段已保存" in model_edit
        field_id = re.search(r'/fields/(\d+)/edit', model_edit).group(1)
        status, size_form = request(f"/admin/models/{model_id}/fields/new")
        status, size_list = request(f"/admin/models/{model_id}/fields/new", {
            "_csrf": csrf(size_form), "name": "ext_size", "label": "尺码",
            "field_type": "multi", "options_text": "S,XS", "sort_order": "2",
        })
        assert status == 200 and "扩展字段已保存" in size_list
        size_id = re.search(r'ext_size.*?/fields/(\d+)/edit', size_list, re.S).group(1)
        status, category_edit = request("/admin/categories/1/edit")
        assert status == 200
        status, category_list = request("/admin/categories/1/edit", {
            "_csrf": csrf(category_edit), "name": "新闻", "slug": "news",
            "sort_order": "1", "model_id": model_id
        })
        assert status == 200 and "产品模型" in category_list
        status, invalid_category_template = request("/admin/categories/1/edit", {
            "_csrf": csrf(category_list), "name": "新闻", "slug": "news", "sort_order": "1",
            "model_id": model_id, "list_template": "missing.html",
        })
        assert status == 422 and "请选择现有的 HTML 模板文件" in invalid_category_template
        status, post_edit = request("/admin/posts/1/edit")
        assert status == 200 and 'name="extra[' + field_id + ']"' in post_edit
        update = {
            "_csrf": csrf(post_edit), "title": "第一篇文章", "slug": "first-post",
            "category_id": "1", "body": "产品正文", "status": "published",
            "visibility": "public", "tag_ids[]": tag_id, "cover_media_id": image_id,
            "subtitle": "重要副标题", "author": "编辑部", "source": "本站",
            "title_color": "#176c73", "seo_keywords": "内容,专题",
            "seo_description": "文章摘要内容", "is_top": "1",
            "is_recommended": "1", "is_headline": "0",
            f"extra[{field_id}]": "红色", f"extra[{size_id}][]": "S"
        }
        status, updated_post = request("/admin/posts/1/edit", update)
        assert status == 200 and "文章已保存" in updated_post
        status, product_article = request("/post/first-post")
        assert status == 200 and "颜色" in product_article and "红色" in product_article
        status, filtered_products = request("/category/news?ext_color=%E7%BA%A2%E8%89%B2&ext_size=S")
        assert status == 200 and "筛选内容" in filtered_products and "第一篇文章" in filtered_products
        assert 'href="/category/news?ext_size=S"' in filtered_products
        assert 'ext_size=S&amp;ext_color=%E7%BA%A2%E8%89%B2' in filtered_products
        assert 'aria-current="true">红色</a>' in filtered_products and "{pboot:select" not in filtered_products
        assert "第一篇文章" not in request("/category/news?ext_color=%E7%BA%A2%E8%89%B2&ext_size=XS")[1]
        assert "第一篇文章" not in request("/category/news?ext_color=%E8%93%9D%E8%89%B2&ext_size=S")[1]
        invalid_filter_page = request("/category/news?ext_color=%E7%BB%BF%E8%89%B2")[1]
        assert "筛选选项无效" in invalid_filter_page and "第一篇文章" not in invalid_filter_page
        assert "第一篇文章" in request("/category/news?unknown=value")[1]
        assert "重要副标题" in product_article and "作者：编辑部" in product_article and "来源：本站" in product_article
        assert 'name="keywords" content="内容,专题"' in product_article
        assert 'name="description" content="文章摘要内容"' in product_article
        assert 'style="color:#176c73"' in product_article
        assert "第一篇文章" in request("/admin/posts?feature=top")[1]
        assert "会员专享" not in request("/admin/posts?feature=top")[1]
        assert "第一篇文章" in request("/search?q=%E7%BC%96%E8%BE%91%E9%83%A8")[1]
        assert '<h3 class="h5 mb-0"><a' in request("/search?keyword=%E7%AC%AC%E4%B8%80%E7%AF%87%E6%96%87%E7%AB%A0")[1]
        assert "第一篇文章" not in request("/search?keyword=%E7%BA%A2%E8%89%B2")[1]
        status, advanced_search = request("/search?keyword=%E7%BA%A2%E8%89%B2&field=ext_color&ext_size=S&scode=1")
        assert status == 200 and "第一篇文章" in advanced_search and 'name="ext_size"' in advanced_search
        assert "第一篇文章" not in request("/search?keyword=%E7%BA%A2%E8%89%B2&field=ext_color&ext_size=XS&scode=1")[1]
        assert "第一篇文章" in request("/search?title=%E7%AC%AC%E4%B8%80%E7%AF%87&author=%E7%BC%96%E8%BE%91%E9%83%A8")[1]
        invalid_search = request("/search?scode=999999")[1]
        assert "搜索条件无效" in invalid_search and "第一篇文章" not in invalid_search
        anonymous = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        assert '<h3 class="h5 mb-0"><a' not in request("/search?keyword=%E4%BC%9A%E5%91%98%E4%B8%93%E4%BA%AB", client=anonymous)[1]
        status, invalid_color = request("/admin/posts/1/edit", update | {"title_color": "red<script>"})
        assert status == 422 and "标题颜色须为" in invalid_color
        assert 'style="color:#176c73"' in request("/post/first-post")[1]
        status, field_search = request("/search?q=%E7%BA%A2%E8%89%B2")
        assert status == 200 and "第一篇文章" in field_search
        status, admin_field_search = request("/admin/posts?q=%E7%BA%A2%E8%89%B2")
        assert status == 200 and "第一篇文章" in admin_field_search
        status, invalid_field = request("/admin/posts/1/edit", update | {f"extra[{field_id}]": "绿色"})
        assert status == 422 and "未定义的选项" in invalid_field
        assert "红色" in request("/post/first-post")[1]
        status, model_template_form = request(f"/admin/models/{model_id}/edit")
        status, model_template_saved = request(f"/admin/models/{model_id}/edit", {
            "_csrf": csrf(model_template_form), "name": "产品模型", "slug": "product", "type": "list",
            "list_template": "list-simple.html", "content_template": "detail-simple.html",
        })
        assert status == 200 and "内容模型已保存" in model_template_saved
        inherited_list = request("/category/news")[1]
        assert "内容栏目" in inherited_list and "第一篇文章" in inherited_list and "{pboot:list}" not in inherited_list
        model_detail = request("/post/first-post")[1]
        assert "新闻 ·" in model_detail and "产品正文" in model_detail and "{content:" not in model_detail
        status, fields_model_form = request(f"/admin/models/{model_id}/edit")
        status, fields_model_saved = request(f"/admin/models/{model_id}/edit", {
            "_csrf": csrf(fields_model_form), "name": "产品模型", "slug": "product", "type": "list",
            "list_template": "list-fields.html", "content_template": "detail-fields.html",
        })
        assert status == 200 and "内容模型已保存" in fields_model_saved
        fields_list = request("/category/news?ext_color=%E7%BA%A2%E8%89%B2")[1]
        assert "第一篇文章" in fields_list and "红色" in fields_list and 'class="badge badge-light mr-1">S</span>' in fields_list
        assert "会员专享" not in fields_list and "{pboot:checkbox" not in fields_list and "[list:ext_color]" not in fields_list
        fields_detail = request("/post/first-post")[1]
        assert "产品正文" in fields_detail and "红色" in fields_detail and 'class="badge badge-light mr-1">S</span>' in fields_detail
        if os.environ.get("FANGDAYS_PREVIEW_FIELDS") == "1":
            print(f"Field template preview running at {base}/category/news?ext_color=%E7%BA%A2%E8%89%B2", flush=True)
            input("Press Enter to resume smoke test: ")
        status, simple_model_form = request(f"/admin/models/{model_id}/edit")
        status, simple_model_saved = request(f"/admin/models/{model_id}/edit", {
            "_csrf": csrf(simple_model_form), "name": "产品模型", "slug": "product", "type": "list",
            "list_template": "list-simple.html", "content_template": "detail-simple.html",
        })
        assert status == 200 and "内容模型已保存" in simple_model_saved
        status, override_form = request("/admin/categories/1/edit")
        status, override_saved = request("/admin/categories/1/edit", {
            "_csrf": csrf(override_form), "name": "新闻", "slug": "news", "sort_order": "1",
            "model_id": model_id, "content_template": "single-simple.html",
        })
        assert status == 200 and "栏目已保存" in override_saved
        overridden_detail = request("/post/first-post")[1]
        assert 'class="site-kicker">新闻</p>' in overridden_detail and "新闻 ·" not in overridden_detail
        (site / "templates/site/full-smoke.html").write_text(
            '<!doctype html><html lang="zh-CN"><head><title>{pboot:pagetitle}</title></head>'
            '<body><h1>{content:title}</h1><div>{content:content}</div></body></html>', encoding="utf-8"
        )
        status, full_template_saved = request("/admin/categories/1/edit", {
            "_csrf": csrf(override_saved), "name": "新闻", "slug": "news", "sort_order": "1",
            "model_id": model_id, "content_template": "full-smoke.html",
        })
        assert status == 200 and "栏目已保存" in full_template_saved
        full_detail = request("/post/first-post")[1]
        assert full_detail.lower().count("<html") == 1 and "第一篇文章" in full_detail and "产品正文" in full_detail and "site-header" not in full_detail
        status, restored_template = request("/admin/categories/1/edit", {
            "_csrf": csrf(full_template_saved), "name": "新闻", "slug": "news", "sort_order": "1",
            "model_id": model_id, "content_template": "",
        })
        assert status == 200 and "新闻 ·" in request("/post/first-post")[1]
        status, single_model_form = request("/admin/models/new")
        status, single_model_created = request("/admin/models", {
            "_csrf": csrf(single_model_form), "name": "产品单页模型", "slug": "product-single-model",
            "type": "single", "content_template": "single-simple.html",
        })
        assert status == 200 and "产品单页模型" in single_model_created
        single_content_model_id = re.search(r'产品单页模型.*?/admin/models/(\d+)/edit', single_model_created, re.S).group(1)
        status, single_field_form = request(f"/admin/models/{single_content_model_id}/fields/new")
        status, single_field_saved = request(f"/admin/models/{single_content_model_id}/fields/new", {
            "_csrf": csrf(single_field_form), "name": "ext_color", "label": "颜色",
            "field_type": "select", "options_text": "红色,蓝色", "is_required": "1", "sort_order": "1",
        })
        assert status == 200 and "扩展字段已保存" in single_field_saved
        single_field_id = re.search(r'/fields/(\d+)/edit', single_field_saved).group(1)
        status, single_model_form = request("/admin/categories/new")
        status, single_model_list = request("/admin/categories", {
            "_csrf": csrf(single_model_form), "name": "产品单页", "slug": "product-single",
            "sort_order": "4", "model_id": single_content_model_id, "type": "single",
        })
        assert status == 200 and "产品单页" in single_model_list
        single_model_id = re.search(r'产品单页.*?/admin/categories/(\d+)/edit', single_model_list, re.S).group(1)
        status, single_model_edit = request(f"/admin/categories/{single_model_id}/single")
        assert status == 200 and f'name="extra[{single_field_id}]"' in single_model_edit
        status, saved_model_single = request(f"/admin/categories/{single_model_id}/single", {
            "_csrf": csrf(single_model_edit), "title": "产品说明", "body": "原正文",
            "status": "published", f"extra[{single_field_id}]": "蓝色",
        })
        assert status == 200 and "单页栏目内容已保存" in saved_model_single
        assert "蓝色" in request("/category/product-single")[1]
        status, invalid_model_single = request(f"/admin/categories/{single_model_id}/single", {
            "_csrf": csrf(saved_model_single), "title": "错误标题", "body": "错误正文",
            "status": "published", f"extra[{single_field_id}]": "绿色",
        })
        assert status == 422 and "未定义的选项" in invalid_model_single
        status, malformed_model_single = request(f"/admin/categories/{single_model_id}/single", {
            "_csrf": csrf(saved_model_single), "title": "错误标题", "body": "错误正文",
            "status": "published", f"extra[{single_field_id}][]": "蓝色",
        })
        assert status == 422 and "内容无效" in malformed_model_single
        public_model_single = request("/category/product-single")[1]
        assert "原正文" in public_model_single and "产品说明" in public_model_single and "错误正文" not in public_model_single
        status, deleted_model_single = request(f"/admin/categories/{single_model_id}/delete", {"_csrf": csrf(saved_model_single)})
        assert status == 200 and request("/category/product-single")[0] == 404
        status, field_edit = request(f"/admin/models/{model_id}/fields/{field_id}/edit")
        assert status == 200 and "颜色" in field_edit
        status, renamed_field = request(f"/admin/models/{model_id}/fields/{field_id}/edit", {
            "_csrf": csrf(field_edit), "name": "ext_color", "label": "商品颜色",
            "field_type": "select", "options_text": "红色,蓝色", "is_required": "1", "sort_order": "1"
        })
        assert status == 200 and "商品颜色" in renamed_field
        status, deleted_field = request(f"/admin/models/{model_id}/fields/{field_id}/delete", {"_csrf": csrf(renamed_field)})
        assert status == 200 and "扩展字段已删除" in deleted_field
        status, category_edit = request("/admin/categories/1/edit")
        status, rebound_category = request("/admin/categories/1/edit", {
            "_csrf": csrf(category_edit), "name": "新闻", "slug": "news", "sort_order": "1", "model_id": "1"
        })
        assert status == 200 and "栏目已保存" in rebound_category
        status, model_list = request("/admin/models")
        status, deleted_model = request(f"/admin/models/{model_id}/delete", {"_csrf": csrf(model_list)})
        assert status == 200 and "内容模型已删除" in deleted_model

        status, rich_form = request("/admin/posts/1/edit")
        assert status == 200 and 'name="body_format"' in rich_form
        editor_upload = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"_csrf\"\r\n\r\n{csrf(rich_form)}\r\n"
                         f"--{boundary}\r\nContent-Disposition: form-data; name=\"image\"; filename=\"body.png\"\r\nContent-Type: image/png\r\n\r\n").encode() + png + f"\r\n--{boundary}--\r\n".encode()
        editor_request = urllib.request.Request(base + "/admin/media/editor-upload", data=editor_upload, headers={"Content-Type": f"multipart/form-data; boundary={boundary}"})
        with opener.open(editor_request, timeout=5) as response:
            editor_image = json.loads(response.read().decode("utf-8"))
        assert editor_image["path"].startswith("/uploads/")
        status, rich_saved = request("/admin/posts/1/edit", {
            "_csrf": csrf(rich_form), "title": "第一篇文章", "slug": "first-post", "category_id": "1",
            "body": f'<p><strong>富文本正文</strong><img src="{editor_image["path"]}" onerror="alert(1)"><a href="javascript:alert(1)">链接</a><script>alert(1)</script></p>',
            "body_format": "html", "status": "published", "visibility": "public", "cover_media_id": image_id,
            "gallery_media_ids[]": [image_id, editor_image["id"]], "gallery_titles[]": ["封面图", "正文图"],
            "tag_ids[]": tag_id,
            "seo_description": "",
        })
        assert status == 200 and "文章已保存" in rich_saved
        status, rich_article = request("/post/first-post")
        assert status == 200 and "<strong>富文本正文</strong>" in rich_article and editor_image["path"] in rich_article
        assert 'aria-label="文章多图"' in rich_article and "封面图" in rich_article and "正文图" in rich_article
        status, media_category_form = request("/admin/categories/1/edit")
        status, media_category_saved = request("/admin/categories/1/edit", {
            "_csrf": csrf(media_category_form), "name": "新闻", "slug": "news", "sort_order": "1",
            "model_id": "1", "list_template": "list-media.html", "content_template": "detail-media.html",
        })
        assert status == 200 and "栏目已保存" in media_category_saved
        media_list = request("/category/news")[1]
        assert 'alt="封面图"' in media_list and "正文图" not in media_list and 'href="/tag/topic"' in media_list
        assert "会员专享" not in media_list and "{pboot:pics" not in media_list
        media_detail = request("/post/first-post")[1]
        assert "封面图" in media_detail and "正文图" in media_detail and 'href="/tag/topic"' in media_detail
        if os.environ.get("FANGDAYS_PREVIEW_MEDIA") == "1":
            print(f"Media template preview running at {base}/category/news", flush=True)
            input("Press Enter to resume smoke test: ")
        status, media_category_restored = request("/admin/categories/1/edit", {
            "_csrf": csrf(media_category_saved), "name": "新闻", "slug": "news", "sort_order": "1", "model_id": "1",
            "list_template": "", "content_template": "",
        })
        assert status == 200 and "栏目已保存" in media_category_restored
        status, duplicate_gallery = request("/admin/posts/1/edit", {
            "_csrf": csrf(rich_form), "title": "第一篇文章", "slug": "first-post", "category_id": "1",
            "body": "正文", "status": "published", "visibility": "public",
            "gallery_media_ids[]": [image_id, image_id], "gallery_titles[]": ["A", "B"],
        })
        assert status == 422 and "多图包含无效、重复" in duplicate_gallery
        assert "正文图" in request("/post/first-post")[1]
        attachment_fields = {
            "_csrf": csrf(rich_form), "title": "第一篇文章", "slug": "first-post", "category_id": "1",
            "body": f'<p><strong>富文本正文</strong><img src="{editor_image["path"]}"></p>', "body_format": "html", "status": "published", "visibility": "public", "gallery_media_ids[]": image_id,
            "gallery_titles[]": "图一",
        }
        pdf = b"%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n"
        parts = b"".join((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode() for key, value in attachment_fields.items())
        parts += (f'--{boundary}\r\nContent-Disposition: form-data; name="attachment"; filename="guide.pdf"\r\nContent-Type: application/pdf\r\n\r\n').encode() + pdf + f'\r\n--{boundary}--\r\n'.encode()
        with opener.open(urllib.request.Request(base + "/admin/posts/1/edit", data=parts, headers={"Content-Type": f"multipart/form-data; boundary={boundary}"}), timeout=5) as response:
            assert "文章已保存" in response.read().decode("utf-8")
        assert "下载附件：guide.pdf" in request("/post/first-post")[1]
        with opener.open(base + "/post/first-post/attachment", timeout=5) as response:
            assert response.read() == pdf and response.headers["X-Content-Type-Options"] == "nosniff"
        bad_parts = parts.replace(b'filename="guide.pdf"', b'filename="danger.php"').replace(pdf, b'<?php echo 1;')
        try:
            opener.open(urllib.request.Request(base + "/admin/posts/1/edit", data=bad_parts, headers={"Content-Type": f"multipart/form-data; boundary={boundary}"}), timeout=5)
            raise AssertionError("Executable attachment was accepted")
        except urllib.error.HTTPError as error:
            assert error.code == 422 and "附件格式无效" in error.read().decode("utf-8")
        with opener.open(base + "/post/first-post/attachment", timeout=5) as response:
            assert response.read() == pdf
        status, restricted = request("/admin/posts/1/edit", {
            **attachment_fields, "visibility": "members", "cover_media_id": image_id,
        })
        assert status == 200 and "文章已保存" in restricted
        try:
            urllib.request.urlopen(base + "/post/first-post/attachment", timeout=5)
            raise AssertionError("Member-only attachment was public")
        except urllib.error.HTTPError as error:
            assert error.code == 404
        status, restored = request("/admin/posts/1/edit", {**attachment_fields, "cover_media_id": image_id})
        assert status == 200 and "文章已保存" in restored
        status, removed_attachment = request("/admin/posts/1/edit", {**attachment_fields, "cover_media_id": image_id, "remove_attachment": "1"})
        assert status == 200 and "文章已保存" in removed_attachment
        assert "下载附件：guide.pdf" not in request("/post/first-post")[1]
        assert request("/post/first-post/attachment")[0] == 404
        assert "onerror" not in rich_article and "javascript:alert(1)" not in rich_article and "<script>alert(1)</script>" not in rich_article
        status, rich_home = request("/")
        assert status == 200 and "富文本正文" in rich_home and "&lt;strong&gt;" not in rich_home
        status, body_image_in_use = request(f'/admin/media/{editor_image["id"]}/delete', {"_csrf": csrf(rich_form)})
        assert status == 200 and "图片正被文章正文使用" in body_image_in_use
        status, editor_forbidden = request("/admin/media/editor-upload", {"_csrf": csrf(reviewer_dashboard)}, client=reviewer)
        assert status == 403 and "无权访问" in editor_forbidden

        status, child_form = request("/admin/categories/new")
        status, child_list = request("/admin/categories", {
            "_csrf": csrf(child_form), "name": "公司新闻", "slug": "company-news",
            "sort_order": "2", "parent_id": "1", "model_id": "1", "thumbnail_media_id": image_id,
        })
        assert status == 200 and "公司新闻" in child_list
        child_id = re.search(r'公司新闻.*?/admin/categories/(\d+)/edit', child_list, re.S).group(1)
        assert 'class="site-child-thumbnail"' in request("/category/news")[1]
        status, grandchild_form = request("/admin/categories/new")
        status, grandchild_list = request("/admin/categories", {
            "_csrf": csrf(grandchild_form), "name": "行业动态", "slug": "industry-news",
            "sort_order": "3", "parent_id": child_id, "model_id": "1"
        })
        assert status == 200 and "行业动态" in grandchild_list
        grandchild_id = re.search(r'行业动态.*?/admin/categories/(\d+)/edit', grandchild_list, re.S).group(1)
        status, nested_form = request("/admin/posts/new")
        status, nested_list = request("/admin/posts", {
            "_csrf": csrf(nested_form), "title": "层级文章", "slug": "nested-post",
            "category_id": grandchild_id, "body": "子栏目正文", "status": "published", "visibility": "public"
        })
        assert status == 200 and "层级文章" in nested_list
        home_with_priority = request("/")[1]
        assert home_with_priority.index("第一篇文章") < home_with_priority.index("层级文章")
        status, parent_page = request("/category/news")
        assert status == 200 and "层级文章" in parent_page and "/category/company-news" in parent_page
        status, child_page = request("/category/company-news")
        assert status == 200 and "层级文章" in child_page and "/category/industry-news" in child_page
        status, grandchild_page = request("/category/industry-news")
        assert status == 200 and "层级文章" in grandchild_page and "公司新闻" in grandchild_page
        assert "层级文章" in request("/admin/posts?category_id=1&q=%E5%B1%82%E7%BA%A7")[1]
        status, parent_edit = request("/admin/categories/1/edit")
        assert status == 200 and f'<option value="{child_id}"' not in parent_edit
        status, cycle = request("/admin/categories/1/edit", {
            "_csrf": csrf(parent_edit), "name": "新闻", "slug": "news", "sort_order": "1",
            "parent_id": grandchild_id, "model_id": "1"
        })
        assert status == 422 and "循环层级" in cycle
        status, blocked_delete = request("/admin/categories/1/delete", {"_csrf": csrf(parent_edit)})
        assert status == 200 and "还有子栏目" in blocked_delete

        status, blocked_type = request("/admin/categories/1/edit", {
            "_csrf": csrf(parent_edit), "name": "新闻", "slug": "news", "sort_order": "1",
            "model_id": "1", "type": "single",
        })
        assert status == 422 and "直属文章" in blocked_type
        assert "第一篇文章" in request("/category/news")[1]

        status, transfer_list = request("/admin/posts")
        assert status == 200 and 'name="target_category_id"' in transfer_list and 'value="copy"' in transfer_list and 'value="move"' in transfer_list
        status, missing_target = request("/admin/posts/bulk", {
            "_csrf": csrf(transfer_list), "post_ids[]": ["1"], "action": "copy", "target_category_id": ""
        })
        assert status == 200 and "请选择目标列表栏目" in missing_target
        status, copied_list = request("/admin/posts/bulk", {
            "_csrf": csrf(transfer_list), "post_ids[]": ["1"], "action": "copy", "target_category_id": child_id
        })
        assert status == 200 and "已复制 1 篇文章" in copied_list and "first-post-copy" in copied_list
        assert "富文本正文" in request("/post/first-post-copy")[1]
        copy_row = next(row for row in re.findall(r"<tr>(.*?)</tr>", copied_list, re.S) if "first-post-copy" in row)
        copy_id = re.search(r'name="post_ids\[\]" value="(\d+)"', copy_row).group(1)
        status, moved_list = request("/admin/posts/bulk", {
            "_csrf": csrf(copied_list), "post_ids[]": ["1"], "action": "move", "target_category_id": child_id
        })
        assert status == 200 and "已移动 1 篇文章" in moved_list
        assert "第一篇文章" in request("/category/company-news")[1]
        status, restored_list = request("/admin/posts/bulk", {
            "_csrf": csrf(moved_list), "post_ids[]": ["1"], "action": "move", "target_category_id": "1"
        })
        assert status == 200 and "已移动 1 篇文章" in restored_list
        status, removed_copy = request(f"/admin/posts/{copy_id}/delete", {"_csrf": csrf(restored_list)})
        assert status == 200 and "文章已删除" in removed_copy

        status, single_form = request("/admin/categories/new")
        status, single_list = request("/admin/categories", {
            "_csrf": csrf(single_form), "name": "公司介绍", "slug": "company-intro",
            "sort_order": "5", "model_id": "1", "type": "single",
            "seo_title": "公司介绍专属标题", "seo_keywords": "公司,介绍",
            "seo_description": "这里是栏目 SEO 描述",
        })
        assert status == 200 and "公司介绍" in single_list
        single_id = re.search(r'公司介绍.*?/admin/categories/(\d+)/edit', single_list, re.S).group(1)
        assert request("/category/company-intro")[0] == 404
        assert "company-intro" not in request("/")[1]
        status, single_edit = request(f"/admin/categories/{single_id}/single")
        assert status == 200 and "编辑此栏目的单页正文" in single_edit and 'id="single-editor"' in single_edit
        assert 'enctype="multipart/form-data"' in single_edit and 'name="cover_media_id"' in single_edit and 'id="gallery-add"' in single_edit and 'name="attachment"' in single_edit
        assert request(f"/admin/categories/{single_id}/single", client=reviewer)[0] == 403
        status, saved_single = request(f"/admin/categories/{single_id}/single", {
            "_csrf": csrf(single_edit), "title": "我们的公司",
            "body": "单页内容 <script>alert(1)</script>", "status": "published",
            "cover_media_id": image_id, "gallery_media_ids[]": [image_id, editor_image["id"]],
            "gallery_titles[]": ["单页封面", "单页内景"],
        })
        assert status == 200 and "单页栏目内容已保存" in saved_single
        status, public_single = request("/category/company-intro")
        assert status == 200 and "我们的公司" in public_single
        assert 'aria-label="单页多图"' in public_single and "单页封面" in public_single and "单页内景" in public_single
        assert f'src="{editor_image["path"]}"' in public_single
        status, duplicate_single_gallery = request(f"/admin/categories/{single_id}/single", {
            "_csrf": csrf(saved_single), "title": "我们的公司", "body": "不能覆盖", "status": "published",
            "gallery_media_ids[]": [image_id, image_id], "gallery_titles[]": ["一", "二"],
        })
        assert status == 422 and "多图包含无效、重复" in duplicate_single_gallery
        assert "不能覆盖" not in request("/category/company-intro")[1]
        single_parts = b"".join((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode() for key, value in {
            "_csrf": csrf(saved_single), "title": "我们的公司", "body": "附件内容", "status": "published",
            "cover_media_id": image_id, "gallery_media_ids[]": editor_image["id"], "gallery_titles[]": "单页内景",
        }.items())
        single_parts += (f'--{boundary}\r\nContent-Disposition: form-data; name="attachment"; filename="company.pdf"\r\nContent-Type: application/pdf\r\n\r\n').encode() + pdf + f'\r\n--{boundary}--\r\n'.encode()
        with opener.open(urllib.request.Request(base + f"/admin/categories/{single_id}/single", data=single_parts, headers={"Content-Type": f"multipart/form-data; boundary={boundary}"}), timeout=5) as response:
            assert "单页栏目内容已保存" in response.read().decode("utf-8")
        assert "下载附件：company.pdf" in request("/category/company-intro")[1]
        with opener.open(base + "/category/company-intro/attachment", timeout=5) as response:
            assert response.read() == pdf and response.headers["X-Content-Type-Options"] == "nosniff"
        invalid_single_upload = single_parts.replace(b'filename="company.pdf"', b'filename="danger.php"').replace(pdf, b'<?php echo 1;')
        try:
            opener.open(urllib.request.Request(base + f"/admin/categories/{single_id}/single", data=invalid_single_upload, headers={"Content-Type": f"multipart/form-data; boundary={boundary}"}), timeout=5)
            raise AssertionError("Executable single-page attachment was accepted")
        except urllib.error.HTTPError as error:
            assert error.code == 422 and "附件格式无效" in error.read().decode("utf-8")
        with opener.open(base + "/category/company-intro/attachment", timeout=5) as response:
            assert response.read() == pdf
        assert "&lt;script&gt;" in public_single and "<script>alert(1)</script>" not in public_single
        assert '<title>公司介绍专属标题</title>' in public_single
        assert 'name="keywords" content="公司,介绍"' in public_single
        assert 'name="description" content="这里是栏目 SEO 描述"' in public_single
        assert 'href="/category/company-intro"' in request("/")[1]
        status, draft_single = request(f"/admin/categories/{single_id}/single", {
            "_csrf": csrf(saved_single), "title": "我们的公司", "body": "暂存内容", "status": "draft",
        })
        assert status == 200 and request("/category/company-intro")[0] == 404
        assert request("/category/company-intro/attachment")[0] == 404
        assert "company-intro" not in request("/")[1]
        status, republished_single = request(f"/admin/categories/{single_id}/single", {
            "_csrf": csrf(draft_single), "title": "我们的公司", "body": "重新发布", "status": "published",
        })
        assert status == 200 and "重新发布" in request("/category/company-intro")[1]
        with opener.open(base + "/category/company-intro/attachment", timeout=5) as response:
            assert response.read() == pdf
        status, rich_single = request(f"/admin/categories/{single_id}/single", {
            "_csrf": csrf(republished_single), "title": "我们的公司",
            "body": "<p><strong>栏目富文本</strong><script>alert(1)</script></p>",
            "body_format": "html", "status": "published",
        })
        assert status == 200
        single_html = request("/category/company-intro")[1]
        assert "<strong>栏目富文本</strong>" in single_html and "<script>alert(1)</script>" not in single_html
        status, removed_single_attachment = request(f"/admin/categories/{single_id}/single", {
            "_csrf": csrf(rich_single), "title": "我们的公司", "body": "栏目富文本", "status": "published", "remove_attachment": "1",
        })
        assert status == 200 and "单页栏目内容已保存" in removed_single_attachment
        assert request("/category/company-intro/attachment")[0] == 404

        status, link_form = request("/admin/categories/new")
        status, invalid_link = request("/admin/categories", {
            "_csrf": csrf(link_form), "name": "坏链接", "slug": "bad-link", "sort_order": "6",
            "model_id": "1", "type": "link", "outlink": "javascript:alert(1)",
        })
        assert status == 422 and "跳转链接须为" in invalid_link
        assert "bad-link" not in request("/")[1]
        status, link_list = request("/admin/categories", {
            "_csrf": csrf(link_form), "name": "资源入口", "slug": "resources", "sort_order": "6",
            "model_id": "1", "type": "link", "outlink": "/search",
        })
        assert status == 200 and "资源入口" in link_list
        assert 'href="/search">资源入口</a>' in request("/")[1]
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, req, fp, code, msg, headers, newurl):
                return None
        try:
            urllib.request.build_opener(NoRedirect()).open(base + "/category/resources", timeout=5)
            raise AssertionError("Link category did not redirect")
        except urllib.error.HTTPError as error:
            assert error.code == 302 and error.headers["Location"] == "/search"

        status, link_post_form = request("/admin/posts/new")
        status, link_post_list = request("/admin/posts", {
            "_csrf": csrf(link_post_form), "title": "跳转文章", "slug": "redirect-post",
            "category_id": "1", "body": "保留正文", "status": "published",
            "visibility": "public", "outlink": "/search",
        })
        assert status == 200 and "跳转文章" in link_post_list
        assert 'href="/search">跳转文章</a>' in request("/")[1]
        try:
            urllib.request.build_opener(NoRedirect()).open(base + "/post/redirect-post", timeout=5)
            raise AssertionError("Post outlink did not redirect")
        except urllib.error.HTTPError as error:
            assert error.code == 302 and error.headers["Location"] == "/search"
        status, bad_post_form = request("/admin/posts/new")
        status, bad_post_link = request("/admin/posts", {
            "_csrf": csrf(bad_post_form), "title": "不安全文章", "slug": "bad-post-link",
            "body": "正文", "status": "published", "outlink": "javascript:alert(1)",
        })
        assert status == 422 and "文章跳转链接须为" in bad_post_link
        assert "bad-post-link" not in request("/")[1]
        status, member_outlink_form = request("/admin/posts/2/edit")
        status, member_outlink_saved = request("/admin/posts/2/edit", {
            "_csrf": csrf(member_outlink_form), "title": "会员专享", "slug": "members-only",
            "category_id": "1", "body": "内部正文", "status": "published",
            "visibility": "members", "outlink": "/search",
        })
        assert status == 200 and "文章已保存" in member_outlink_saved
        stranger = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        assert request("/post/members-only", client=stranger)[0] == 403

        print("FangdaysCMS HTTP install, admin, content operations, license, and public-site smoke test passed")
        if os.environ.get("FANGDAYS_PREVIEW") == "1":
            print(f"Preview running at {base}", flush=True)
            input("Press Enter to stop preview: ")
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait(timeout=5)
