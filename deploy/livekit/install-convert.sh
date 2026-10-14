#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════
#  سرویسِ تبدیلِ پاورپوینت/ورد به PDF برای کلاسِ آنلاینِ ستاره ماه.
#  روی همان VPS، با root:   bash install-convert.sh
#  فایلِ convert-server.py باید کنارِ همین اسکریپت باشد.
#  در پایان «نشانی» و «رمز» را نشان می‌دهد که در تنظیماتِ ادمینِ کل وارد می‌کنید.
# ═══════════════════════════════════════════════════════════════════════
set -euo pipefail
PORT=7890
DIR=/opt/starmah-convert
say() { printf '\n\033[1;35m▶ %s\033[0m\n' "$*"; }
ok()  { printf '\033[1;32m✔ %s\033[0m\n' "$*"; }
die() { printf '\033[1;31m✖ %s\033[0m\n' "$*"; exit 1; }
[ "$(id -u)" = 0 ] || die "با root اجرا کنید."
HERE=$(cd "$(dirname "$0")" && pwd)
[ -s "$HERE/convert-server.py" ] || die "فایلِ convert-server.py کنارِ این اسکریپت نیست؛ هر دو را در یک پوشه بگذارید."

say "۱/۳ نصبِ LibreOffice و فونت‌ها (چند دقیقه)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y >/dev/null
apt-get install -y --no-install-recommends python3 libreoffice-impress-nogui libreoffice-writer-nogui fonts-dejavu-core fonts-noto-core >/dev/null \
  || apt-get install -y python3 libreoffice-impress libreoffice-writer fonts-dejavu-core >/dev/null \
  || die "نصبِ LibreOffice نشد (مخزنِ Ubuntu در دسترس نیست؟)."
# فونت‌های فارسی (اگر در مخزن بود)
apt-get install -y fonts-vazirmatn >/dev/null 2>&1 || true
apt-get install -y fonts-farsiweb >/dev/null 2>&1 || true
ok "$(soffice --version | head -1)"

# اگر LiveKit با nginx روی همین سرور است: از راهِ همان https (پورتِ ۴۴۳) سرو شود — امن‌تر،
# و هاستِ سایت معمولاً پورت‌های غیرِ ۴۴۳ را برای اتصالِ خروجی می‌بندد.
SITE=/etc/nginx/sites-available/livekit
PROXY=0; DOMAIN=""
if [ -f "$SITE" ] && grep -q 'snippets/starmah-' "$SITE"; then
  PROXY=1; DOMAIN=$(awk '/server_name/ {gsub(";",""); print $2; exit}' "$SITE")
fi
BIND=0.0.0.0; [ "$PROXY" = 1 ] && BIND=127.0.0.1

say "۲/۳ ساختنِ سرویس"
mkdir -p "$DIR"
install -m 755 "$HERE/convert-server.py" "$DIR/convert-server.py"
id starmah-convert >/dev/null 2>&1 || useradd --system --home "$DIR" --shell /usr/sbin/nologin starmah-convert
[ -s "$DIR/token" ] || { openssl rand -hex 24 > "$DIR/token"; }
chown starmah-convert "$DIR/token"; chmod 600 "$DIR/token"
TOKEN=$(cat "$DIR/token")
cat > /etc/systemd/system/starmah-convert.service <<UNIT
[Unit]
Description=Starmah lesson-file converter (PowerPoint/Word to PDF)
After=network-online.target

[Service]
User=starmah-convert
Environment=CONVERT_TOKEN=${TOKEN}
Environment=CONVERT_PORT=${PORT}
Environment=CONVERT_BIND=${BIND}
ExecStart=/usr/bin/python3 ${DIR}/convert-server.py
Restart=always
RestartSec=3
PrivateTmp=true
MemoryMax=2G

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now starmah-convert
systemctl restart starmah-convert
sleep 2
curl -fsS -H "X-Token: ${TOKEN}" "http://127.0.0.1:${PORT}/health" >/dev/null || { journalctl -u starmah-convert -n 20 --no-pager; die "سرویس بالا نیامد."; }
ok "سرویس روشن است"

if [ "$PROXY" = 1 ]; then
  say "۳/۳ وصل کردن به nginx (https://${DOMAIN}/convert-api)"
  cat > /etc/nginx/snippets/starmah-convert.conf <<NGX
location /convert-api/ {
    proxy_pass http://127.0.0.1:${PORT}/;
    proxy_set_header X-Token \$http_x_token;
    proxy_read_timeout 200s;
    proxy_send_timeout 200s;
    proxy_request_buffering on;
}
NGX
  nginx -t && systemctl reload nginx
  ufw delete allow "${PORT}/tcp" >/dev/null 2>&1 || true
  sleep 1
  curl -fsS -H "X-Token: ${TOKEN}" "https://${DOMAIN}/convert-api/health" >/dev/null && ok "از راهِ https در دسترس است" || warn "آزمایشِ https جواب نداد (SSL نصب شده؟)."
  printf '\n\033[1;32m════════ در تنظیماتِ ادمینِ کل ← کلاسِ آنلاین وارد کنید ════════\033[0m\n'
  printf 'نشانیِ سرویسِ تبدیل:  https://%s/convert-api\n' "$DOMAIN"
  printf 'رمز (Token):          %s\n\n' "$TOKEN"
  exit 0
fi

say "۳/۳ بازکردنِ پورت"
ufw allow "${PORT}/tcp" comment 'Lesson converter' >/dev/null || true
IP=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src"){print $(i+1); exit}}')
[ -n "$IP" ] || IP=$(hostname -I | awk '{print $1}')

printf '\n\033[1;32m════════ در تنظیماتِ ادمینِ کل ← کلاسِ آنلاین وارد کنید ════════\033[0m\n'
printf 'نشانیِ سرویسِ تبدیل:  http://%s:%s\n' "$IP" "$PORT"
printf 'رمز (Token):          %s\n\n' "$TOKEN"
