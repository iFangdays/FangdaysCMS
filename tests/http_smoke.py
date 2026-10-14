"""End-to-end smoke test using a disposable project copy and PHP's local server."""

import http.cookiejar
import base64
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
        assert "schema version 5 is ready" in command(site / "bin/migrate.php")
        status, unlicensed = request("/")
        assert status == 503 and "尚未获得有效授权" in unlicensed

        status, dashboard = request(
            "/login", {"_csrf": csrf(login), "username": "owner", "password": "strong-test-password-123"}
        )
        assert status == 200 and "管理后台" in dashboard and 'id="admin-navigation"' in dashboard

        status, categories = request("/admin/categories/new")
        assert status == 200
        status, category_list = request(
            "/admin/categories", {"_csrf": csrf(categories), "name": "新闻", "slug": "news", "sort_order": "1"}
        )
        assert status == 200 and "新闻" in category_list
        status, bad_category = request("/admin/categories", {"_csrf": csrf(categories), "name": "错误栏目", "slug": "bad", "sort_order": "abc"})
        assert status == 422 and "栏目排序必须是整数" in bad_category

        status, new_tag = request("/admin/tags/new")
        assert status == 200
        status, tag_list = request("/admin/tags", {"_csrf": csrf(new_tag), "name": "专题", "slug": "topic"})
        assert status == 200 and "专题" in tag_list
        tag_id = re.search(r'/admin/tags/(\d+)/edit', tag_list).group(1)

        status, new_post = request("/admin/posts/new")
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
        assert status == 200
        status, page_list = request("/admin/pages", {
            "_csrf": csrf(new_page), "title": "关于我们", "slug": "about", "body": "站点简介",
            "status": "published", "menu_order": "10"
        })
        assert status == 200 and "关于我们" in page_list
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
        status, about = request("/page/about")
        assert status == 200 and "站点简介" in about
        assert request("/assets/theme/bootstrap.min.css")[0] == 200
        assert request("/assets/theme/aoyun.css")[0] == 200
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
        assert request("/admin/members", client=reviewer)[0] == 403
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

        print("FangdaysCMS HTTP install, admin, license, and public-site smoke test passed")
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
