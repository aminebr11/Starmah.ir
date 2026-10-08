/**
 * پیام‌های روشن برای شکستِ درخواست‌های هوش مصنوعی (طراحیِ سؤال، کاربرگ، مأموریت، بازی).
 * هدف: هیچ‌وقت کادرِ خطای خالی یا «خطا»ی بی‌توضیح نشان داده نشود.
 */
/** توضیحِ روشن برای هر نوع شکست — هیچ‌وقت کادرِ خطای خالی نشان داده نشود. */
export function aiFailText(status, body) {
    if (!status) return 'پاسخی از سرور نرسید — اینترنت قطع شد یا پاسخِ هوش مصنوعی خیلی طول کشید. تعدادِ سؤال را کمتر کنید و دوباره تلاش کنید.';
    if (status === 401 || status === 419) return 'نشستِ شما منقضی شده؛ صفحه را تازه کنید (یا دوباره وارد شوید) و دوباره تلاش کنید.';
    if (status === 403) return 'اجازه‌ی استفاده از طراحیِ سؤال را ندارید؛ با مدیرِ مدرسه یا ادمینِ کل هماهنگ کنید.';
    if (status === 404) return 'این بخش برای حسابِ شما فعال نیست (آزمایشگاهِ هوشمند خاموش است یا مدرسه در فهرستِ آزمایشی نیست).';
    if (status === 429) return 'درخواست‌ها زیاد شد؛ یک دقیقه صبر کنید و دوباره تلاش کنید.';
    if (status === 502 || status === 503 || status === 504 || status === 524) return 'سرور در زمانِ مجاز پاسخ نداد (احتمالاً سقفِ زمانِ هاست). تعدادِ سؤال را کمتر کنید یا از ادمین بخواهید مدلِ سریع‌تری انتخاب کند.';
    if (status >= 500) return 'خطای سرور هنگامِ طراحیِ سؤال (' + status + '). چند لحظه بعد دوباره تلاش کنید؛ اگر تکرار شد به ادمین خبر دهید.';
    if (status === 200 && (body === '' || body == null)) return 'سرور پاسخِ خالی داد — معمولاً یعنی زمانِ اجرای PHP روی هاست تمام شده. تعدادِ سؤال را کمتر کنید یا مدلِ سریع‌تری انتخاب شود.';
    const pg = inertiaPage(body);
    if (pg) {
        if (/Auth\/Login|Login$/.test(pg.component || '')) return 'نشستِ شما منقضی شده یا از حساب خارج شده‌اید؛ دوباره وارد شوید و دوباره تلاش کنید.';
        if (/ForcePassword|ChangePassword/i.test(pg.component || '')) return 'پیش از ادامه باید رمزِ عبورتان را تغییر دهید (صفحه را تازه کنید).';
        return 'سرور به‌جای پاسخ، صفحه‌ی «' + (pg.component || '؟') + '» (' + (pg.url || '') + ') را برگرداند — این متن را برای پشتیبانی بفرستید.';
    }
    const t = titleOf(body);
    if (t) return 'پاسخی غیرمنتظره رسید: صفحه‌ای با عنوانِ «' + t.trim().slice(0, 80) + '» (احتمالاً فایروال یا صفحه‌ی امنیتیِ هاست). این متن را برای پشتیبانی بفرستید.';
    const sn = snippet(body);
    return 'پاسخِ نامعتبر از سرور دریافت شد (' + status + ').' + (sn ? ' ابتدای پاسخ: «' + sn + '» — این متن را برای پشتیبانی بفرستید.' : ' صفحه را تازه کنید و دوباره تلاش کنید.');
}


/** آیا پاسخ واقعاً JSONِ سرویس است؟ */
export const isAiPayload = (data) => !!data && typeof data === 'object' && 'ok' in data;

/**
 * اگر پیش از JSON متنی چاپ شده باشد (هشدارِ PHP روی هاست)، axios پاسخ را رشته
 * برمی‌گرداند؛ JSONِ اصلی را از داخلش بیرون می‌کشیم.
 */
export function salvageAiPayload(data) {
    if (isAiPayload(data)) return data;
    if (typeof data !== 'string') return null;
    const i = data.indexOf('{"ok"');
    if (i < 0) return null;
    for (let j = data.lastIndexOf('}'); j > i; j = data.lastIndexOf('}', j - 1)) {
        try { const o = JSON.parse(data.slice(i, j + 1)); if (isAiPayload(o)) return o; } catch { /* ادامه */ }
    }
    return null;
}

/** اگر پاسخ یک صفحه‌ی کاملِ سایت (Inertia) باشد: نامِ صفحه و نشانی‌اش. */
function inertiaPage(s) {
    if (typeof s !== 'string') return null;
    const m = s.match(/data-page="([^"]+)"/);
    if (!m) return null;
    try {
        const txt = m[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&#039;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>');
        const pg = JSON.parse(txt);
        return { component: pg.component, url: pg.url };
    } catch { return null; }
}
const titleOf = (s) => (typeof s === 'string' && (s.match(/<title[^>]*>([^<]*)<\/title>/i) || [])[1]) || '';
const snippet = (s) => typeof s === 'string' ? s.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140) : '';

/** متنِ خطا از استثنای axios. */
export function aiErrorText(e) {
    const err = e?.response?.data;
    const first = err && typeof err === 'object' && err.errors ? Object.values(err.errors)[0]?.[0] : null;
    return first || (err && typeof err === 'object' && err.message) || aiFailText(e?.response?.status, err);
}

/** متنِ خطا برای پاسخی که ok=false است یا اصلاً JSON نیست. */
export function aiResultText(data) {
    if (!isAiPayload(data)) return aiFailText(200, data);
    return data.message || 'هوش مصنوعی پاسخِ قابلِ استفاده‌ای برنگرداند؛ دوباره تلاش کنید یا مبحث را دقیق‌تر بنویسید.';
}
