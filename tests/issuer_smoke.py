#!/usr/bin/env python3
"""Exercise the owner-side issuer independently of a CMS installation."""

import html
import http.cookiejar
import os
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BINARY", "php")


def run(*args, env=None, input_text=None):
    return subprocess.run(
        [PHP, *args], cwd=ROOT, env=env, input=input_text,
        text=True, capture_output=True, check=True,
    ).stdout.strip()


def token(page):
    match = re.search(r'name="csrf" value="([0-9a-f]+)"', page)
    assert match, "CSRF token is missing"
    return match.group(1)


def post(opener, url, values):
    data = urllib.parse.urlencode(values).encode()
    with opener.open(urllib.request.Request(url, data=data, method="POST")) as response:
        return response.read().decode()


with tempfile.TemporaryDirectory(prefix="fangdays-issuer-") as temporary:
    base = Path(temporary)
    key = base / "signing.key"
    home = base / "ledger"
    env = dict(os.environ, FANGDAYS_ISSUER_HOME=str(home))
    public = run("bin/license.php", "keygen", str(key)).split("=", 1)[1]
    setup = run("bin/issuer.php", "setup", str(key), "main", env=env)
    password = re.search(r"password=([0-9a-f]+)", setup).group(1)
    assert "public_key=" + public in setup
    assert "key_id=main" in run("bin/issuer.php", "public-key", env=env)
    assert home.stat().st_mode & 0o077 == 0
    assert (home / "issuer.sqlite").stat().st_mode & 0o077 == 0

    code = run("bin/issuer.php", "issue", "example.com", "甲方官网", "never", env=env)
    assert code.startswith("FD1.")
    assert "example.com" in run("bin/issuer.php", "list", env=env)
    assert '"valid":true' in run("bin/license.php", "verify", "main", public, "example.com", input_text=code)

    legacy = run("bin/license.php", "issue", str(key), "main", "legacy.test", "never")
    legacy_file = base / "legacy.code"
    legacy_file.write_text(legacy)
    run("bin/issuer.php", "import", str(legacy_file), "旧客户", env=env)
    assert "legacy.test" in run("bin/issuer.php", "list", env=env)
    duplicate = subprocess.run(
        [PHP, "bin/issuer.php", "import", str(legacy_file)], cwd=ROOT, env=env,
        text=True, capture_output=True,
    )
    assert duplicate.returncode != 0 and "already" in duplicate.stderr

    tampered_file = base / "tampered.code"
    tampered_file.write_text(legacy[:-1] + ("A" if legacy[-1] != "A" else "B"))
    tampered = subprocess.run(
        [PHP, "bin/issuer.php", "import", str(tampered_file)], cwd=ROOT, env=env,
        text=True, capture_output=True,
    )
    assert tampered.returncode != 0 and "verification failed" in tampered.stderr
    new_password = run("bin/issuer.php", "reset-password", env=env).split("=", 1)[1]
    assert new_password != password
    password = new_password

    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        port = listener.getsockname()[1]
    url = f"http://127.0.0.1:{port}/"
    with (base / "server.log").open("w+") as log:
        server = subprocess.Popen(
            [PHP, "-S", f"127.0.0.1:{port}", "-t", "issuer/public", "issuer/router.php"],
            cwd=ROOT, env=env, stdout=log, stderr=log,
        )
        try:
            for _ in range(50):
                try:
                    urllib.request.urlopen(url, timeout=1).close()
                    break
                except urllib.error.URLError:
                    time.sleep(0.1)
            else:
                raise AssertionError("issuer HTTP server did not start")

            cookies = http.cookiejar.CookieJar()
            browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))
            login = browser.open(url).read().decode()
            assert "登录授权中心" in login
            page = post(browser, url, {"csrf": token(login), "action": "login", "password": password})
            assert "签发与管理" in page and "legacy.test" in page

            try:
                post(browser, url, {"csrf": "bad", "action": "issue", "domains": "forged.test"})
                raise AssertionError("invalid CSRF was accepted")
            except urllib.error.HTTPError as error:
                assert error.code == 403

            page = post(browser, url, {
                "csrf": token(page), "action": "issue", "domains": "client.test,www.client.test",
                "customer": "新客户", "expires": "", "notes": "交付项目",
            })
            assert "client.test" in page and "授权码已生成" in page
            match = re.search(r'<textarea id="license-code"[^>]*>([^<]+)</textarea>', page)
            assert match, "issued code is not visible for copying"
            issued = html.unescape(match.group(1))
            assert '"valid":true' in run("bin/license.php", "verify", "main", public, "client.test", input_text=issued)
            assert "新客户" in run("bin/issuer.php", "list", env=env)

            bad_host = urllib.request.Request(url, headers={"Host": "outside.example"})
            try:
                urllib.request.urlopen(bad_host, timeout=2)
                raise AssertionError("non-local host was accepted")
            except urllib.error.HTTPError as error:
                assert error.code == 403
        finally:
            server.terminate()
            server.wait(timeout=5)

print("issuer smoke ok")
