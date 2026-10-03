import { useMemo, useState, useEffect } from 'react';

/**
 * مرتب‌سازیِ مشترکِ فهرست‌ها و جدول‌ها.
 *
 *   const s = useSort(rows, {
 *       name:   (r) => r.name,          // نام (کلمه‌ی اول)
 *       family: (r) => lastName(r.name), // نام خانوادگی
 *       xp:     (r) => r.xp,            // عدد
 *   }, { key: 'name', dir: 'asc', id: 'teacher-points' });
 *   s.sorted  → ردیف‌های مرتب‌شده
 *   <SortTh s={s} k="xp">امتیاز</SortTh>     در جدول
 *   <SortBar s={s} options={[['name','نام'],['family','نام خانوادگی'],['xp','امتیاز']]} />  برای فهرست‌های کارتی
 *
 * - مقایسه‌ی فارسی (ی/ک عربی و فارسی یکی، اعداد فارسی/لاتین عدد حساب می‌شوند)
 * - خالی‌ها همیشه آخرِ فهرست
 * - با id، انتخابِ کاربر در مرورگر به یاد می‌ماند
 */
const collator = new Intl.Collator('fa', { numeric: true, sensitivity: 'base' });
const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹', AR_DIGITS = '٠١٢٣٤٥٦٧٨٩';

export const normDigits = (s) => String(s ?? '')
    .replace(/[۰-۹]/g, (d) => FA_DIGITS.indexOf(d))
    .replace(/[٠-٩]/g, (d) => AR_DIGITS.indexOf(d));

const normText = (s) => normDigits(s).replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[‌‏]/g, ' ').trim();

/** کلمه‌ی اولِ نام. */
export const firstName = (full) => normText(full).split(/\s+/)[0] || '';
/** نام خانوادگی = بقیه‌ی نام پس از کلمه‌ی اول (اگر تک‌کلمه بود، همان). */
export const lastName = (full) => {
    const parts = normText(full).split(/\s+/).filter(Boolean);
    return parts.length > 1 ? parts.slice(1).join(' ') : (parts[0] || '');
};

const isEmpty = (v) => v === null || v === undefined || v === '' || (typeof v === 'number' && Number.isNaN(v));

function toComparable(v) {
    if (isEmpty(v)) return null;
    if (typeof v === 'number' || typeof v === 'boolean') return Number(v);
    if (v instanceof Date) return v.getTime();
    const t = normText(v);
    // «۱۲»، «-۵»، «۱۲.۵٪» → عدد
    const m = t.replace(/[٪%,٬]/g, '').match(/^[-+]?\d+(\.\d+)?$/);
    return m ? Number(m[0]) : t;
}

export function compareValues(a, b) {
    const x = toComparable(a), y = toComparable(b);
    if (x === null && y === null) return 0;
    if (x === null) return 1;   // خالی آخر
    if (y === null) return -1;
    if (typeof x === 'number' && typeof y === 'number') return x - y;
    return collator.compare(String(x), String(y));
}

const readSaved = (id) => {
    if (!id) return null;
    try { return JSON.parse(localStorage.getItem('sort:' + id) || 'null'); } catch { return null; }
};

export function useSort(rows, getters = {}, init = {}) {
    const saved = readSaved(init.id);
    const [sort, setSort] = useState(() => (saved && (saved.key === null || getters[saved.key])) ? saved : { key: init.key ?? null, dir: init.dir || 'asc' });

    useEffect(() => {
        if (!init.id) return;
        try { localStorage.setItem('sort:' + init.id, JSON.stringify(sort)); } catch { /* حالتِ خصوصی */ }
    }, [sort.key, sort.dir]); // eslint-disable-line

    const sorted = useMemo(() => {
        const list = Array.isArray(rows) ? rows : [];
        if (!sort.key || !getters[sort.key]) return list;
        const get = typeof getters[sort.key] === 'function' ? getters[sort.key] : (r) => r?.[getters[sort.key]];
        const sign = sort.dir === 'desc' ? -1 : 1;
        // خالی‌ها در هر دو جهت آخر می‌مانند
        return list.map((r, i) => [r, get(r), i]).sort((p, q) => {
            const ea = isEmpty(p[1]), eb = isEmpty(q[1]);
            if (ea || eb) return ea === eb ? p[2] - q[2] : (ea ? 1 : -1);
            return sign * compareValues(p[1], q[1]) || p[2] - q[2];
        }).map((p) => p[0]);
    }, [rows, sort.key, sort.dir]); // eslint-disable-line

    /** کلیک روی یک ستون: صعودی ← نزولی ← بدونِ مرتب‌سازی. عددها اولِ کار نزولی (بیشتر بالا). */
    const toggle = (key, firstDir) => setSort((s) => {
        if (s.key !== key) return { key, dir: firstDir || init.firstDir?.[key] || 'asc' };
        const start = firstDir || init.firstDir?.[key] || 'asc';
        if (s.dir === start) return { key, dir: start === 'asc' ? 'desc' : 'asc' };
        return { key: init.key ?? null, dir: init.dir || 'asc' };
    });

    return { sorted, sort, toggle, setSort };
}

const arrow = (s, k) => (s.sort.key === k ? (s.sort.dir === 'asc' ? '▲' : '▼') : '⇅');

/** سرِ ستونِ قابلِ کلیک. */
export function SortTh({ s, k, first, children, className = '', style, ...rest }) {
    const on = s.sort.key === k;
    return (
        <th {...rest} style={style} className={`sort-th ${on ? 'on' : ''} ${className}`} aria-sort={on ? (s.sort.dir === 'asc' ? 'ascending' : 'descending') : 'none'}
            onClick={() => s.toggle(k, first)} title="برای مرتب‌سازی کلیک کنید">
            <span className="sort-th-in">{children}<i className="sort-ar">{arrow(s, k)}</i></span>
        </th>
    );
}

/** نوارِ مرتب‌سازی برای فهرست‌های کارتی/دکمه‌ای. options: [[key, label, firstDir?], …] */
export function SortBar({ s, options, label = 'مرتب‌سازی:', className = '' }) {
    return (
        <div className={`sort-bar no-print ${className}`}>
            <span className="sort-bar-l">↕️ {label}</span>
            {options.map(([k, t, first]) => (
                <button type="button" key={k} className={`sort-chip ${s.sort.key === k ? 'on' : ''}`} onClick={() => s.toggle(k, first)}>
                    {t} <i>{arrow(s, k)}</i>
                </button>
            ))}
        </div>
    );
}
