#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════
#  سرورِ TURN (coturn) برای حالتِ «داخلی» کلاسِ آنلاین.
#  اینترنتِ موبایل و بعضی شبکه‌ها مستقیم وصل نمی‌شوند؛ TURN رله می‌کند.
#  این سرور به گواهی SSL نیاز ندارد؛ پس الان هم کار می‌کند.
#
#  اجرا (روی همان VPS، با root):
#      bash install-turn.sh
#  خروجی یک خطِ «ICE» می‌دهد که در تنظیمات ادمین کل، کادرِ STUN/TURN
#  را با آن پر می‌کنید.
# ═══════════════════════════════════════════════════════════════════════
set -euo pipefail
KEYS=/root/turn-keys.txt
PORT=3479                 # ۳۴۷۸ مال LiveKit است
RELAY_MIN=41000
RELAY_MAX=49000

say() { printf '\n\033[1;35m▶ %s\033[0m\n' "$*"; }
ok()  { printf '\033[1;32m✔ %s\033[0m\n' "$*"; }
die() { printf '\033[1;31m✖ %s\033[0m\n' "$*"; exit 1; }
[ "$(id -u)" = 0 ] || die "با root اجرا کنید."

say "۱/۳ نصبِ coturn"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y >/dev/null
apt-get install -y coturn >/dev/null
ok "coturn نصب شد"

say "۲/۳ ساختنِ تنظیمات و کاربر"
IP=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src"){print $(i+1); exit}}')
[ -n "$IP" ] || IP=$(hostname -I | awk '{print $1}')
if [ -s "$KEYS" ]; then . "$KEYS"; else
  TURN_USER="starmah"
  TURN_PASS="$(openssl rand -hex 16)"
  printf 'TURN_USER=%s\nTURN_PASS=%s\n' "$TURN_USER" "$TURN_PASS" > "$KEYS"; chmod 600 "$KEYS"
fi
cat > /etc/turnserver.conf <<CONF
# coturn برای کلاسِ آنلاینِ ستاره ماه (حالتِ داخلی)
listening-port=${PORT}
listening-ip=0.0.0.0
external-ip=${IP}
min-port=${RELAY_MIN}
max-port=${RELAY_MAX}
realm=starmah.ir
fingerprint
lt-cred-mech
user=${TURN_USER}:${TURN_PASS}
no-tls
no-dtls
no-multicast-peers
no-cli
no-loopback-peers
stale-nonce=600
log-file=/var/log/turnserver.log
simple-log
CONF
sed -i 's/^#\?TURNSERVER_ENABLED=.*/TURNSERVER_ENABLED=1/' /etc/default/coturn 2>/dev/null || true
ok "تنظیمات نوشته شد"

say "۳/۳ روشن‌کردن و بازکردنِ پورت‌ها"
ufw allow "${PORT}/udp" comment 'TURN (internal class)' >/dev/null
ufw allow "${PORT}/tcp" comment 'TURN (internal class)' >/dev/null
ufw allow "${RELAY_MIN}:${RELAY_MAX}/udp" comment 'TURN relay' >/dev/null
systemctl enable --now coturn
systemctl restart coturn
sleep 2
systemctl is-active --quiet coturn || { journalctl -u coturn -n 30 --no-pager; die "coturn بالا نیامد."; }
ok "coturn روشن است"

printf '\n\033[1;32m════════ این خط را در تنظیمات ادمین کل (STUN/TURN) بگذارید ════════\033[0m\n'
printf 'stun:stun.l.google.com:19302\n'
printf 'turn:%s:%s %s %s\n' "$IP" "$PORT" "$TURN_USER" "$TURN_PASS"
printf 'turn:%s:%s?transport=tcp %s %s\n' "$IP" "$PORT" "$TURN_USER" "$TURN_PASS"
printf '\nاین خط‌ها در %s هم ذخیره شدند.\n' "$KEYS"
