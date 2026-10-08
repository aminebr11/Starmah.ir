import { useMemo, useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';

/**
 * صفحه‌ی اصلیِ «استودیوی بازی» و «آزمون‌ها»: دکمه‌ی بزرگِ ساختِ تازه،
 * سه دسته (منتشرشده / در انتظارِ انتشار / آرشیو) و تفکیک بر اساسِ درس.
 * خودِ فرمِ ساخت در صفحه‌ی جداگانه‌ی /new است.
 */
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export const BUCKETS = [
    { k: 'published', t: 'منتشرشده', ic: '🚀', hint: 'الان در دسترسِ دانش‌آموزان' },
    { k: 'pending', t: 'در انتظارِ انتشار', ic: '⏳', hint: 'پیش‌نویس یا زمان‌بندی‌شده' },
    { k: 'archived', t: 'آرشیو', ic: '🗄️', hint: 'بایگانی، بسته یا پایان‌یافته' },
];

const SUBJECT_IC = [
    [/ریاض|حساب/, '➗'], [/علوم|تجربی|زیست|فیزیک|شیمی/, '🔬'], [/فارسی|ادبیات|نگارش|املا|خوانداری/, '📖'],
    [/قرآن|دینی|هدیه|پیامها/, '🕌'], [/اجتماع|تاریخ|جغراف|مدنی/, '🌍'], [/انگلیس|زبان/, '🔤'], [/عربی/, '🌙'],
    [/هنر|نقاشی/, '🎨'], [/ورزش|تربیت/, '⚽'], [/کار|فناوری|رایانه/, '💻'],
];
export const subjectIcon = (s) => (SUBJECT_IC.find(([re]) => re.test(s || ''))?.[1]) || '📚';

export default function CreatorHub({ kind, items = [], createHref, createLabel, createHint, renderCard, extraStats = [], emptyIcon, accent = '#3a67c8', accent2 = '#7c5cf0' }) {
    const { flash } = usePage().props;
    const banner = typeof flash?.flash === 'string' ? flash.flash : flash?.flash?.message;
    const key = `hub:${kind}`;
    const saved = (() => { try { return JSON.parse(sessionStorage.getItem(key) || '{}'); } catch { return {}; } })();
    const [bucket, setBucket] = useState(saved.bucket || 'all');
    const [subject, setSubject] = useState(saved.subject || '');
    const [q, setQ] = useState('');
    useEffect(() => { try { sessionStorage.setItem(key, JSON.stringify({ bucket, subject })); } catch { /* حالت خصوصی */ } }, [bucket, subject]);

    const counts = useMemo(() => {
        const c = { all: items.length, published: 0, pending: 0, archived: 0 };
        items.forEach((i) => { c[i.bucket] = (c[i.bucket] || 0) + 1; });
        return c;
    }, [items]);

    // درس‌ها با شمارِ هر دسته — «علوم: ۲ منتشر، ۱ در انتظار»
    const subjects = useMemo(() => {
        const m = new Map();
        items.forEach((i) => {
            const s = i.subject || 'بدونِ درس';
            const r = m.get(s) || { name: s, total: 0, published: 0, pending: 0, archived: 0 };
            r.total++; r[i.bucket]++; m.set(s, r);
        });
        return [...m.values()].sort((a, b) => b.total - a.total);
    }, [items]);

    const list = items.filter((i) => (bucket === 'all' || i.bucket === bucket)
        && (!subject || (i.subject || 'بدونِ درس') === subject)
        && (!q.trim() || (i.title || '').includes(q.trim())));
    const noun = kind === 'game' ? 'بازی' : 'آزمون';

    return (
        <div className="ch" style={{ '--ch1': accent, '--ch2': accent2 }}>
            {banner && <div className="ch-flash">✅ {banner}</div>}

            <div className="ch-hero">
                <div className="ch-hero-txt">
                    <h2>{kind === 'game' ? '🎮 استودیوی بازی' : '🧠 آزمون‌های هوشمند'}</h2>
                    <p>{createHint}</p>
                    <div className="ch-hero-stats">
                        <span><b>{fa(counts.published)}</b> منتشرشده</span>
                        <span><b>{fa(counts.pending)}</b> در انتظار</span>
                        <span><b>{fa(counts.archived)}</b> آرشیو</span>
                        {extraStats.map(([v, l], i) => <span key={i}><b>{fa(v)}</b> {l}</span>)}
                    </div>
                </div>
                <Link href={createHref} className="ch-create">
                    <span className="ch-create-plus">＋</span>
                    <span className="ch-create-t">{createLabel}<small>با هوش مصنوعی، بانکِ سؤال یا دستی</small></span>
                    <span className="ch-create-go">←</span>
                </Link>
            </div>

            <div className="ch-buckets" role="tablist">
                {[{ k: 'all', t: 'همه', ic: '✨', hint: `همه‌ی ${noun}‌ها` }, ...BUCKETS].map((b) => (
                    <button key={b.k} type="button" role="tab" aria-selected={bucket === b.k}
                        className={`ch-bucket b-${b.k} ${bucket === b.k ? 'on' : ''}`} onClick={() => setBucket(b.k)}>
                        <span className="ch-bucket-ic">{b.ic}</span>
                        <span className="ch-bucket-t">{b.t}<small>{b.hint}</small></span>
                        <b className="ch-bucket-n">{fa(counts[b.k] || 0)}</b>
                    </button>
                ))}
            </div>

            {subjects.length > 0 && (
                <div className="ch-subjects">
                    <button type="button" className={`ch-subj ${!subject ? 'on' : ''}`} onClick={() => setSubject('')}>
                        <span>📚</span> همه‌ی درس‌ها <b>{fa(items.length)}</b>
                    </button>
                    {subjects.map((s) => (
                        <button key={s.name} type="button" className={`ch-subj ${subject === s.name ? 'on' : ''}`} onClick={() => setSubject(subject === s.name ? '' : s.name)}
                            title={`${s.published} منتشر · ${s.pending} در انتظار · ${s.archived} آرشیو`}>
                            <span>{subjectIcon(s.name)}</span> {s.name} <b>{fa(s.total)}</b>
                            <i className="ch-subj-mix">
                                {s.published > 0 && <em className="p" style={{ flex: s.published }} />}
                                {s.pending > 0 && <em className="w" style={{ flex: s.pending }} />}
                                {s.archived > 0 && <em className="a" style={{ flex: s.archived }} />}
                            </i>
                        </button>
                    ))}
                </div>
            )}

            {subject && (() => {
                const s = subjects.find((x) => x.name === subject);
                return s ? (
                    <div className="ch-subj-sum">
                        {subjectIcon(s.name)} درسِ <b>{s.name}</b>: {fa(s.total)} {noun} —
                        {' '}<span className="p">{fa(s.published)} منتشرشده</span>،
                        {' '}<span className="w">{fa(s.pending)} در انتظارِ انتشار</span>،
                        {' '}<span className="a">{fa(s.archived)} آرشیو</span>
                    </div>
                ) : null;
            })()}

            <div className="ch-toolbar">
                <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder={`🔍 جست‌وجوی نامِ ${noun}…`} />
                <span>{fa(list.length)} {noun}</span>
            </div>

            {list.length ? (
                <div className="ch-grid">{list.map(renderCard)}</div>
            ) : (
                <div className="ch-empty">
                    <div className="ch-empty-ic">{emptyIcon}</div>
                    <b>{items.length ? `در این دسته ${noun}ی نیست.` : `هنوز ${noun}ی نساخته‌اید.`}</b>
                    <p>{items.length ? 'دسته یا درسِ دیگری را انتخاب کنید.' : `اولین ${noun} را همین حالا بسازید؛ چند دقیقه بیشتر طول نمی‌کشد.`}</p>
                    <Link href={createHref} className="btn">＋ {createLabel}</Link>
                </div>
            )}
        </div>
    );
}

/** برچسبِ رنگیِ وضعیت روی هر کارت. */
export function BucketTag({ item, label }) {
    const b = BUCKETS.find((x) => x.k === item.bucket);
    return <span className={`ch-tag b-${item.bucket}`}>{b?.ic} {label || b?.t}</span>;
}
