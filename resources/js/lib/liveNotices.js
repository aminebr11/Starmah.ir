import { router } from '@inertiajs/react';
import axios from 'axios';

/**
 * اعلانِ زنده — زنگوله بدونِ رفرشِ دستی به‌روز می‌شود.
 *
 * هر ۱۵ ثانیه (فقط وقتی برگه دیده می‌شود) «نبضِ» اعلان‌ها خوانده می‌شود. اگر
 * فید تغییر کرده باشد، فقط داده‌های زنگوله با reloadِ جزئیِ Inertia تازه
 * می‌شوند (فرم‌ها و اسکرولِ صفحه دست نمی‌خورند) و برای اعلانِ تازه یک
 * پیامِ شناور + لرزش + صدای کوتاه نشان داده می‌شود. با برگشتن به برگه،
 * فوکوس یا وصل‌شدنِ دوباره‌ی اینترنت، بی‌درنگ بررسی می‌شود.
 *
 * یگانه است: هر چند زنگوله در صفحه باشد، فقط یک حلقه اجرا می‌شود.
 */
const EVERY = 15000;
const FULL_RELOAD = ['/notices', '/messages', '/family-notes'];
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

let started = false;
let timer = null;
let sig = null;
let lastTop = null;
let busy = false;         // یک بازدیدِ Inertia (مثلاً ذخیره‌ی فرم) در جریان است
let failures = 0;

function schedule(ms = EVERY) {
    clearTimeout(timer);
    if (typeof document !== 'undefined' && document.hidden) return; // برگه پنهان است → صبر تا برگشت
    timer = setTimeout(pulse, ms);
}

function setBadge(count) {
    try {
        const t = document.title.replace(/^\(\S+\)\s*/, '');
        document.title = count > 0 ? `(${fa(count)}) ${t}` : t;
        if ('setAppBadge' in navigator) (count > 0 ? navigator.setAppBadge(count) : navigator.clearAppBadge()).catch(() => {});
    } catch { /* نادیده */ }
}

function ding() {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        const ctx = new Ctx();
        const o = ctx.createOscillator(); const g = ctx.createGain();
        o.type = 'sine'; o.frequency.setValueAtTime(880, ctx.currentTime); o.frequency.exponentialRampToValueAtTime(1320, ctx.currentTime + 0.12);
        g.gain.setValueAtTime(0.0001, ctx.currentTime); g.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
        o.connect(g).connect(ctx.destination); o.start(); o.stop(ctx.currentTime + 0.4);
        setTimeout(() => ctx.close().catch(() => {}), 600);
    } catch { /* مرورگر اجازه نداد */ }
}

function toast(top, count) {
    document.querySelectorAll('.live-toast').forEach((el) => el.remove());
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'live-toast';
    el.setAttribute('role', 'status');
    const ic = document.createElement('span'); ic.className = 'lt-ic'; ic.textContent = top.icon || '🔔';
    const tx = document.createElement('span'); tx.className = 'lt-tx';
    const b = document.createElement('b'); b.textContent = top.title || 'اعلانِ تازه';
    tx.appendChild(b);
    if (top.body) { const s = document.createElement('small'); s.textContent = top.body; tx.appendChild(s); }
    const more = document.createElement('em'); more.className = 'lt-more';
    more.textContent = count > 1 ? `${fa(count)} خوانده‌نشده` : 'مشاهده';
    const x = document.createElement('span'); x.className = 'lt-x'; x.textContent = '×'; x.setAttribute('aria-label', 'بستن');
    el.append(ic, tx, more, x);
    let gone = false;
    const close = () => { if (gone) return; gone = true; el.classList.add('out'); setTimeout(() => el.remove(), 300); };
    x.addEventListener('click', (e) => { e.stopPropagation(); close(); });
    el.addEventListener('click', () => {
        close();
        const visit = () => router.visit(top.href || '/notices');
        router.post(route('notices.read', top.id), {}, { preserveScroll: true, preserveState: true, onFinish: visit });
    });
    document.body.appendChild(el);
    requestAnimationFrame(() => el.classList.add('in'));
    setTimeout(close, 7000);
}

function refresh() {
    const path = window.location.pathname;
    const full = FULL_RELOAD.some((p) => path === p || path.startsWith(p + '/'));
    router.reload({
        only: full ? undefined : ['notifications', 'unreadNotices'],
        preserveScroll: true, preserveState: true, async: true,
    });
}

async function pulse() {
    if (busy) { schedule(3000); return; }
    try {
        const { data } = await axios.get('/notices/pulse', { headers: { Accept: 'application/json' }, params: { _: Date.now() } });
        failures = 0;
        const first = sig === null;
        const changed = !first && data.sig !== sig;
        const fresh = data.top && data.top.id !== lastTop && (!first);
        sig = data.sig;
        if (first) {
            lastTop = data.top?.id ?? null;
            // از زمانِ بارگذاریِ صفحه چیزی عوض شده؟ زنگوله را هماهنگ کن
            const shown = window.__inertiaUnread;
            if (typeof shown === 'number' && shown !== data.count) refresh();
        } else if (changed) {
            refresh();
            if (fresh) {
                lastTop = data.top.id;
                toast(data.top, data.count);
                try { navigator.vibrate?.(120); } catch { /* نادیده */ }
                ding();
            } else {
                lastTop = data.top?.id ?? null;
            }
        }
        setBadge(data.count);
        schedule();
    } catch (e) {
        const st = e?.response?.status;
        if (st === 401 || st === 419) { stop(); return; } // خارج شده
        failures += 1;
        schedule(Math.min(120000, EVERY * 2 ** failures));
    }
}

function wake() { if (!document.hidden) { clearTimeout(timer); timer = setTimeout(pulse, 300); } }

function stop() {
    clearTimeout(timer);
    started = false;
}

/** یک‌بار در هر برگه شروع می‌شود؛ صدا زدنِ دوباره بی‌اثر است. */
export function startLiveNotices(unreadNow) {
    if (typeof window === 'undefined') return;
    window.__inertiaUnread = unreadNow;
    if (started) return;
    started = true;
    router.on('start', (e) => { if (!e.detail.visit.async) busy = true; });
    router.on('finish', (e) => { if (!e.detail.visit.async) busy = false; });
    document.addEventListener('visibilitychange', () => (document.hidden ? clearTimeout(timer) : wake()));
    window.addEventListener('focus', wake);
    window.addEventListener('online', wake);
    schedule(4000);
}

/** شمارِ زنگوله‌ی همین صفحه را به حلقه می‌گوید (بعد از هر بارگذاری). */
export function syncUnread(n) {
    if (typeof window !== 'undefined') window.__inertiaUnread = n;
    setBadge(n);
}
