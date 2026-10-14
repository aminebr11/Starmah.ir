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

say "۳/۳ بازکردنِ پورت"
ufw allow "${PORT}/tcp" comment 'Lesson converter' >/dev/null || true
IP=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src"){print $(i+1); exit}}')
[ -n "$IP" ] || IP=$(hostname -I | awk '{print $1}')

printf '\n\033[1;32m════════ در تنظیماتِ ادمینِ کل ← کلاسِ آنلاین وارد کنید ════════\033[0m\n'
printf 'نشانیِ سرویسِ تبدیل:  http://%s:%s\n' "$IP" "$PORT"
printf 'رمز (Token):          %s\n\n' "$TOKEN"
