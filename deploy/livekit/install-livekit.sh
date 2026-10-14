#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════
#  نصبِ سرورِ کلاسِ آنلاینِ «ستاره ماه» — LiveKit + nginx + SSL + TURN
#
#  روی یک سرورِ تازه‌ی Ubuntu 22.04 / 24.04 با کاربرِ root اجرا کنید:
#      bash install-livekit.sh live.starmah.ir you@example.com
#
#  دوباره اجرا کردنش بی‌خطر است: کلیدهای قبلی حفظ می‌شوند.
# ═══════════════════════════════════════════════════════════════════════
set -euo pipefail

DOMAIN="${1:-live.starmah.ir}"
EMAIL="${2:-}"
LK_VERSION="${LK_VERSION:-1.10.1}"
CONF_DIR=/etc/livekit
KEYS_FILE=/root/livekit-keys.txt

say()  { printf '\n\033[1;35m▶ %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✔ %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m⚠ %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m✖ %s\033[0m\n' "$*"; exit 1; }

[ "$(id -u)" = 0 ] || die "باید با کاربرِ root اجرا شود (یا: sudo bash $0 ...)"
[ -n "$EMAIL" ] || die "ایمیل را هم بدهید؛ مثال: bash $0 $DOMAIN you@example.com"

case "$(uname -m)" in
  x86_64) ARCH=amd64 ;; aarch64|arm64) ARCH=arm64 ;; *) die "معماریِ پردازنده پشتیبانی نمی‌شود: $(uname -m)" ;;
esac

# ── ۱) IP عمومیِ سرور و بررسیِ DNS ─────────────────────────────────────
say "۱/۸ بررسیِ IP و دامنه"
PUBLIC_IP="${PUBLIC_IP:-$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src"){print $(i+1); exit}}')}"
[ -n "$PUBLIC_IP" ] || PUBLIC_IP=$(hostname -I | awk '{print $1}')
ok "IP سرور: $PUBLIC_IP"
DNS_IP=$(getent ahostsv4 "$DOMAIN" 2>/dev/null | awk 'NR==1{print $1}' || true)
if [ "$DNS_IP" != "$PUBLIC_IP" ]; then
  warn "دامنه‌ی $DOMAIN الان به «${DNS_IP:-هیچ‌جا}» اشاره می‌کند، نه به $PUBLIC_IP."
  warn "در cPanel → Zone Editor یک رکوردِ A برای $DOMAIN با مقدارِ $PUBLIC_IP بسازید، چند دقیقه صبر کنید و دوباره اجرا کنید."
  die "تا وقتی DNS درست نشده، گواهیِ SSL صادر نمی‌شود."
fi
ok "DNS درست است"

# ── ۲) بسته‌ها ─────────────────────────────────────────────────────────
say "۲/۸ نصبِ nginx، certbot و فایروال"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y nginx certbot python3-certbot-nginx ufw curl openssl ca-certificates tar
ok "بسته‌ها نصب شد"

# ── ۳) LiveKit ─────────────────────────────────────────────────────────
say "۳/۸ نصبِ LiveKit نسخه‌ی $LK_VERSION"
TGZ=/root/livekit_${LK_VERSION}_linux_${ARCH}.tar.gz
if [ ! -s "$TGZ" ]; then
  curl -fL --retry 3 --connect-timeout 20 -o "$TGZ" \
    "https://github.com/livekit/livekit/releases/download/v${LK_VERSION}/livekit_${LK_VERSION}_linux_${ARCH}.tar.gz" \
    || { rm -f "$TGZ"; die "دانلود از GitHub نشد. یا اینترنتِ بین‌الملل فعال نیست، یا GitHub در دسترس نیست.
راهِ دیگر: فایلِ livekit_${LK_VERSION}_linux_${ARCH}.tar.gz را روی رایانه‌ی خودتان دانلود و در مسیرِ $TGZ روی سرور بارگذاری کنید و اسکریپت را دوباره اجرا کنید."; }
fi
tar -xzf "$TGZ" -C /usr/local/bin livekit-server
chmod +x /usr/local/bin/livekit-server
ok "$(/usr/local/bin/livekit-server --version)"

# ── ۴) کلیدها و تنظیمات ────────────────────────────────────────────────
say "۴/۸ ساختِ کلیدها و فایلِ تنظیمات"
mkdir -p "$CONF_DIR"
if [ -s "$CONF_DIR/keys" ]; then
  . "$CONF_DIR/keys"; ok "کلیدهای قبلی حفظ شد"
else
  API_KEY="API$(openssl rand -hex 6)"
  API_SECRET="$(openssl rand -base64 36 | tr -d '/+=\n' | cut -c1-44)"
  printf 'API_KEY=%s\nAPI_SECRET=%s\n' "$API_KEY" "$API_SECRET" > "$CONF_DIR/keys"
  chmod 600 "$CONF_DIR/keys"; ok "کلیدهای تازه ساخته شد"
fi
# کلیدها همین حالا هم ذخیره شوند (اگر مرحله‌ی SSL شکست بخورد هم در دسترس باشند)
printf 'نشانیِ سرور (wss):  wss://%s\nAPI Key:            %s\nAPI Secret:         %s\n' "$DOMAIN" "$API_KEY" "$API_SECRET" > "$KEYS_FILE"
chmod 600 "$KEYS_FILE"

cat > "$CONF_DIR/livekit.yaml" <<YAML
# تنظیماتِ LiveKit برای کلاسِ آنلاینِ ستاره ماه
port: 7880
logging:
  level: info
rtc:
  tcp_port: 7881
  port_range_start: 50000
  port_range_end: 60000
  # IP را خودمان می‌دهیم (روی اینترنتِ ملی، پیدا کردنِ خودکار با STUN کار نمی‌کند)
  use_external_ip: false
  node_ip: ${PUBLIC_IP}
turn:
  # برای اینترنتِ همراه و شبکه‌های سخت‌گیر
  enabled: true
  udp_port: 3478
  domain: ${DOMAIN}
  relay_range_start: 30000
  relay_range_end: 40000
room:
  empty_timeout: 300
  max_participants: 300
keys:
  ${API_KEY}: ${API_SECRET}
YAML
chmod 600 "$CONF_DIR/livekit.yaml"

# بافرِ UDP بزرگ‌تر برای صدا/تصویرِ روان
cat > /etc/sysctl.d/90-livekit.conf <<'SYS'
net.core.rmem_max=5000000
net.core.wmem_max=5000000
net.core.rmem_default=1000000
net.core.wmem_default=1000000
SYS
sysctl --system >/dev/null || true
ok "تنظیمات نوشته شد"

# ── ۵) سرویسِ خودکار ───────────────────────────────────────────────────
say "۵/۸ ساختِ سرویسِ همیشه‌روشن"
cat > /etc/systemd/system/livekit.service <<UNIT
[Unit]
Description=LiveKit (Starmah online class)
After=network-online.target
Wants=network-online.target

[Service]
ExecStart=/usr/local/bin/livekit-server --config ${CONF_DIR}/livekit.yaml
Restart=always
RestartSec=3
LimitNOFILE=500000

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now livekit
systemctl restart livekit
sleep 3
curl -fsS http://127.0.0.1:7880 >/dev/null || { journalctl -u livekit -n 30 --no-pager; die "LiveKit بالا نیامد (گزارش بالا را بفرستید)."; }
ok "LiveKit روشن است"

# ── ۶) nginx (پراکسیِ امن برای wss://) ────────────────────────────────
say "۶/۸ تنظیمِ nginx برای $DOMAIN"
cat > /etc/nginx/sites-available/livekit <<NGX
map \$http_upgrade \$connection_upgrade { default upgrade; '' close; }
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    location / {
        proxy_pass http://127.0.0.1:7880;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection \$connection_upgrade;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
    }
}
NGX
ln -sf /etc/nginx/sites-available/livekit /etc/nginx/sites-enabled/livekit
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx || systemctl restart nginx
ok "nginx آماده است"

# ── ۷) فایروال ─────────────────────────────────────────────────────────
say "۷/۸ باز کردنِ پورت‌ها"
SSH_PORT=$(ss -tlnp 2>/dev/null | awk '/sshd/ {sub(/.*:/,"",$4); print $4; exit}')
ufw allow "${SSH_PORT:-22}/tcp" comment 'SSH'
[ "${SSH_PORT:-22}" = 22 ] || ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP (SSL)'
ufw allow 443/tcp comment 'HTTPS / wss'
ufw allow 7881/tcp comment 'LiveKit TCP'
ufw allow 3478/udp comment 'TURN'
ufw allow 50000:60000/udp comment 'LiveKit media'
ufw allow 30000:40000/udp comment 'TURN relay'
ufw --force enable
ok "فایروال فعال شد (SSH باز ماند)"

# ── ۸) گواهیِ SSL ──────────────────────────────────────────────────────
say "۸/۸ گرفتنِ گواهیِ SSL رایگان"
if ! certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect; then
  warn "گواهیِ رایگان صادر نشد (معمولاً یعنی اینترنتِ بین‌المللِ VPS فعال نیست)."
  warn "LiveKit نصب و روشن است و کلیدها در $KEYS_FILE ذخیره شد."
  warn "راهِ دیگر: گواهی را جای دیگری بگیرید و با «bash install-manual-cert.sh» نصب کنید (راهنما در همان فایل)."
  exit 1
fi
systemctl reload nginx
sleep 1
if curl -fsS "https://${DOMAIN}" >/dev/null; then ok "https://${DOMAIN} جواب می‌دهد"; else warn "https هنوز جواب نمی‌دهد؛ چند دقیقه بعد امتحان کنید."; fi

cat > "$KEYS_FILE" <<OUT
نشانیِ سرور (wss):  wss://${DOMAIN}
API Key:            ${API_KEY}
API Secret:         ${API_SECRET}
OUT
chmod 600 "$KEYS_FILE"

printf '\n\033[1;32m════════════ نصب تمام شد 🎉 ════════════\033[0m\n'
cat "$KEYS_FILE"
printf '\nاین سه مقدار را در سایت وارد کنید: ادمینِ کل ← کلاسِ آنلاین ← «سرورِ رسانه‌ی LiveKit».\n'
printf 'این اطلاعات در فایلِ %s هم ذخیره شد.\n\n' "$KEYS_FILE"
