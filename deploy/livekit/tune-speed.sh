#!/usr/bin/env bash
# ╔══════════════════════════════════════════════════════════════════════╗
# ║  تنظیمِ سرعتِ سرورِ کلاسِ آنلاین (LiveKit) — ستاره ماه                 ║
# ║  روی همان VPS که LiveKit نصب است، یک بار با root اجرا کنید:           ║
# ║      bash tune-speed.sh                                              ║
# ║  بی‌خطر است و هر چند بار اجرا شود همان نتیجه را می‌دهد؛                ║
# ║  هیچ رمز یا کلیدی نمی‌خواهد و چیزی از تنظیماتِ LiveKit را عوض نمی‌کند. ║
# ╚══════════════════════════════════════════════════════════════════════╝
set -euo pipefail
say()  { printf '\n\033[1;36m▶ %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✔ %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m⚠ %s\033[0m\n' "$*"; }

[ "$(id -u)" = 0 ] || { echo "این اسکریپت باید با root اجرا شود (sudo bash tune-speed.sh)"; exit 1; }

# ── ۱) شبکه ────────────────────────────────────────────────────────────
say "۱/۳ تنظیمِ شبکه برای صدا و تصویرِ روان"
modprobe tcp_bbr 2>/dev/null || true
BBR=""
if grep -qw bbr /proc/sys/net/ipv4/tcp_available_congestion_control 2>/dev/null; then
  BBR=$'net.core.default_qdisc=fq\nnet.ipv4.tcp_congestion_control=bbr'
  echo tcp_bbr > /etc/modules-load.d/starmah-bbr.conf
fi
cat > /etc/sysctl.d/90-livekit.conf <<SYS
# بافرِ بزرگ‌ترِ UDP: وقتی چند ده دانش‌آموز هم‌زمان وصل‌اند بسته‌ی صدا/تصویر دور ریخته نشود
net.core.rmem_max=7500000
net.core.wmem_max=7500000
net.core.rmem_default=1000000
net.core.wmem_default=1000000
net.core.netdev_max_backlog=5000
net.ipv4.udp_rmem_min=16384
net.ipv4.udp_wmem_min=16384
# اتصال‌های TCP (wss و حالتِ پشتیبانِ اینترنتِ همراه): بعد از مکث دوباره کُند شروع نشود
net.ipv4.tcp_slow_start_after_idle=0
net.ipv4.tcp_mtu_probing=1
${BBR}
SYS
sysctl --system >/dev/null 2>&1 || true
if [ -n "$BBR" ]; then ok "BBR روشن شد (روی اینترنتِ همراهِ پُرافت بهتر جواب می‌دهد)"; else warn "هسته‌ی این سرور BBR ندارد؛ بقیه‌ی تنظیم‌ها اعمال شد"; fi

# ── ۲) nginx ───────────────────────────────────────────────────────────
say "۲/۳ nginx"
if command -v nginx >/dev/null && [ -d /etc/nginx/conf.d ]; then
  cat > /etc/nginx/conf.d/starmah-speed.conf <<'NGX'
# نشستِ TLS نگه داشته شود تا ورودِ دوباره به کلاس دست‌دادنِ کامل نخواهد
ssl_session_cache shared:STARMAH:10m;
ssl_session_timeout 1d;
NGX
  if nginx -t >/dev/null 2>&1; then
    systemctl reload nginx && ok "nginx به‌روز شد"
  else
    rm -f /etc/nginx/conf.d/starmah-speed.conf   # با تنظیمِ موجودِ خودتان تداخل داشت؛ دست نمی‌زنیم
    warn "تنظیمِ nginx از قبل وجود دارد؛ تغییری داده نشد"
  fi
else
  warn "nginx پیدا نشد؛ رد شد"
fi

# ── ۳) گزارشِ وضعیت ────────────────────────────────────────────────────
say "۳/۳ وضعیتِ سرور"
CPU=$(nproc 2>/dev/null || echo '?')
RAM=$(awk '/MemTotal/ {printf "%.1f", $2/1048576}' /proc/meminfo)
FREE=$(awk '/MemAvailable/ {printf "%.1f", $2/1048576}' /proc/meminfo)
LOAD=$(cut -d' ' -f1-3 /proc/loadavg)
echo "  پردازنده: ${CPU} هسته   |   حافظه: ${RAM} گیگ (آزاد ${FREE})   |   بار: ${LOAD}"
echo "  کنترلِ ازدحام: $(sysctl -n net.ipv4.tcp_congestion_control 2>/dev/null)   |   بافرِ UDP: $(sysctl -n net.core.rmem_max 2>/dev/null)"
if systemctl is-active --quiet livekit 2>/dev/null; then ok "LiveKit روشن است"; else warn "سرویسِ LiveKit روشن نیست:  systemctl status livekit"; fi
DROPS=$(awk '/^Udp:/ && $2 ~ /^[0-9]+$/ {print $6}' /proc/net/snmp 2>/dev/null || true)
[ -n "${DROPS:-}" ] && echo "  بسته‌های UDP دورریخته از زمانِ روشن‌شدن: ${DROPS}  (اگر وسطِ کلاس تند بالا می‌رود، سرور کم آورده است)"
cat <<'TIP'

  راهنمای ظرفیت (تقریبی):
   • کلاسِ ۳۰ نفره با صدای معلم + تخته:        ۱ هسته / ۱ گیگ کافی است
   • با دوربینِ معلم و چند دوربینِ دانش‌آموز:    ۲ هسته / ۲ گیگ
   • چند کلاسِ هم‌زمان یا ضبط:                  ۴ هسته / ۴ گیگ
  اگر «بار» وسطِ کلاس از تعدادِ هسته‌ها بیشتر شد، سرور باید قوی‌تر شود.
TIP
ok "تمام شد. نیازی به راه‌اندازیِ دوباره‌ی سرور یا LiveKit نیست."
