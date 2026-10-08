// واژه‌نامه و کمک‌کارهای مشترکِ آزمون‌ساز، استودیوی بازی و بانک سؤال
export const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export const TYPE_FA = { mc: 'چهارگزینه‌ای', tf: 'درست/نادرست', blank: 'جای خالی', desc: 'تشریحی', short: 'پاسخِ کوتاه' };
export const DIFF_FA = { easy: 'آسان', medium: 'متوسط', hard: 'دشوار', mixed: 'ترکیبی' };
export const BLOOM_FA = { remember: 'یادآوری', understand: 'درک', apply: 'کاربرد', analyze: 'تحلیل', mixed: 'ترکیبی' };
export const SOURCE_FA = { ai: 'هوش مصنوعی', manual: 'دستی', bank: 'بانک', worksheet: 'کاربرگ', sample: 'نمونه', imported: 'واردشده' };
export const DIFF_COLOR = { easy: '#16a34a', medium: '#d97706', hard: '#dc2626' };

/** خلاصه‌ی یک‌خطیِ زمینه‌ی درسی: «کلاس چهارم الف · ریاضی · فصل ۳ — ضرب و تقسیم · ضرب» */
export function contextLine(ctx = {}, classes = []) {
    const cls = classes.find((c) => String(c.id) === String(ctx.classroom_id));
    return [
        cls ? `${cls.name}` : (ctx.grade ? `پایه‌ی ${ctx.grade}` : null),
        ctx.subject || null,
        ctx.chapterLabel || ctx.chapter || null,
        ctx.topic || null,
    ].filter(Boolean).join(' · ');
}

/** پارامترهای زمینه برای ارسال به سرور (بدونِ فیلدهای نمایشی). */
export function contextPayload(ctx = {}) {
    const out = {};
    ['classroom_id', 'grade', 'level', 'subject', 'book', 'chapter_id', 'chapter', 'topic', 'goal'].forEach((k) => {
        if (ctx[k] !== undefined && ctx[k] !== null && ctx[k] !== '') out[k] = ctx[k];
    });
    return out;
}
