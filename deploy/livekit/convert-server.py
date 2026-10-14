#!/usr/bin/env python3
"""
سرویسِ تبدیلِ فایلِ درس (پاورپوینت/ورد) به PDF برای کلاسِ آنلاینِ ستاره ماه.

فقط کتابخانه‌ی استانداردِ پایتون + LibreOffice. هاستِ سایت فایل را با رمز (X-Token)
می‌فرستد و PDF می‌گیرد؛ مرورگرِ معلم صفحه‌های PDF را به اسلایدِ تخته تبدیل می‌کند.

    GET  /health                 → {"ok":true}   (با X-Token)
    POST /convert?ext=pptx       → application/pdf  (بدنه = خودِ فایل)
"""
import hmac
import http.server
import json
import os
import shutil
import socketserver
import subprocess
import sys
import tempfile
import threading
from urllib.parse import parse_qs, urlparse

TOKEN = os.environ.get('CONVERT_TOKEN', '')
PORT = int(os.environ.get('CONVERT_PORT', '7890'))
BIND = os.environ.get('CONVERT_BIND', '0.0.0.0')
ALLOWED = {'ppt', 'pptx', 'pps', 'ppsx', 'odp', 'doc', 'docx', 'odt', 'rtf'}
MAX_BYTES = 60 * 1024 * 1024
SLOTS = threading.Semaphore(2)          # حداکثر دو تبدیلِ هم‌زمان (رم و پردازنده آزاد بماند)


class Handler(http.server.BaseHTTPRequestHandler):
    server_version = 'starmah-convert/1'

    def _auth(self):
        return bool(TOKEN) and hmac.compare_digest(self.headers.get('X-Token', ''), TOKEN)

    def _send(self, code, body, ctype='application/json'):
        self.send_response(code)
        self.send_header('Content-Type', ctype)
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _json(self, code, obj):
        self._send(code, json.dumps(obj, ensure_ascii=False).encode())

    def do_GET(self):
        if not self.path.startswith('/health'):
            return self._json(404, {'error': 'not found'})
        if not self._auth():
            return self._json(401, {'ok': False})
        return self._json(200, {'ok': True, 'soffice': bool(shutil.which('soffice'))})

    def do_POST(self):
        if not self.path.startswith('/convert'):
            return self._json(404, {'error': 'not found'})
        if not self._auth():
            return self._json(401, {'error': 'token'})
        ext = (parse_qs(urlparse(self.path).query).get('ext', [''])[0] or '').lower()
        if ext not in ALLOWED:
            return self._json(415, {'error': 'type'})
        size = int(self.headers.get('Content-Length') or 0)
        if size <= 0 or size > MAX_BYTES:
            return self._json(413, {'error': 'size'})
        data = self.rfile.read(size)
        with SLOTS:
            work = tempfile.mkdtemp(prefix='conv-')
            try:
                src = os.path.join(work, 'lesson.' + ext)
                with open(src, 'wb') as f:
                    f.write(data)
                cmd = ['soffice', '-env:UserInstallation=file://' + work + '/profile', '--headless', '--norestore',
                       '--nolockcheck', '--convert-to', 'pdf', '--outdir', work, src]
                run = subprocess.run(cmd, capture_output=True, timeout=150)
                out = os.path.join(work, 'lesson.pdf')
                if not os.path.exists(out):
                    return self._json(500, {'error': 'convert', 'log': run.stderr.decode(errors='ignore')[-300:]})
                with open(out, 'rb') as f:
                    return self._send(200, f.read(), 'application/pdf')
            except subprocess.TimeoutExpired:
                return self._json(504, {'error': 'timeout'})
            finally:
                shutil.rmtree(work, ignore_errors=True)

    def log_message(self, fmt, *args):
        sys.stderr.write('%s %s\n' % (self.address_string(), fmt % args))


class Server(socketserver.ThreadingMixIn, http.server.HTTPServer):
    daemon_threads = True


if __name__ == '__main__':
    if not TOKEN:
        sys.exit('CONVERT_TOKEN is not set')
    print('starmah-convert listening on %s:%d' % (BIND, PORT), flush=True)
    Server((BIND, PORT), Handler).serve_forever()
