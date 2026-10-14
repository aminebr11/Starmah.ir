#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════
#  نصبِ دستیِ گواهیِ SSL برای سرورِ LiveKit (وقتی certbot به اینترنتِ بین‌الملل
#  دسترسی ندارد). روی VPS با root اجرا کنید.
#
#  گام ۱ — ساختِ کلید و درخواست (CSR):
#      bash install-manual-cert.sh csr live.starmah.ir
#    متنِ CSR را که نشان می‌دهد در سایتِ فروشنده‌ی SSL (یا ZeroSSL) بچسبانید و
#    دامنه را با «رکوردِ DNS» (CNAME یا TXT در cPanel ← Zone Editor) تأیید کنید.
#
#  گام ۲ — نصبِ گواهیِ دریافتی (فایل‌ها را با SFTP در /root بگذارید):
#      bash install-manual-cert.sh install live.starmah.ir /root/certificate.crt [/root/ca_bundle.crt] [/root/private.key]
#    اگر کلید را خودِ فروشنده ساخته (مثلِ ZIPِ ZeroSSL)، مسیرِ private.key را هم بدهید؛
#    وگرنه همان کلیدِ گام ۱ به کار می‌رود.
# ═══════════════════════════════════════════════════════════════════════
set -euo pipefail
say()  { printf '\n\033[1;35m▶ %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✔ %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m⚠ %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m✖ %s\033[0m\n' "$*"; exit 1; }
[ "$(id -u)" = 0 ] || die "با root اجرا کنید."
MODE="${1:-}"; DOMAIN="${2:-live.starmah.ir}"
SSL=/etc/livekit/ssl
mkdir -p "$SSL"; chmod 700 "$SSL"

case "$MODE" in
csr)
  say "ساختِ کلیدِ خصوصی و CSR برای $DOMAIN"
  if [ -s "$SSL/$DOMAIN.key" ]; then ok "کلیدِ قبلی حفظ شد"; else
    openssl genrsa -out "$SSL/$DOMAIN.key" 2048 2>/dev/null; chmod 600 "$SSL/$DOMAIN.key"; fi
  openssl req -new -key "$SSL/$DOMAIN.key" -out "$SSL/$DOMAIN.csr" -subj "/CN=$DOMAIN" -addext "subjectAltName=DNS:$DOMAIN" 2>/dev/null \
    || openssl req -new -key "$SSL/$DOMAIN.key" -out "$SSL/$DOMAIN.csr" -subj "/CN=$DOMAIN"
  cp "$SSL/$DOMAIN.csr" "/root/$DOMAIN.csr"
  ok "CSR ساخته شد (در /root/$DOMAIN.csr هم هست). این متن را کامل کپی کنید:"
  echo; cat "$SSL/$DOMAIN.csr"; echo
  printf 'کلیدِ خصوصی روی همین سرور می‌ماند (%s) و لازم نیست جایی بفرستید.\n' "$SSL/$DOMAIN.key"
  ;;
install)
  CRT="${3:-}"; CA="${4:-}"; KEY="${5:-$SSL/$DOMAIN.key}"
  [ -s "$CRT" ] || die "فایلِ گواهی پیدا نشد: «$CRT»"
  [ -s "$KEY" ] || die "کلیدِ خصوصی پیدا نشد: «$KEY» (اگر فروشنده کلید داده، مسیرش را آرگومانِ پنجم بدهید)"
  # اگر فقط سه فایل داده شد و سومی کلید است (بدونِ CA)
  if [ -n "$CA" ] && grep -q 'PRIVATE KEY' "$CA" 2>/dev/null; then KEY="$CA"; CA=""; fi
  say "بررسیِ گواهی"
  openssl x509 -in "$CRT" -noout >/dev/null 2>&1 || die "«$CRT» گواهیِ معتبر (PEM) نیست. اگر .cer/.der است: openssl x509 -inform der -in فایل -out cert.crt"
  A=$(openssl x509 -in "$CRT" -noout -pubkey | openssl sha256); B=$(openssl pkey -in "$KEY" -pubout | openssl sha256)
  [ "$A" = "$B" ] || die "این گواهی با این کلیدِ خصوصی جور نیست (CSR را با کلیدِ دیگری ساخته‌اید؟)."
  openssl x509 -in "$CRT" -noout -checkhost "$DOMAIN" 2>/dev/null | grep -q 'does match' || warn "نامِ گواهی با $DOMAIN یکی نیست؛ ادامه می‌دهم ولی مرورگر ممکن است ایراد بگیرد."
  END=$(openssl x509 -in "$CRT" -noout -enddate | cut -d= -f2)
  ok "گواهی سالم است؛ اعتبار تا: $END"
  { cat "$CRT"; echo; [ -n "$CA" ] && [ -s "$CA" ] && cat "$CA"; } | sed '/^$/d' > "$SSL/fullchain.pem"
  install -m 600 "$KEY" "$SSL/privkey.pem"

  say "تنظیمِ nginx روی ۴۴۳"
  cat > /etc/nginx/sites-available/livekit <<NGX
map \$http_upgrade \$connection_upgrade { default upgrade; '' close; }
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    location /.well-known/ { root /var/www/html; }
    location / { return 301 https://\$host\$request_uri; }
}
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name ${DOMAIN};
    ssl_certificate     ${SSL}/fullchain.pem;
    ssl_certificate_key ${SSL}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_session_cache shared:lk:10m;
    location / {
        proxy_pass http://127.0.0.1:7880;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection \$connection_upgrade;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
    }
}
NGX
  # نسخه‌های قدیمیِ nginx «http2 on» را نمی‌شناسند
  nginx -t 2>/dev/null || sed -i 's/^    http2 on;$//; s/listen 443 ssl;/listen 443 ssl http2;/; s/listen \[::\]:443 ssl;/listen [::]:443 ssl http2;/' /etc/nginx/sites-available/livekit
  ln -sf /etc/nginx/sites-available/livekit /etc/nginx/sites-enabled/livekit
  rm -f /etc/nginx/sites-enabled/default
  nginx -t || die "تنظیمِ nginx ایراد دارد (پیامِ بالا)."
  systemctl reload nginx || systemctl restart nginx
  ufw allow 443/tcp >/dev/null 2>&1 || true
  sleep 1
  if curl -fsS --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}" >/dev/null 2>&1; then ok "https://${DOMAIN} روی همین سرور جواب می‌دهد"
  else warn "آزمایشِ داخلی جواب نداد؛ systemctl status livekit را ببینید."; fi

  printf '\n\033[1;32m════════ SSL نصب شد 🎉 — در سایت وارد کنید ════════\033[0m\n'
  printf 'نشانیِ سرور (wss):  wss://%s\n' "$DOMAIN"
  if [ -s /etc/livekit/keys ]; then . /etc/livekit/keys; printf 'API Key:            %s\nAPI Secret:         %s\n' "$API_KEY" "$API_SECRET"; fi
  printf '\nیادآوری: تاریخِ پایانِ گواهی %s است؛ پیش از آن گواهیِ تازه بگیرید و همین فرمان را دوباره اجرا کنید.\n\n' "$END"
  ;;
*)
  sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'
  exit 1
  ;;
esac
