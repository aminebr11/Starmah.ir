/**
 * رنگ و شکلکِ ثابت برای هر درس — در همه‌ی صفحه‌های برنامه‌ی کلاسی یکی است.
 *
 * قبلاً رنگ از «هشِ» نامِ درس و از میانِ فقط ۸ رنگ انتخاب می‌شد، پس دو درسِ
 * متفاوت زیاد هم‌رنگ می‌شدند. حالا هر درسِ شناخته‌شده رنگِ خودش را دارد و
 * درس‌های ناشناخته در هر صفحه به ترتیب از رنگ‌هایی می‌گیرند که هنوز استفاده
 * نشده‌اند؛ یعنی در یک برنامه هیچ دو درسی هم‌رنگ نمی‌شوند (تا ۲۴ درس).
 */
const S = (re, emoji, a, b, ink = '#fff') => ({ re, emoji, a, b, ink });

export const SUBJECTS = [
    S(/ریاض|حساب|هندسه/, '🧮', '#5b8cff', '#2e5be6'),
    S(/علوم|تجربی|زیست|فیزیک|شیمی/, '🔬', '#3ad68a', '#14a85c'),
    S(/املا/, '✍️', '#c77dff', '#9439e8'),
    S(/نگارش|بنویسیم|انشا/, '📝', '#ff7a9c', '#e8406b'),
    S(/فارسی|بخوانیم|خوانداری|ادبیات/, '📖', '#ffa24c', '#f2741d'),
    S(/اجتماع|مطالعات|تاریخ|جغراف|مدنی/, '🌍', '#2fd0cf', '#0e9aa8'),
    S(/قرآن/, '📗', '#a6dc3c', '#6fa915', '#21340a'),
    S(/هدیه|دینی|دین|پیامها|احکام/, '🕌', '#8a7bff', '#5a45e0'),
    S(/هنر|نقاشی|کاردستی|خوشنویس/, '🎨', '#ff6ad5', '#d936ad'),
    S(/ورزش|تربیت بدنی|بدنی/, '⚽', '#ff6b5e', '#e2382d'),
    S(/انگلیس|زبان خارجه|english/i, '🔤', '#45c8ff', '#0d93d8'),
    S(/عربی/, '🌙', '#e0a46a', '#b8712f'),
    S(/تفکر|پژوهش|سبک زندگی/, '💡', '#ffd23f', '#f2ac00', '#3a2a00'),
    S(/کار و فناوری|فناوری|رایانه|کامپیوتر|رباتیک/, '💻', '#7f9cc4', '#4e6b95'),
    S(/موسیقی|سرود/, '🎵', '#ff8fb8', '#ef5a92'),
    S(/آزمایش|آزمایشگاه/, '🧪', '#5fe0b8', '#1eaf86'),
    S(/کتابخوانی|کتاب‌خوانی|کتابخانه|قصه/, '📚', '#ffb36b', '#e98a2c'),
    S(/مشاوره|پرورشی/, '🤝', '#9fb0ff', '#6a7ff0'),
];

// برای درس‌های ناشناخته — رنگ‌هایی که با درس‌های بالا اشتباه گرفته نمی‌شوند
const EXTRA = [
    ['#ff8c69', '#e85f3a'], ['#4dd9e8', '#13a7c0'], ['#b388ff', '#7f4ff0'], ['#7ee081', '#3fae45'],
    ['#ffb4d4', '#f07aac'], ['#6fa8ff', '#3d73e8'], ['#f5c26b', '#d99426'], ['#8ce0d0', '#3fb7a1'],
];

const norm = (s) => String(s || '').replace(/[‌‏‎]/g, '').replace(/ي/g, 'ی').replace(/ك/g, 'ک').trim();
const known = (title) => SUBJECTS.find((x) => x.re.test(norm(title)));

/** پالتِ یک صفحه: درس‌های ناشناخته به ترتیبِ ظاهرشدن رنگِ یکتا می‌گیرند. */
export function makeSubjectPalette(titles = []) {
    const map = new Map();
    let n = 0;
    [...new Set(titles.map(norm).filter(Boolean))].forEach((t) => {
        if (known(t)) return;
        const [a, b] = EXTRA[n % EXTRA.length];
        map.set(t, { emoji: ['🌟', '🚀', '🧩', '🪁', '🎯', '🌈', '🦋', '🍀'][n % 8], a, b, ink: '#fff' });
        n++;
    });

    return (title) => {
        const t = norm(title);
        const k = known(t) || map.get(t) || { emoji: '📘', a: '#8fa3c7', b: '#5f749c', ink: '#fff' };
        return { ...k, grad: `linear-gradient(135deg, ${k.a}, ${k.b})` };
    };
}

/** همه‌ی عنوان‌های درس از یک برنامه‌ی هفتگی ({0: [...], 1: [...]}) */
export const titlesOf = (entries = {}) => Object.values(entries).flat().filter((e) => e && e.kind !== 'recess').map((e) => e.title);

/** رنگِ روزهای هفته (سرِ ستون‌ها) — شنبه تا جمعه */
export const DAY_TINTS = ['#ff9f43', '#5b8cff', '#3ad68a', '#ff6ad5', '#8a7bff', '#2fd0cf', '#ff6b5e'];

/** «الان» و «بعدی»: از رشته‌ی «08:00 - 08:45» */
export function lessonState(time, isToday) {
    if (!isToday || !time) return null;
    const latin = String(time).replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
    const m = latin.match(/(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})/);
    if (!m) return null;
    const now = new Date();
    const cur = now.getHours() * 60 + now.getMinutes();
    const s = +m[1] * 60 + +m[2], e = +m[3] * 60 + +m[4];
    if (cur >= s && cur < e) return 'now';
    if (cur < s) return 'later';
    return 'done';
}
