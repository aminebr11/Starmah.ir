import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { play, buzz, muted, setMuted, unlockSound } from '@/lib/mahooSound';
import axios from 'axios';
import Mascot from '@/Components/Mascot';
import ErrorBoundary from '@/Components/ErrorBoundary';
import '@/../css/assistant.css';

/**
 * «ماهو» — دستیارِ هوشمندِ ستاره‌ماه، در همه‌ی صفحه‌ها.
 *
 * - صفحه‌ی خوش‌آمد با «نمای من»: چند عددِ واقعی فقط از داده‌ای که همین
 *   کاربر حقِ دیدنش را دارد (سرور: AssistantService::snapshot).
 * - پاسخ‌ها کلمه‌به‌کلمه تایپ می‌شوند، با مکان‌نما؛ «توقف» هر لحظه کار می‌کند.
 * - ماهو زنده است: فکر می‌کند، حرف می‌زند، خوشحال می‌شود، دست تکان می‌دهد،
 *   با نشانگر نگاه می‌کند و اگر کسی سراغش نیاید چرت می‌زند.
 * - نامِ بخش‌های سایت در پاسخ («کارنامه»، «مأموریت‌های من»…) دکمه می‌شود.
 */

const ROLES = ['student', 'teacher', 'school_admin', 'parent', 'super_admin'];

const CARDS = {
    student: [
        { icon: '📊', title: 'گزارشِ تحلیلیِ من', sub: 'وضعیتم را کامل بگو', q: 'گزارش تحلیلی بده' },
        { icon: '🎯', title: 'امروز چه کنم؟', sub: 'برنامه‌ی کوتاهِ امروز', q: 'امروز چه کار کنم؟' },
        { icon: '📉', title: 'درسِ ضعیفم', sub: 'و یک تمرینِ کوچک', q: 'کدوم درسم ضعیفه؟' },
        { icon: '🏅', title: 'رتبه و امتیازم', sub: 'در کلاس و تیم', q: 'رتبه‌ام چنده و چقدر امتیاز دارم؟' },
    ],
    teacher: [
        { icon: '📊', title: 'وضعیتِ کلاس‌ها', sub: 'تحلیل با عدد', q: 'وضعیتِ کلاس‌هام چطوره؟' },
        { icon: '📉', title: 'ضعیف‌ترین درس‌ها', sub: 'و پیشنهادِ اقدام', q: 'ضعیف‌ترین درس‌های کلاسم کدومه و چه کنم؟' },
        { icon: '🎯', title: 'طرحِ یک مأموریت', sub: 'آماده‌ی ساخت', q: 'برای ضعیف‌ترین درس یک مأموریت طراحی کن' },
        { icon: '🏆', title: 'مسابقه‌ی زنده', sub: 'چطور بسازم و اجرا کنم', q: 'مسابقه‌ی زنده چطور بسازم؟' },
    ],
    school_admin: [
        { icon: '📊', title: 'گزارشِ مدرسه', sub: 'نمای کلی با عدد', q: 'گزارشِ مدرسه' },
        { icon: '📉', title: 'درس‌های ضعیفِ مدرسه', sub: 'و اقدامِ مدیریتی', q: 'درس‌های ضعیفِ مدرسه کدومه و چه کنم؟' },
        { icon: '👩‍🏫', title: 'مدیریتِ معلم‌ها', sub: 'کلاس و دسترسی', q: 'مدیریتِ معلم‌ها' },
        { icon: '📢', title: 'اطلاع‌رسانی', sub: 'اطلاعیه و پیامک', q: 'اطلاعیه چطور بگذارم؟' },
    ],
    parent: [
        { icon: '📊', title: 'وضعیتِ فرزندم', sub: 'خلاصه با عدد', q: 'وضعیتِ فرزندم چطوره؟' },
        { icon: '📉', title: 'درسِ نیازمندِ تمرین', sub: 'کدام درس؟', q: 'کدوم درسش ضعیفه؟' },
        { icon: '🏠', title: 'کمک در خانه', sub: 'سه کارِ ساده', q: 'در خانه چه کمکی کنم؟' },
        { icon: '✉️', title: 'گفت‌وگو با معلم', sub: 'از کجا پیام بدهم', q: 'چطور با معلم حرف بزنم؟' },
    ],
    super_admin: [
        { icon: '🩺', title: 'سلامتِ سیستم', sub: 'چه چیزی را چک کنم', q: 'سلامتِ سیستم را از کجا ببینم؟' },
        { icon: '🤖', title: 'هوش مصنوعی', sub: 'کلید و تنظیمات', q: 'کلیدِ هوش مصنوعی را کجا بگذارم؟' },
        { icon: '🏫', title: 'مدرسه‌ی تازه', sub: 'ساخت و اشتراک', q: 'مدرسه‌ی تازه چطور بسازم؟' },
        { icon: '📈', title: 'گزارش‌ها', sub: 'بازدید و فعالیت', q: 'گزارش‌ها' },
    ],
};

const CHIPS = {
    student: ['📊 گزارش تحلیلی بده', '🏅 رتبه‌ام چنده؟', '📉 کدوم درسم ضعیفه؟', '🎯 امروز چه کار کنم؟', '⚡ چقدر امتیاز دارم؟', '📔 نمره‌هام چیه؟'],
    teacher: ['📊 وضعیتِ کلاس‌هام چطوره؟', '📉 ضعیف‌ترین درس‌ها', '🎯 چطور مأموریت بسازم؟', '🎨 کاربرگ چطور بسازم؟'],
    school_admin: ['📊 گزارشِ مدرسه', '👩‍🏫 مدیریتِ معلم‌ها', '📢 اطلاعیه چطور بگذارم؟'],
    parent: ['📊 وضعیتِ فرزندم چطوره؟', '📉 کدوم درسش ضعیفه؟', '🏠 در خانه چه کمکی کنم؟', '✉️ چطور با معلم حرف بزنم؟'],
    super_admin: ['🩺 سلامتِ سیستم', '🤖 تنظیمِ هوش مصنوعی', '📈 گزارش‌ها'],
    default: ['راهنمای سایت', 'چطور امتیاز بگیرم؟'],
};

const INTRO = {
    student: 'من ماهو هستم، دوستِ درس‌خوانِ تو 🌙 از نمره و رتبه‌ات تا برنامه‌ی امروز، هرچه بخواهی از داده‌ی واقعیِ خودت می‌گویم.',
    teacher: 'من ماهو هستم، دستیارِ آموزشیِ شما. کلاس‌هایتان را تحلیل می‌کنم و برای درس‌های ضعیف مأموریت و محتوا پیشنهاد می‌دهم.',
    school_admin: 'من ماهو هستم، تحلیلگرِ مدرسه‌ی شما. نمای کلی، درس‌های ضعیف و اقدام‌های پیشنهادی را با عدد می‌گویم.',
    parent: 'من ماهو هستم. وضعیتِ درسیِ فرزندتان و راه‌های کمک در خانه را از داده‌ی واقعیِ او می‌گویم.',
    super_admin: 'من ماهو هستم. در راه‌اندازی، تنظیمات و گزارش‌های کلِ سامانه کنارتان هستم.',
    default: 'من ماهو هستم، دستیارِ هوشمندِ ستاره‌ماه. هر سؤالی داری بپرس.',
};

const THINKING = {
    student: ['دارم کارنامه‌ات را ورق می‌زنم…', 'نمره‌ها را کنارِ هم می‌گذارم…', 'یک پیشنهادِ خوب پیدا می‌کنم…'],
    teacher: ['کلاس‌ها را مرور می‌کنم…', 'درصدِ درس‌ها را مقایسه می‌کنم…', 'پیشنهاد را آماده می‌کنم…'],
    school_admin: ['آمارِ مدرسه را جمع می‌کنم…', 'درس‌ها را مقایسه می‌کنم…', 'جمع‌بندی می‌کنم…'],
    parent: ['پرونده‌ی فرزندتان را می‌خوانم…', 'درس‌ها را بررسی می‌کنم…', 'پیشنهاد را آماده می‌کنم…'],
    default: ['دارم فکر می‌کنم…', 'جواب را آماده می‌کنم…'],
};

const PEEK = {
    student: (n) => `سلام ${n}! 🌙 امروز با هم چی یاد بگیریم؟`,
    teacher: (n) => `سلام ${n} 👋 گزارشِ امروزِ کلاس‌ها آماده است.`,
    school_admin: (n) => `سلام ${n} 👋 نمای امروزِ مدرسه را ببینیم؟`,
    parent: (n) => `سلام ${n} 🌸 وضعیتِ فرزندتان را بپرسید.`,
    super_admin: (n) => `سلام ${n} 👋 کاری از دستم برمی‌آید؟`,
    default: (n) => `سلام ${n} 👋 سؤالی داری؟`,
};

/* واکنش به لمس — هر بار یکی، با صدا؛ پنج لمسِ تند = سرگیجه 😵‍💫 */
const REACT = [
    { mood: 'giggle', sfx: 'giggle', say: ['هی! قلقلکم میاد 😆', 'هههه! نکن دیگه 🤭', 'هی‌هی‌هی! 😂'] },
    { mood: 'surprised', sfx: 'boing', say: ['اِ! سلام! 😮', 'وای! یهو اومدی 🙀', 'بوینگ! 🫨'] },
    { mood: 'love', sfx: 'love', say: ['دوستت دارم 💛', 'تو بهترینی 💖', 'قلبم برات تاپ‌تاپ می‌کنه 💗'] },
    { mood: 'cheer', sfx: 'tada', say: ['هورا! 🎉', 'بزن قدش! ✋', 'امروز روزِ توئه! 🌟'] },
    { mood: 'wink', sfx: 'squeak', say: ['چشمک! 😉', 'یه راز: هر روز یه کم = کلی! 🤫', 'من حواسم بهت هست 😉'] },
];
const DIZZY = { mood: 'dizzy', sfx: 'dizzy', say: ['سرم گیج رفت! 😵‍💫', 'وااای... دور خودم چرخیدم 🌀'] };
const pick = (a) => a[Math.floor(Math.random() * a.length)];

/* «کارهایی که بلدم» — مثال برای هر کار؛ fill یعنی متن را در کادر بگذار تا کاربر کاملش کند */
const ABILITY_EX = {
    navigate: { student: 'برو به کارنامه', teacher: 'برو به مرکز امتیاز', school_admin: 'برو به اطلاعیه‌ها', parent: 'برو به اعلان‌ها', super_admin: 'برو به سلامت سیستم' },
    timer: { q: 'تایمر ۱۵ دقیقه' },
    fun: { q: 'یه چیستان بگو' },
    award: { fill: 'به  ۵ امتیاز بده', caret: 3 },
    class_notice: { fill: 'به کلاس بگو: ' },
    school_notice: { fill: 'اطلاعیه بده برای همه: ' },
    remedial: { q: 'از سؤال‌های سخت آزمون جبرانی بساز' },
};

// نام‌های رایجِ بخش‌ها که در پاسخ‌ها می‌آیند ولی عینِ عنوانِ منو نیستند
const ALIASES = {
    'آزمون هوشمند': ['/student/smart-exams', '/teacher/smart-exams'],
    'کاربرگ‌ها': ['/class-content?tab=homework', '/teacher/worksheet-inbox'],
    'ارتباط با معلم': ['/messages'],
    'ارتباط با والدین': ['/messages'],
    'اطلاعیه‌ها': ['/school/announcements', '/notices'],
    'گزارش‌ها': ['/teacher/reports', '/school/reports', '/admin/reports'],
    'سلامت سیستم': ['/admin/diagnostics'],
    'مرکز هوش مصنوعی': ['/admin/ai'],
};

const norm = (s) => String(s ?? '').replace(/[ً-ْ‌‏]/g, '').replace(/ي/g, 'ی').replace(/ك/g, 'ک')
    .replace(/[^\p{L}\p{N} ]/gu, ' ').replace(/\s+/g, ' ').trim();
const fa = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const reduced = () => typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
// اگر سرور هنوز مسیرِ تازه را نشناسد (فایل‌های قدیمی یا کشِ مسیرها)، route() خطا می‌دهد
// و پیش از این کلِ صفحه سفید می‌شد؛ حالا نشانیِ ثابت به کار می‌رود.
const url = (name, path) => { try { return route(name); } catch { return path; } };
const uid = () => Math.random().toString(36).slice(2, 10);
const clock = (t) => fa(new Date(t).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }));

/* ── آیکون‌های کنترل (SVG، نه ایموجی) ─────────────────────────────── */
const I = {
    send: <path d="M4 12 20 4l-4 16-4-7-8-1Z" />,
    stop: <rect x="6" y="6" width="12" height="12" rx="2.5" />,
    close: <path d="M6 6l12 12M18 6 6 18" />,
    plus: <path d="M12 5v14M5 12h14" />,
    expand: <path d="M4 10V4h6M20 14v6h-6M4 4l7 7M20 20l-7-7" />,
    shrink: <path d="M10 4v6H4M14 20v-6h6M10 10 3 3M14 14l7 7" />,
    copy: <><rect x="8" y="8" width="12" height="12" rx="2.5" /><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" /></>,
    check: <path d="m5 12 5 5 9-10" />,
    down: <path d="M12 5v14M6 13l6 6 6-6" />,
};
const Ic = ({ n, s = 20 }) => (
    <svg width={s} height={s} viewBox="0 0 24 24" fill={n === 'stop' ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="2.2"
        strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{I[n]}</svg>
);

/* ── نمایشِ پاسخ: تیتر، فهرست، پررنگ و پیوندِ بخش‌ها ─────────────────── */
function useLinker(menu) {
    return useMemo(() => {
        const items = (menu ?? []).filter((m) => m.href && m.label).map((m) => ({ key: norm(m.label), href: m.href, label: m.label }));
        const hrefs = new Set(items.map((m) => m.href.split('?')[0]));
        return (name) => {
            const k = norm(name);
            if (!k) return null;
            const hit = items.find((m) => m.key === k) || (k.length >= 4 && items.find((m) => m.key.includes(k) || k.includes(m.key)));
            if (hit) return hit.href;
            const alias = Object.entries(ALIASES).find(([a]) => norm(a) === k);
            return alias ? alias[1].find((h) => hrefs.has(h.split('?')[0])) ?? null : null;
        };
    }, [menu]);
}

function Inline({ text, link, onNav }) {
    const parts = String(text).split(/(\*\*[^*]+\*\*|«[^»\n]{1,40}»|\[[^\]\n]+\]\(\/[^)\s]*\))/g);
    return parts.map((p, i) => {
        if (p.startsWith('**') && p.endsWith('**') && p.length > 4) return <b key={i}>{p.slice(2, -2)}</b>;
        if (p.startsWith('«') && p.endsWith('»')) {
            const href = link(p.slice(1, -1));
            return href ? <Link key={i} href={href} className="as-link" onClick={onNav}>{p.slice(1, -1)}<span aria-hidden="true"> ↖</span></Link> : <span key={i}>{p}</span>;
        }
        const md = p.match(/^\[([^\]]+)\]\((\/[^)\s]*)\)$/);
        if (md) return <Link key={i} href={md[2]} className="as-link" onClick={onNav}>{md[1]}</Link>;
        return <span key={i}>{p}</span>;
    });
}

function Rich({ text, link, onNav, caret = false }) {
    // در حینِ تایپ ممکن است «**» هنوز بسته نشده باشد
    let src = String(text ?? '');
    if ((src.match(/\*\*/g) || []).length % 2) src = src.replace(/\*\*(?!.*\*\*)/s, '');
    const lines = src.replace(/\r/g, '').split('\n');
    const blocks = [];
    for (const raw of lines) {
        const line = raw.trimEnd();
        const bullet = line.match(/^\s*(?:[•\-*▪◦]|✅|✔️?)\s+(.*)$/);
        const num = line.match(/^\s*([0-9۰-۹]+)[).\-]\s+(.*)$/);
        const head = line.match(/^\s*(?:#{1,4}\s+(.+)|\*\*([^*]+)\*\*:?)\s*$/);
        const last = blocks[blocks.length - 1];
        if (bullet) {
            if (last?.t === 'ul') last.items.push(bullet[1]); else blocks.push({ t: 'ul', items: [bullet[1]] });
        } else if (num) {
            if (last?.t === 'ol') last.items.push(num[2]); else blocks.push({ t: 'ol', items: [num[2]] });
        } else if (head) {
            blocks.push({ t: 'h', text: head[1] ?? head[2] });
        } else if (line.trim() === '') {
            if (last && last.t !== 'gap') blocks.push({ t: 'gap' });
        } else if (last?.t === 'p') {
            last.text += '\n' + line;
        } else {
            blocks.push({ t: 'p', text: line });
        }
    }
    while (blocks.length && blocks[blocks.length - 1].t === 'gap') blocks.pop();
    const cur = <span className="as-caret" aria-hidden="true" />;
    return blocks.map((b, i) => {
        const end = caret && i === blocks.length - 1;
        if (b.t === 'gap') return <div key={i} className="as-gap" />;
        if (b.t === 'h') return <div key={i} className="as-h"><Inline text={b.text} link={link} onNav={onNav} />{end && cur}</div>;
        if (b.t === 'p') {
            const rows = b.text.split('\n');
            return (
                <p key={i}>
                    {rows.map((row, k) => {
                        const kv = row.match(/^([^:：«»*\n]{2,26})[:：]\s+(.+)$/);
                        return (
                            <span key={k} className={kv ? 'as-kv' : undefined}>
                                {kv ? <><span className="as-k">{kv[1]}</span><span className="as-v"><Inline text={kv[2]} link={link} onNav={onNav} /></span></> : <Inline text={row} link={link} onNav={onNav} />}
                                {k < rows.length - 1 && !kv && '\n'}
                            </span>
                        );
                    })}
                    {end && cur}
                </p>
            );
        }
        const Tag = b.t;
        return (
            <Tag key={i}>
                {b.items.map((it, k) => <li key={k}><Inline text={it} link={link} onNav={onNav} />{end && k === b.items.length - 1 && cur}</li>)}
            </Tag>
        );
    });
}

/** تایپِ کلمه‌به‌کلمه — مثلِ پاسخِ زنده؛ برای فارسی کلمه‌ای بهتر از حرفی است (شکلِ حروف نمی‌پرد). */
function Typed({ text, link, onNav, onDone, onTick, stopRef }) {
    const tokens = useMemo(() => String(text ?? '').split(/(\s+)/), [text]);
    const [n, setN] = useState(() => (reduced() ? tokens.length : 0));
    useEffect(() => {
        if (n >= tokens.length) return undefined;
        const words = tokens.length / 2;
        const total = Math.min(4200, Math.max(700, words * 45));   // میلی‌ثانیه برای کلِ پاسخ
        const per = Math.max(1, Math.round(tokens.length * 30 / total));
        let i = n;
        const t = setInterval(() => {
            i = Math.min(tokens.length, i + per);
            setN(i);
            onTick?.();
            if (i >= tokens.length) clearInterval(t);
        }, 30);
        stopRef.current = () => { clearInterval(t); setN(tokens.length); };
        return () => clearInterval(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tokens]);
    useEffect(() => { if (n >= tokens.length) onDone?.(); /* eslint-disable-next-line */ }, [n >= tokens.length]);
    return <Rich text={tokens.slice(0, n).join('')} link={link} onNav={onNav} caret={n < tokens.length} />;
}

/* ── جشنِ کوچک: ستاره و کاغذرنگی از یک نقطه ───────────────────────── */
function burst(x, y, n = 18) {
    if (typeof document === 'undefined' || reduced()) return;
    const box = document.createElement('div');
    box.className = 'mh-burst';
    box.style.left = `${x}px`; box.style.top = `${y}px`;
    const glyphs = ['✨', '⭐', '🌟', '💛', '🎉'];
    for (let i = 0; i < n; i++) {
        const p = document.createElement('i');
        const a = (Math.PI * 2 * i) / n + Math.random() * 0.4; const r = 70 + Math.random() * 90;
        p.textContent = i % 3 === 0 ? glyphs[i % glyphs.length] : '';
        p.style.setProperty('--dx', `${Math.cos(a) * r}px`); p.style.setProperty('--dy', `${Math.sin(a) * r - 40}px`);
        p.style.setProperty('--c', ['#ffd75e', '#ff8fa3', '#7c5cf0', '#34d399', '#60a5fa'][i % 5]);
        p.style.animationDelay = `${Math.random() * 80}ms`;
        box.appendChild(p);
    }
    document.body.appendChild(box);
    setTimeout(() => box.remove(), 1400);
}

/**
 * ورودِ نمایشی پس از ورود به حساب: ماهو از بالای صفحه می‌افتد، فرود می‌آید، دست تکان
 * می‌دهد و خوش‌آمد می‌گوید؛ بعد کوچک می‌شود و به جای دکمه‌اش پرواز می‌کند. لمس = رد شدن.
 */
function Entrance({ first, role, voice, fabRef, onDone }) {
    const [stage, setStage] = useState('fall');   // fall → hello → fly
    const meRef = useRef(null);
    const done = useRef(false);
    const audio = useRef(null);
    const line = role === 'student' ? 'امروز با هم کلی ستاره جمع می‌کنیم! ⭐' : role === 'parent' ? 'وضعیتِ فرزندتان را از من بپرسید 🌸' : 'هر کاری داشتید، من این‌جام ✨';

    const fly = useCallback(() => {
        if (done.current) return;
        done.current = true;
        setStage('fly');
        play('whoosh');
        const me = meRef.current; const fab = fabRef.current;
        if (me && fab && me.animate) {
            const a = me.getBoundingClientRect(); const b = fab.getBoundingClientRect();
            const dx = b.left + b.width / 2 - (a.left + a.width / 2); const dy = b.top + b.height / 2 - (a.top + a.height / 2);
            const anim = me.animate([{ transform: 'translate(0,0) scale(1) rotate(0)' }, { transform: `translate(${dx * 0.5}px,${dy * 0.5 - 80}px) scale(.7) rotate(-12deg)`, offset: 0.5 }, { transform: `translate(${dx}px,${dy}px) scale(.38) rotate(0)` }],
                { duration: 700, easing: 'cubic-bezier(.5,0,.3,1)', fill: 'forwards' });
            anim.onfinish = () => onDone();
        } else {
            onDone();
        }
    }, [fabRef, onDone]);

    useEffect(() => {
        play('whoosh');
        const t1 = setTimeout(() => {
            play('land'); buzz(25); setStage('hello');
            const r = meRef.current?.getBoundingClientRect();
            if (r) burst(r.left + r.width / 2, r.top + r.height * 0.75, 22);
            setTimeout(() => play('chime'), 180);
            if (voice) {
                axios.post(url('assistant.voice', '/assistant/voice')).then(({ data }) => {
                    if (data?.url && !done.current && !muted()) { audio.current = new Audio(data.url); audio.current.play().catch(() => {}); }
                }).catch(() => {});
            }
        }, 900);
        const t2 = setTimeout(fly, 4600);
        return () => { clearTimeout(t1); clearTimeout(t2); };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
    useEffect(() => () => audio.current?.pause(), []);

    return (
        <div className={`mh-enter is-${stage}`} onClick={fly} role="dialog" aria-label="خوش‌آمدِ ماهو">
            <div className="mh-enter-sky" aria-hidden="true">{Array.from({ length: 14 }, (_, i) => <i key={i} style={{ '--i': i }} />)}</div>
            <div className="mh-enter-me" ref={meRef}>
                <div className="mh-enter-trail" aria-hidden="true" />
                <Mascot mood={stage === 'fall' ? 'surprised' : stage === 'hello' ? 'wave' : 'happy'} size={170} />
            </div>
            {stage === 'hello' && (
                <div className="mh-enter-say">
                    <b>سلام {first}! 🌙</b>
                    <span>{line}</span>
                    <small>برای ادامه، جایی را لمس کن</small>
                </div>
            )}
        </div>
    );
}

/** تایمرِ درس/استراحت کنارِ دکمه‌ی ماهو — بینِ صفحه‌ها هم می‌ماند. */
const TIMER_KEY = 'mahoo-timer';
function readTimer() { try { const t = JSON.parse(localStorage.getItem(TIMER_KEY)); return t && t.end > Date.now() - 2000 ? t : null; } catch { return null; } }
function TimerPill({ timer, onCancel, style }) {
    const [, tick] = useState(0);
    useEffect(() => { const t = setInterval(() => tick((x) => x + 1), 1000); return () => clearInterval(t); }, []);
    const left = Math.max(0, Math.round((timer.end - Date.now()) / 1000));
    const pct = 1 - left / (timer.min * 60);
    return (
        <div className="mh-timer" style={style} role="timer" aria-label={`تایمرِ ${timer.label}`}>
            <svg viewBox="0 0 36 36" aria-hidden="true"><circle cx="18" cy="18" r="15" /><circle className="p" cx="18" cy="18" r="15" style={{ strokeDashoffset: 94.25 * (1 - pct) }} /></svg>
            <b>{fa(String(Math.floor(left / 60)).padStart(2, '0'))}:{fa(String(left % 60).padStart(2, '0'))}</b>
            <small>{timer.label === 'استراحت' ? '☕' : '📚'}</small>
            <button type="button" onClick={onCancel} aria-label="لغوِ تایمر">×</button>
        </div>
    );
}

/** کارتِ کار در گفت‌وگو: تأیید/انصراف، رفتن به صفحه، جوابِ چیستان. */
function ActionView({ m, link, onConfirm, onCancel, onNav }) {
    const a = m.action;
    const [show, setShow] = useState(false);
    if (!a) return null;
    if (a.type === 'navigate') {
        const href = link(a.target);
        return href
            ? <Link href={href} className="mh-act-go" onClick={onNav}>🧭 باز کردنِ «{a.target}» ↖</Link>
            : <div className="mh-act-note">این بخش را در منوی شما پیدا نکردم؛ نامش را از منو بگو.</div>;
    }
    if (a.type === 'riddle') {
        return show
            ? <div className="mh-act-answer">💡 جواب: <b>{a.answer}</b></div>
            : <button type="button" className="mh-act-go" onClick={(e) => { setShow(true); play('tada'); const r = e.currentTarget.getBoundingClientRect(); burst(r.left + r.width / 2, r.top, 14); }}>🤔 جواب را ببینم</button>;
    }
    if (a.type === 'timer') return <div className="mh-act-note">⏱️ تایمر کنارِ دکمه‌ی ماهو در حالِ شمارش است.</div>;
    if (a.type !== 'confirm') return null;
    return (
        <div className={`mh-act ${a.state ?? ''}`}>
            <div className="mh-act-h">{a.title}</div>
            <ul>{a.lines.map((l, i) => <li key={i}>{l}</li>)}</ul>
            {!a.state && (
                <div className="mh-act-b">
                    <button type="button" className="ok" onClick={(e) => onConfirm(m, e)}>{a.button}</button>
                    <button type="button" onClick={() => onCancel(m)}>انصراف</button>
                </div>
            )}
            {a.state === 'busy' && <div className="mh-act-s">در حالِ انجام… ⏳</div>}
            {a.state === 'done' && <div className="mh-act-s ok">✅ انجام شد{a.href ? <> · <Link href={a.href} onClick={onNav}>مشاهده ↖</Link></> : null}</div>}
            {a.state === 'fail' && <div className="mh-act-s bad">⛔ انجام نشد</div>}
            {a.state === 'cancel' && <div className="mh-act-s">انصراف داده شد</div>}
        </div>
    );
}

/** هر خطایی در دستیار فقط خودِ دستیار را پنهان می‌کند، نه کلِ سایت را. */
export default function AssistantWidget(props) {
    return <ErrorBoundary name="assistant" fallback={null}><Assistant {...props} /></ErrorBoundary>;
}

function Assistant({ menu = [] }) {
    const { auth, assistantOn = true, mahooLook } = usePage().props;
    const look = mahooLook ?? { entrance: true, sound: true, voice: true };
    const roles = auth?.roles ?? [];
    const role = ROLES.find((r) => roles.includes(r)) ?? 'default';
    const words = String(auth?.user?.name ?? '').trim().split(/\s+/).filter(Boolean);
    // «خانم احمدی» → «خانم احمدی»، نه فقط «خانم»
    const first = (/^(خانم|آقای|آقا|دکتر|استاد|سرکار)$/.test(words[0] ?? '') ? words.slice(0, 2).join(' ') : words[0]) || 'دوستِ من';
    const storeKey = `sm-mahoo:${auth?.user?.id ?? 'guest'}`;
    const link = useLinker(menu);

    const [open, setOpen] = useState(false);
    const [wide, setWide] = useState(false);
    const [msgs, setMsgs] = useState(() => {
        try { const s = sessionStorage.getItem(storeKey); if (s) return JSON.parse(s).map((m) => ({ ...m, fresh: false })); } catch { /* حافظه در دسترس نیست */ }
        return [];
    });
    const [input, setInput] = useState('');
    const [busy, setBusy] = useState(false);       // منتظرِ سرور
    const [typing, setTyping] = useState(null);     // شناسه‌ی پیامی که در حالِ تایپ است
    const [hello, setHello] = useState(null);
    const [think, setThink] = useState(0);
    const [joy, setJoy] = useState(false);
    const [peek, setPeek] = useState(false);
    const [sleepy, setSleepy] = useState(false);
    const [atEnd, setAtEnd] = useState(true);
    const [copied, setCopied] = useState(null);
    const [peekAt, setPeekAt] = useState(null);
    const [mute, setMute] = useState(() => muted());
    const [react, setReact] = useState(null);       // واکنشِ لمس: { mood, text, where, k }
    const [pos, setPos] = useState(() => { try { return JSON.parse(localStorage.getItem('mahoo-pos')) || null; } catch { return null; } });
    const [dragging, setDragging] = useState(false);
    const [landed, setLanded] = useState(0);
    const [timer, setTimer] = useState(() => (typeof window !== 'undefined' ? readTimer() : null));
    const [entering, setEntering] = useState(false);
    const [fabAt, setFabAt] = useState(null);

    const boxRef = useRef(null);
    const taRef = useRef(null);
    const fabRef = useRef(null);
    const abortRef = useRef(null);
    const stopRef = useRef(null);
    const atEndRef = useRef(true);

    const started = msgs.length > 0;
    const taps = useRef([]);
    const drag = useRef(null);
    const noClick = useRef(false);
    const autoNav = useRef(null);

    // ── صدا: تصمیمِ ادمین/مدرسه + انتخابِ خودِ کاربر
    useEffect(() => { window.__mahooSound = look.sound !== false; unlockSound(); }, [look.sound]);
    const toggleMute = () => { const v = !mute; setMute(v); setMuted(v); if (!v) play('pop', { force: true }); };

    // ── لمس = واکنش با صدا؛ پنج لمسِ تند = سرگیجه
    const poke = useCallback((where = 'fab') => {
        const now = Date.now();
        taps.current = [...taps.current.filter((t) => now - t < 2500), now];
        const r = taps.current.length >= 5 ? DIZZY : pick(REACT);
        if (r === DIZZY) taps.current = [];
        play(r.sfx); buzz(14);
        setReact({ mood: r.mood, text: pick(r.say), where, k: now });
    }, []);
    useEffect(() => {
        if (!react) return undefined;
        const t = setTimeout(() => setReact(null), react.mood === 'dizzy' ? 2600 : 2000);
        return () => clearTimeout(t);
    }, [react]);

    // ── ورودِ نمایشی، یک بار پس از ورود به حساب (و نه در هر برگه‌ی تازه)
    useEffect(() => {
        if (!assistantOn || !look.entrance || reduced()) return;
        const k = `mahoo-enter:${auth?.user?.id ?? 0}`;
        try {
            if (sessionStorage.getItem(k)) return;
            const last = Number(localStorage.getItem(k) || 0);
            sessionStorage.setItem(k, '1');
            if (Date.now() - last < 30 * 60 * 1000) return;
            localStorage.setItem(k, String(Date.now()));
        } catch { return; }
        const t = setTimeout(() => setEntering(true), 400);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [assistantOn, look.entrance]);

    // ── تایمر: پایانش با صدا و جشن
    useEffect(() => {
        if (!timer) return undefined;
        const left = timer.end - Date.now();
        const t = setTimeout(() => {
            try { localStorage.removeItem(TIMER_KEY); } catch { /* */ }
            setTimer(null);
            play('alarm', { force: false }); buzz(200);
            setReact({ mood: 'cheer', text: timer.label === 'استراحت' ? '⏰ استراحت تمام شد! برگردیم سرِ درس 💪' : `⏰ ${fa(timer.min)} دقیقه تمام شد! آفرین قهرمان 🎉`, where: 'fab', k: Date.now() });
            const r = fabRef.current?.getBoundingClientRect();
            if (r) burst(r.left + r.width / 2, r.top, 24);
        }, Math.max(0, left));
        return () => clearTimeout(t);
    }, [timer]);
    const startTimer = (min, label) => {
        const t = { end: Date.now() + min * 60000, min, label };
        try { localStorage.setItem(TIMER_KEY, JSON.stringify(t)); } catch { /* */ }
        setTimer(t);
    };
    const cancelTimer = () => { try { localStorage.removeItem(TIMER_KEY); } catch { /* */ } setTimer(null); play('pop'); };

    // ── جای دکمه (برای حباب‌ها و تایمر)
    useEffect(() => {
        const at = () => { const r = fabRef.current?.getBoundingClientRect(); if (r) setFabAt({ left: r.left, right: window.innerWidth - r.right, top: r.top, w: r.width, onLeft: r.left < window.innerWidth / 2 }); };
        at();
        window.addEventListener('resize', at);
        return () => window.removeEventListener('resize', at);
    }, [pos, landed, entering]);
    const beside = (gap = 12) => (fabAt ? { bottom: window.innerHeight - fabAt.top + gap, ...(fabAt.onLeft ? { left: Math.max(10, fabAt.left) } : { right: Math.max(10, fabAt.right) }) } : { visibility: 'hidden' });

    // ── جابه‌جا کردنِ ماهو با کشیدن؛ رها کردن = چسبیدن به نزدیک‌ترین لبه
    const onFabDown = (e) => {
        if (e.button && e.button !== 0) return;
        drag.current = { x: e.clientX, y: e.clientY, moved: false, long: false };
        drag.current.timer = setTimeout(() => { if (drag.current && !drag.current.moved) { drag.current.long = true; poke('fab'); } }, 480);
    };
    const onFabMove = (e) => {
        const d = drag.current; const el = fabRef.current;
        if (!d || !el) return;
        if (!d.moved && Math.hypot(e.clientX - d.x, e.clientY - d.y) > 8) {
            d.moved = true; clearTimeout(d.timer);
            try { el.setPointerCapture(e.pointerId); } catch { /* */ }
            setDragging(true); hidePeek(); play('whoosh');
        }
        if (d.moved) {
            const w = el.offsetWidth;
            el.style.left = `${Math.min(window.innerWidth - w - 4, Math.max(4, e.clientX - w / 2))}px`;
            el.style.top = `${Math.min(window.innerHeight - w - 4, Math.max(4, e.clientY - w / 2))}px`;
            el.style.right = 'auto'; el.style.bottom = 'auto'; el.style.insetInlineEnd = 'auto';
        }
    };
    const onFabUp = (e) => {
        const d = drag.current; drag.current = null;
        if (!d) return;
        clearTimeout(d.timer);
        if (d.moved) {
            const el = fabRef.current; const w = el?.offsetWidth ?? 66;
            const next = { side: e.clientX < window.innerWidth / 2 ? 'left' : 'right', bottom: Math.round(Math.min(window.innerHeight - w - 70, Math.max(12, window.innerHeight - e.clientY - w / 2))) };
            if (el) { el.style.left = ''; el.style.top = ''; el.style.right = ''; el.style.bottom = ''; el.style.insetInlineEnd = ''; }
            setPos(next); setDragging(false); setLanded(Date.now());
            try { localStorage.setItem('mahoo-pos', JSON.stringify(next)); } catch { /* */ }
            play('land'); buzz(20);
            noClick.current = true;
        } else if (d.long) {
            noClick.current = true;
        }
        // «کلیکِ» پس از رها کردن (اگر مرورگر بفرستد) نادیده گرفته شود، ولی لمسِ بعدی نه
        if (noClick.current) setTimeout(() => { noClick.current = false; }, 60);
    };
    // جای انتخابیِ کاربر با متغیرِ CSS (قاعده‌ی «!important»ِ طرح‌ها را هم کنار می‌زند)
    const fabStyle = pos ? { '--mh-b': `${pos.bottom}px` } : undefined;
    const panelStyle = pos ? { [pos.side]: 18, [pos.side === 'left' ? 'right' : 'left']: 'auto', insetInlineEnd: 'auto' } : undefined;

    const thinking = THINKING[role] ?? THINKING.default;

    // ── نگه‌داشتنِ گفت‌وگو بینِ صفحه‌ها
    useEffect(() => {
        try { sessionStorage.setItem(storeKey, JSON.stringify(msgs.slice(-24).map(({ fresh, ...m }) => m))); } catch { /* بی‌خیال */ }
    }, [msgs, storeKey]);

    // ── «نمای من» هنگامِ اولین بازشدن
    useEffect(() => {
        if (!open || hello) return;
        axios.get(url('assistant.hello', '/assistant/hello')).then(({ data }) => setHello(data)).catch(() => setHello({ stats: [] }));
    }, [open, hello]);

    // ── پیامِ کوتاهِ «سلام» کنارِ حباب، یک‌بار در هر نشست
    useEffect(() => {
        if (!assistantOn) return undefined;
        let seen = false;
        try { seen = sessionStorage.getItem('sm-mahoo-peek') === '1'; } catch { /* */ }
        if (seen) return undefined;
        const t = setTimeout(() => setPeek(true), 5000);
        const h = setTimeout(() => setPeek(false), 16000);
        return () => { clearTimeout(t); clearTimeout(h); };
    }, [assistantOn]);
    useEffect(() => { if (open) { try { sessionStorage.setItem('sm-mahoo-peek', '1'); } catch { /* */ } } }, [open]);
    // حباب کنارِ همان دکمه بنشیند، هر جا که طرحِ صفحه آن را گذاشته باشد
    useEffect(() => {
        if (!peek || !fabRef.current) return;
        const r = fabRef.current.getBoundingClientRect();
        const bottom = window.innerHeight - r.top + 12;
        setPeekAt(r.left < window.innerWidth / 2 ? { left: Math.max(10, r.left), bottom } : { right: Math.max(10, window.innerWidth - r.right), bottom });
    }, [peek]);
    const hidePeek = () => { setPeek(false); try { sessionStorage.setItem('sm-mahoo-peek', '1'); } catch { /* */ } };

    // ── چرت‌زدن وقتی کسی سراغش نمی‌آید + نگاه با نشانگر (فقط رایانه)
    useEffect(() => {
        let idle;
        const wake = () => { setSleepy(false); clearTimeout(idle); idle = setTimeout(() => setSleepy(true), 90000); };
        let raf = 0;
        const look = (e) => {
            wake();
            if (raf || !fabRef.current) return;
            raf = requestAnimationFrame(() => {
                raf = 0;
                const el = fabRef.current; if (!el) return;
                const r = el.getBoundingClientRect();
                const dx = e.clientX - (r.left + r.width / 2); const dy = e.clientY - (r.top + r.height / 2);
                const d = Math.hypot(dx, dy) || 1; const k = Math.min(1, d / 260);
                el.style.setProperty('--lx', `${(dx / d) * 2.6 * k}px`);
                el.style.setProperty('--ly', `${(dy / d) * 2.6 * k}px`);
            });
        };
        wake();
        window.addEventListener('pointermove', look, { passive: true });
        window.addEventListener('keydown', wake);
        window.addEventListener('touchstart', wake, { passive: true });
        return () => { clearTimeout(idle); cancelAnimationFrame(raf); window.removeEventListener('pointermove', look); window.removeEventListener('keydown', wake); window.removeEventListener('touchstart', wake); };
    }, []);

    // ── متنِ «در حالِ فکر» عوض شود
    useEffect(() => {
        if (!busy) return undefined;
        setThink(0);
        const t = setInterval(() => setThink((x) => x + 1), 1600);
        return () => clearInterval(t);
    }, [busy]);

    // ── Esc می‌بندد؛ روی گوشی پشتِ ورقه اسکرول نشود
    useEffect(() => {
        if (!open) return undefined;
        const onKey = (e) => { if (e.key === 'Escape') setOpen(false); };
        window.addEventListener('keydown', onKey);
        document.documentElement.classList.add('mahoo-open');
        setTimeout(() => { if (window.matchMedia('(pointer: fine)').matches) taRef.current?.focus(); }, 250);
        return () => { window.removeEventListener('keydown', onKey); document.documentElement.classList.remove('mahoo-open'); };
    }, [open]);

    // ── پیمایش: تا کاربر خودش بالا نرفته، همیشه پایین بمانیم
    const toEnd = useCallback((force = false) => {
        const b = boxRef.current; if (!b) return;
        if (force || atEndRef.current) b.scrollTop = b.scrollHeight;
    }, []);
    useEffect(() => { toEnd(true); }, [open, msgs.length, busy, toEnd]);
    const onScroll = () => {
        const b = boxRef.current; if (!b) return;
        const end = b.scrollHeight - b.scrollTop - b.clientHeight < 40;
        atEndRef.current = end; setAtEnd(end);
    };

    // ── اندازه‌ی خودکارِ کادرِ نوشتن
    useEffect(() => {
        const t = taRef.current; if (!t) return;
        t.style.height = 'auto'; t.style.height = `${Math.min(130, t.scrollHeight)}px`;
    }, [input, open]);

    const finishTyping = useCallback(() => {
        setTyping(null);
        setTimeout(() => toEnd(), 40);
        setJoy(true);
        setTimeout(() => setJoy(false), 1800);
        // «برو به …»: بعد از نوشتنِ پاسخ، خودش صفحه را باز می‌کند
        const nav = autoNav.current; autoNav.current = null;
        if (nav) {
            setTimeout(() => {
                play('whoosh');
                if (window.matchMedia('(max-width: 560px)').matches) setOpen(false);
                router.visit(nav);
            }, 650);
        }
    }, [toEnd]);

    const send = async (text) => {
        const message = (text ?? input).replace(/^[^\p{L}\p{N}]+/u, '').trim();
        if (!message || busy || typing) return;
        const history = msgs.filter((m) => !m.local).slice(-8).map(({ role: r, content }) => ({ role: r, content: String(content ?? '') }));
        setMsgs((m) => [...m, { id: uid(), role: 'user', content: message, at: Date.now() }]);
        setInput('');
        setBusy(true);
        play('send');
        atEndRef.current = true;
        const ctrl = new AbortController();
        abortRef.current = ctrl;
        try {
            const { data } = await axios.post(url('assistant.chat', '/assistant/chat'), { message, history }, { signal: ctrl.signal });
            const id = uid();
            const action = data.action ?? null;
            if (action?.type === 'timer') startTimer(action.minutes, action.label);
            if (action?.type === 'navigate') autoNav.current = link(action.target);
            setMsgs((m) => [...m, { id, role: 'assistant', content: data.reply, diag: data.diag, mode: data.mode, action, at: Date.now(), fresh: true }]);
            setTyping(id);
            play(action?.type === 'confirm' ? 'squeak' : 'msg');
        } catch (err) {
            if (axios.isCancel?.(err) || err?.name === 'CanceledError') {
                setMsgs((m) => [...m, { id: uid(), role: 'assistant', content: '⏹ باشد، نگه داشتم. هر وقت خواستی دوباره بپرس.', local: true, at: Date.now() }]);
            } else {
                const s = err?.response?.status;
                const known = {
                    403: err?.response?.data?.reply || 'دستیار برای حسابِ شما بسته است. از مدیرِ مدرسه بخواهید بازش کند.',
                    419: 'نشستِ شما منقضی شده. یک‌بار صفحه را تازه کن و دوباره بپرس.',
                    429: 'کمی تند پرسیدی 🙂 چند لحظه صبر کن و دوباره امتحان کن.',
                    401: 'برای گفت‌وگو با ماهو باید دوباره وارد حساب شوی.',
                };
                const msg = known[s]
                    || (s >= 500 ? `خطای سرور (کد ${fa(s)}). اگر تکرار شد به مدیرِ مدرسه اطلاع بده.` : null)
                    || (err?.message === 'Network Error' ? 'اینترنتت قطع است یا سرور در دسترس نیست.' : null)
                    || 'الان نتوانستم پاسخ بدهم. چند لحظه بعد دوباره امتحان کن.';
                setMsgs((m) => [...m, { id: uid(), role: 'assistant', content: msg, local: true, err: true, at: Date.now() }]);
            }
        } finally {
            abortRef.current = null;
            setBusy(false);
        }
    };

    // «✅ انجام بده»: فقط با تأییدِ خودِ کاربر؛ سرور دوباره اجازه را بررسی می‌کند
    const setAct = (id, patch) => setMsgs((list) => list.map((x) => (x.id === id ? { ...x, action: { ...x.action, ...patch } } : x)));
    const confirmAct = async (m, e) => {
        const r = e?.currentTarget?.getBoundingClientRect();
        setAct(m.id, { state: 'busy' });
        try {
            const { data } = await axios.post(url('assistant.act', '/assistant/act'), { token: m.action.token });
            setAct(m.id, { state: data.ok ? 'done' : 'fail', href: data.href, token: null });
            setMsgs((list) => [...list, { id: uid(), role: 'assistant', content: data.reply, local: true, err: !data.ok, at: Date.now() }]);
            if (data.ok) { play('tada'); if (r) burst(r.left + r.width / 2, r.top, 22); setJoy(true); setTimeout(() => setJoy(false), 1800); } else play('boing');
        } catch {
            setAct(m.id, { state: 'fail' });
            setMsgs((list) => [...list, { id: uid(), role: 'assistant', content: 'الان نشد؛ چند لحظه بعد دوباره امتحان کنید.', local: true, err: true, at: Date.now() }]);
        }
    };
    const cancelAct = (m) => { setAct(m.id, { state: 'cancel', token: null }); play('pop'); };

    // «کارهایی که بلدم»: مثال را بفرست یا در کادر بگذار تا کامل شود
    const tryAbility = (ab) => {
        const ex = ABILITY_EX[ab.key];
        if (!ex) return;
        if (ex.fill) {
            setInput(ex.fill);
            setTimeout(() => { const t = taRef.current; if (t) { t.focus(); const p = ex.caret ?? ex.fill.length; t.setSelectionRange(p, p); } }, 60);
            return;
        }
        send(ex.q ?? ex[role] ?? ex.student);
    };

    const stop = () => {
        if (busy) abortRef.current?.abort();
        else if (typing) stopRef.current?.();
    };

    const reset = () => {
        stop();
        setTyping(null);
        setMsgs([]);
        setInput('');
    };

    const copy = async (m) => {
        const plain = String(m.content).replace(/\*\*/g, '');
        try { await navigator.clipboard.writeText(plain); } catch {
            const ta = document.createElement('textarea'); ta.value = plain; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch { /* */ } ta.remove();
        }
        setCopied(m.id); setTimeout(() => setCopied(null), 1600);
    };

    const onNav = () => { if (window.matchMedia('(max-width: 560px)').matches) setOpen(false); };

    if (!assistantOn) return null;

    const mood = busy ? 'think' : typing ? 'talk' : joy ? 'happy' : 'idle';
    const status = busy ? 'در حال فکر کردن…' : typing ? 'در حال نوشتن…' : 'آنلاین · آماده‌ی کمک';
    const asked = new Set(msgs.filter((m) => m.role === 'user').map((m) => norm(m.content)));
    const chips = (CHIPS[role] ?? CHIPS.default).filter((c) => !asked.has(norm(c)));
    const cards = CARDS[role] ?? [];
    const fabMood = react?.where === 'fab' ? react.mood : dragging ? 'surprised' : open ? 'happy' : timer ? 'think' : peek ? 'wave' : sleepy ? 'sleep' : 'idle';
    const heroMood = react?.where === 'hero' ? react.mood : 'wave';
    const headMood = react && react.where !== 'fab' ? react.mood : mood;
    const abilities = hello?.abilities ?? [];
    const canSend = input.trim() && !busy && !typing;

    return (
        <>
            {entering && <Entrance first={first} role={role} voice={look.voice} fabRef={fabRef} onDone={() => { setEntering(false); setLanded(Date.now()); play('pop'); }} />}

            {timer && !open && !entering && <TimerPill timer={timer} onCancel={cancelTimer} style={beside(react || peek ? 70 : 12)} />}

            {react?.where === 'fab' && !open && (
                <div key={react.k} className="mahoo-say" role="status" style={beside(timer ? 64 : 14)}>{react.text}</div>
            )}

            {peek && !open && !react && (
                <div className="mahoo-peek" role="status" style={peekAt ?? { visibility: 'hidden' }}>
                    <button type="button" className="mahoo-peek-x" onClick={hidePeek} aria-label="بستنِ پیام">×</button>
                    <button type="button" className="mahoo-peek-t" onClick={() => { hidePeek(); setOpen(true); }}>{(PEEK[role] ?? PEEK.default)(first)}</button>
                </div>
            )}

            <button ref={fabRef} type="button" title="گفت‌وگو با ماهو — بکشید تا جابه‌جا شود"
                onClick={() => {
                    if (noClick.current) { noClick.current = false; return; }
                    hidePeek();
                    if (!open) { poke('panel'); play('pop'); }
                    setOpen((o) => !o);
                }}
                onPointerDown={onFabDown} onPointerMove={onFabMove} onPointerUp={onFabUp} onPointerCancel={onFabUp}
                onContextMenu={(e) => e.preventDefault()}
                style={fabStyle}
                className={`assistant-fab mahoo-fab ${open ? 'is-open' : ''} ${dragging ? 'is-drag' : ''} ${entering ? 'is-hidden' : ''} ${pos && !dragging ? `is-placed on-${pos.side}` : ''}`} aria-label="دستیار هوشمند" aria-expanded={open}>
                <span key={landed} className="mahoo-fab-in"><Mascot mood={fabMood} size={58} /></span>
                {!open && <span className="mahoo-fab-dot" aria-hidden="true" />}
            </button>

            {open && (
                <section className={`assistant-panel mahoo-panel ${wide ? 'is-wide' : ''}`} style={panelStyle} dir="rtl" role="dialog" aria-label="ماهو، دستیارِ هوشمند">
                    <header className="mh-head">
                        <div className="mh-head-stars" aria-hidden="true"><i /><i /><i /><i /><i /></div>
                        <button type="button" className="mh-avatar" onClick={() => poke('head')} aria-label="ماهو را لمس کن"><Mascot mood={headMood} size={46} /></button>
                        <div className="mh-id">
                            <b>ماهو <span className="mh-badge">{hello?.ai ? '✨ هوشمند' : 'دستیار'}</span></b>
                            <span className={`mh-status ${busy || typing ? 'live' : ''}`}><i />{status}</span>
                        </div>
                        {look.sound !== false && (
                            <button type="button" className="mh-hbtn" onClick={toggleMute} title={mute ? 'روشن کردنِ صدا' : 'بی‌صدا'} aria-label={mute ? 'روشن کردنِ صدا' : 'بی‌صدا'}>
                                <span aria-hidden="true" style={{ fontSize: 17 }}>{mute ? '🔇' : '🔊'}</span>
                            </button>
                        )}
                        <button type="button" className="mh-hbtn" onClick={reset} title="گفت‌وگوی تازه" aria-label="گفت‌وگوی تازه"><Ic n="plus" s={19} /></button>
                        <button type="button" className="mh-hbtn mh-only-desk" onClick={() => setWide((w) => !w)} title={wide ? 'کوچک‌تر' : 'بزرگ‌تر'} aria-label={wide ? 'کوچک‌تر' : 'بزرگ‌تر'}><Ic n={wide ? 'shrink' : 'expand'} s={18} /></button>
                        <button type="button" className="mh-hbtn" onClick={() => setOpen(false)} title="بستن" aria-label="بستن"><Ic n="close" s={19} /></button>
                    </header>

                    <div className="mh-body" ref={boxRef} onScroll={onScroll} aria-live="polite">
                        {!started && (
                            <div className="mh-welcome">
                                <div className="mh-hero">
                                    <button type="button" className="mh-hero-me" onClick={() => poke('hero')} aria-label="ماهو را لمس کن">
                                        <Mascot mood={heroMood} size={112} />
                                        {react?.where === 'hero' && <span key={react.k} className="mh-hero-say">{react.text}</span>}
                                    </button>
                                    <h3>سلام {first}!</h3>
                                    <p>{INTRO[role] ?? INTRO.default}</p>
                                    <small className="mh-hero-tip">👆 به من دست بزن! · می‌توانی دکمه‌ام را بکشی و هر جا بگذاری</small>
                                </div>
                                {hello === null && <div className="mh-stats"><div className="mh-stat sk" /><div className="mh-stat sk" /><div className="mh-stat sk" /></div>}
                                {hello?.stats?.length > 0 && (
                                    <>
                                        <div className="mh-label">نمای من · فقط داده‌ای که شما اجازه‌ی دیدنش را دارید</div>
                                        <div className="mh-stats">
                                            {hello.stats.map((s, i) => (
                                                <div key={i} className="mh-stat" style={{ animationDelay: `${i * 70}ms` }}>
                                                    <span className="ic">{s.icon}</span><b>{s.value}</b><small>{s.label}</small>
                                                </div>
                                            ))}
                                        </div>
                                    </>
                                )}
                                {abilities.length > 0 && (
                                    <>
                                        <div className="mh-label">🛠️ کارهایی که برایت انجام می‌دهم</div>
                                        <div className="mh-abls">
                                            {abilities.map((a, i) => (
                                                <button key={a.key} type="button" className="mh-abl" style={{ animationDelay: `${80 + i * 60}ms` }} onClick={() => tryAbility(a)} title={a.desc}>{a.title}</button>
                                            ))}
                                        </div>
                                    </>
                                )}
                                {cards.length > 0 && <div className="mh-label">از این‌جا شروع کن</div>}
                                <div className="mh-cards">
                                    {cards.map((c, i) => (
                                        <button key={c.title} type="button" className="mh-card" style={{ animationDelay: `${120 + i * 70}ms` }} onClick={() => send(c.q)}>
                                            <span className="ic">{c.icon}</span><b>{c.title}</b><small>{c.sub}</small>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {msgs.map((m) => (m.role === 'user' ? (
                            <div key={m.id} className="mh-row me">
                                <div className="mh-bubble">{m.content}</div>
                                <time>{clock(m.at)}</time>
                            </div>
                        ) : (
                            <div key={m.id} className={`mh-row bot ${m.err ? 'err' : ''}`}>
                                <div className="mh-mini"><Mascot mood={typing === m.id ? 'talk' : 'idle'} size={30} /></div>
                                <div className="mh-msg">
                                    <div className="mh-text">
                                        {typing === m.id
                                            ? <Typed text={m.content} link={link} onNav={onNav} onDone={finishTyping} onTick={() => toEnd()} stopRef={stopRef} />
                                            : <Rich text={m.content} link={link} onNav={onNav} />}
                                    </div>
                                    {m.diag && <div className="mh-diag">⚠️ {m.diag}</div>}
                                    {m.action && typing !== m.id && <ActionView m={m} link={link} onConfirm={confirmAct} onCancel={cancelAct} onNav={onNav} />}
                                    {typing !== m.id && (
                                        <div className="mh-tools">
                                            <time>{clock(m.at)}</time>
                                            {m.mode === 'ai' && <span className="mh-ai">✨ هوش مصنوعی</span>}
                                            {!m.local && (
                                                <button type="button" onClick={() => copy(m)} aria-label="کپی">
                                                    <Ic n={copied === m.id ? 'check' : 'copy'} s={15} />{copied === m.id ? 'کپی شد' : 'کپی'}
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        )))}

                        {busy && (
                            <div className="mh-row bot">
                                <div className="mh-mini"><Mascot mood="think" size={30} /></div>
                                <div className="mh-msg mh-thinking">
                                    <span className="mh-dots3"><i /><i /><i /></span>
                                    <span key={think} className="mh-think-t">{thinking[think % thinking.length]}</span>
                                </div>
                            </div>
                        )}
                    </div>

                    {!atEnd && started && (
                        <button type="button" className="mh-jump" onClick={() => { atEndRef.current = true; setAtEnd(true); toEnd(true); }} aria-label="رفتن به آخرین پیام"><Ic n="down" s={18} /></button>
                    )}

                    <footer className="mh-foot">
                        {started && chips.length > 0 && (
                            <div className="mh-chips">
                                {chips.map((h) => <button key={h} type="button" onClick={() => send(h)} disabled={busy || !!typing}>{h}</button>)}
                            </div>
                        )}
                        <form className="mh-compose" onSubmit={(e) => { e.preventDefault(); send(); }}>
                            <textarea ref={taRef} rows={1} value={input} maxLength={1000} onChange={(e) => setInput(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing && window.matchMedia('(pointer: fine)').matches) { e.preventDefault(); send(); }
                                }}
                                placeholder={`از ماهو بپرس، ${first}…`} aria-label="پیام به ماهو" />
                            {busy || typing
                                ? <button type="button" className="mh-send stop" onClick={stop} aria-label="توقف" title="توقف"><Ic n="stop" s={16} /></button>
                                : <button type="submit" className="mh-send" disabled={!canSend} aria-label="ارسال" title="ارسال"><Ic n="send" s={19} /></button>}
                        </form>
                        <div className="mh-note">
                            {input.length > 800 ? <span>{fa(input.length)} / {fa(1000)}</span> : <span>ماهو فقط اطلاعاتِ حسابِ خودِ شما را می‌بیند.</span>}
                        </div>
                    </footer>
                </section>
            )}
        </>
    );
}
