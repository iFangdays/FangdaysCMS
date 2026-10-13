"""Verify one-command provisioning and its immediately usable public pages."""

import os
import pathlib
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request


SOURCE = pathlib.Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BINARY") or shutil.which("php")
if not PHP:
    raise SystemExit("Set PHP_BINARY to a PHP 8.2+ executable")

with tempfile.TemporaryDirectory(prefix="fangdays-quickstart-") as workspace:
    root = pathlib.Path(workspace)
    site = root / "site"
    shutil.copytree(SOURCE, site, ignore=shutil.ignore_patterns("app.php", "*.sqlite", "license.code"))
    env = os.environ.copy()
    env["HOME"] = str(root)
    setup = subprocess.run([PHP, str(site / "bin/quickstart.php")], cwd=site, env=env, text=True, capture_output=True, check=True)
    assert re.search(r"后台密码（只显示这一次）：[A-Za-z0-9_-]{32}", setup.stdout)
    assert (root / ".fangdayscms/issuer.key").is_file()
    assert (site / "config/app.php").is_file()
    assert (site / "var/license.code").is_file()
    duplicate = subprocess.run([PHP, str(site / "bin/quickstart.php")], cwd=site, env=env, text=True, capture_output=True)
    assert duplicate.returncode == 1 and "已经安装" in duplicate.stderr

    with socket.socket() as probe:
        probe.bind(("127.0.0.1", 0))
        port = probe.getsockname()[1]
    server = subprocess.Popen([PHP, "-S", f"127.0.0.1:{port}", "-t", str(site / "public"), str(site / "public/router.php")], cwd=site, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(30):
            try:
                with urllib.request.urlopen(f"http://127.0.0.1:{port}/", timeout=5) as response:
                    home = response.read().decode("utf-8")
                break
            except urllib.error.URLError:
                time.sleep(0.1)
        else:
            raise AssertionError("PHP server did not start")
        assert "欢迎使用 FangdaysCMS" in home and "/page/about" in home
        with urllib.request.urlopen(f"http://127.0.0.1:{port}/page/about", timeout=5) as response:
            assert "请在后台" in response.read().decode("utf-8")
        with urllib.request.urlopen(f"http://127.0.0.1:{port}/assets/theme/site.css", timeout=5) as response:
            assert response.status == 200
        print("FangdaysCMS one-command setup and first public visit passed")
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait(timeout=5)
