/**
 * واسطه‌ی تلگرام برای «ستاره ماه» (Cloudflare Worker — رایگان، بیرون از ایران)
 *
 * سرورِ سایت در ایران است و به api.telegram.org دسترسی ندارد. این واسطه دو کار می‌کند:
 *   ۱) پیام‌های تلگرام → سایت:   POST https://<این‌واسطه>/hook/<راز>  →  https://<سایت>/bot/telegram/<راز>
 *   ۲) پاسخ‌های سایت → تلگرام:   https://<این‌واسطه>/bot<توکن>/<متد>  →  https://api.telegram.org/bot<توکن>/<متد>
 *
 * متغیرهای Worker (Settings → Variables):
 *   SITE = https://starmah.ir          (نشانیِ سایت، بدونِ / در آخر)
 *   KEY  = یک رمزِ طولانیِ دلخواه       (همان «کلیدِ واسطه» در صفحه‌ی 🤖 بات)
 */
export default {
  async fetch(req, env) {
    const url = new URL(req.url);

    // ۱) تلگرام → سایت
    if (req.method === 'POST' && url.pathname.startsWith('/hook/')) {
      const secret = url.pathname.slice('/hook/'.length).replace(/[^A-Za-z0-9]/g, '');
      const res = await fetch(`${env.SITE}/bot/telegram/${secret}`, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: await req.text(),
      });
      return new Response(await res.text(), { status: res.status, headers: { 'content-type': 'application/json' } });
    }

    // ۲) سایت → تلگرام (فقط با کلیدِ درست)
    if (/^\/(file\/)?bot[^/]+\//.test(url.pathname)) {
      if (!env.KEY || req.headers.get('x-relay-key') !== env.KEY) {
        return new Response(JSON.stringify({ ok: false, description: 'relay key' }), { status: 403 });
      }
      const res = await fetch('https://api.telegram.org' + url.pathname + url.search, {
        method: req.method,
        headers: { 'content-type': req.headers.get('content-type') || 'application/json' },
        body: req.method === 'GET' ? undefined : await req.arrayBuffer(),
      });
      return new Response(res.body, { status: res.status, headers: { 'content-type': res.headers.get('content-type') || 'application/json' } });
    }

    return new Response('Starmah Telegram relay ✅');
  },
};
