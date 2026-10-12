import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import Mascot from '@/Components/Mascot';
import ErrorBoundary from '@/Components/ErrorBoundary';

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

/** هر خطایی در دستیار فقط خودِ دستیار را پنهان می‌کند، نه کلِ سایت را. */
export default function AssistantWidget(props) {
    return <ErrorBoundary name="assistant" fallback={null}><Assistant {...props} /></ErrorBoundary>;
}

function Assistant({ menu = [] }) {
    const { auth, assistantOn = true } = usePage().props;
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

    const boxRef = useRef(null);
    const taRef = useRef(null);
    const fabRef = useRef(null);
    const abortRef = useRef(null);
    const stopRef = useRef(null);
    const atEndRef = useRef(true);

    const started = msgs.length > 0;
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
    }, [toEnd]);

    const send = async (text) => {
        const message = (text ?? input).replace(/^[^\p{L}\p{N}]+/u, '').trim();
        if (!message || busy || typing) return;
        const history = msgs.filter((m) => !m.local).slice(-8).map(({ role: r, content }) => ({ role: r, content: String(content ?? '') }));
        setMsgs((m) => [...m, { id: uid(), role: 'user', content: message, at: Date.now() }]);
        setInput('');
        setBusy(true);
        atEndRef.current = true;
        const ctrl = new AbortController();
        abortRef.current = ctrl;
        try {
            const { data } = await axios.post(url('assistant.chat', '/assistant/chat'), { message, history }, { signal: ctrl.signal });
            const id = uid();
            setMsgs((m) => [...m, { id, role: 'assistant', content: data.reply, diag: data.diag, mode: data.mode, at: Date.now(), fresh: true }]);
            setTyping(id);
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
    const fabMood = open ? 'happy' : peek ? 'wave' : sleepy ? 'sleep' : 'idle';
    const canSend = input.trim() && !busy && !typing;

    return (
        <>
            {peek && !open && (
                <div className="mahoo-peek" role="status" style={peekAt ?? { visibility: 'hidden' }}>
                    <button type="button" className="mahoo-peek-x" onClick={hidePeek} aria-label="بستنِ پیام">×</button>
                    <button type="button" className="mahoo-peek-t" onClick={() => { hidePeek(); setOpen(true); }}>{(PEEK[role] ?? PEEK.default)(first)}</button>
                </div>
            )}

            <button ref={fabRef} type="button" onClick={() => { hidePeek(); setOpen((o) => !o); }} title="گفت‌وگو با ماهو، دستیارِ هوشمند"
                className={`assistant-fab mahoo-fab ${open ? 'is-open' : ''}`} aria-label="دستیار هوشمند" aria-expanded={open}>
                <Mascot mood={fabMood} size={58} />
                {!open && <span className="mahoo-fab-dot" aria-hidden="true" />}
            </button>

            {open && (
                <section className={`assistant-panel mahoo-panel ${wide ? 'is-wide' : ''}`} dir="rtl" role="dialog" aria-label="ماهو، دستیارِ هوشمند">
                    <header className="mh-head">
                        <div className="mh-avatar"><Mascot mood={mood} size={46} /></div>
                        <div className="mh-id">
                            <b>ماهو <span className="mh-badge">{hello?.ai ? '✨ هوشمند' : 'دستیار'}</span></b>
                            <span className={`mh-status ${busy || typing ? 'live' : ''}`}><i />{status}</span>
                        </div>
                        <button type="button" className="mh-hbtn" onClick={reset} title="گفت‌وگوی تازه" aria-label="گفت‌وگوی تازه"><Ic n="plus" s={19} /></button>
                        <button type="button" className="mh-hbtn mh-only-desk" onClick={() => setWide((w) => !w)} title={wide ? 'کوچک‌تر' : 'بزرگ‌تر'} aria-label={wide ? 'کوچک‌تر' : 'بزرگ‌تر'}><Ic n={wide ? 'shrink' : 'expand'} s={18} /></button>
                        <button type="button" className="mh-hbtn" onClick={() => setOpen(false)} title="بستن" aria-label="بستن"><Ic n="close" s={19} /></button>
                    </header>

                    <div className="mh-body" ref={boxRef} onScroll={onScroll} aria-live="polite">
                        {!started && (
                            <div className="mh-welcome">
                                <div className="mh-hero">
                                    <Mascot mood="wave" size={104} />
                                    <h3>سلام {first}!</h3>
                                    <p>{INTRO[role] ?? INTRO.default}</p>
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
