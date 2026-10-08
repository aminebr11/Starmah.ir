import { useState, useEffect, useMemo, useRef, useCallback } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import JalaliDatePicker from '@/Components/JalaliDatePicker';
import { compressImage, chunkFiles } from '@/lib/imageCompress';
import { useSort, SortBar } from '@/lib/useSort';
import { pushOverlay, closeOverlay } from '@/lib/overlayBack';

/**
 * گالریِ آلبومی — مشترک بینِ معلم و دانش‌آموز.
 *
 *  قفسه‌ی آلبوم‌ها (کاورِ پولارویدی با لایه‌های پشتش، فیلترِ تاریخ، جست‌وجو، مرتب‌سازی)
 *  ← آلبوم (سربرگِ مات با کاور، عکس‌ها به ترتیبِ زمانِ آپلود)
 *  ← نمایشگرِ تمام‌صفحه (کشیدن/کلید، شمارنده، نوارِ بندانگشتی).
 *  معلم داخلِ آلبوم: افزودن، حذف (تکی/گروهی)، تغییرِ عنوان، انتقال، کاور ⭐، مخفی‌کردن.
 */
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
export const THEMES = {
    sunset: ['#ffb36b', '#ff5e7e', '🌅'], ocean: ['#4fc3ff', '#2f6bff', '🌊'], forest: ['#5fdc8c', '#1f9a6a', '🌿'],
    candy: ['#ff9bd6', '#a774ff', '🍭'], night: ['#6f6fe6', '#232a6b', '🌙'], sky: ['#8fdcff', '#8f9bff', '☁️'],
};
const themeOf = (a) => THEMES[a.theme] || THEMES.sky;

/**
 * بستن با دکمه‌ی «بازگشت» گوشی/مرورگر (و کشیدنِ لبه در آیفون).
 * هنگامِ باز شدن یک ورودیِ تاریخچه اضافه می‌شود؛ «بازگشت» فقط همین پنجره را
 * می‌بندد، نه کلِ صفحه را. بستن با دکمه هم همان ورودی را برمی‌دارد.
 */
function useBackClose(key, onClose) {
    const me = useRef(null);
    if (!me.current) me.current = { key, close: () => {} };
    me.current.close = onClose;
    useEffect(() => pushOverlay(me.current), []); // eslint-disable-line
    return () => closeOverlay(me.current);
}

/* ═══════════════════════════ قفسه‌ی آلبوم‌ها ═══════════════════════════ */
export default function AlbumGallery({ albums = [], mode = 'student', classrooms = [], openAlbum = null, onViewed }) {
    const teacher = mode === 'teacher';
    const [openId, setOpenId] = useState(openAlbum);
    const [day, setDay] = useState('');
    const [q, setQ] = useState('');
    const [creating, setCreating] = useState(false);

    useEffect(() => { if (openAlbum) setOpenId(openAlbum); }, [openAlbum]);

    const days = useMemo(() => {
        const m = new Map();
        albums.forEach((a) => { if (!m.has(a.day)) m.set(a.day, { day: a.day, label: a.date, n: 0 }); m.get(a.day).n++; });
        return [...m.values()].sort((x, y) => (y.day || '').localeCompare(x.day || ''));
    }, [albums]);

    const filtered = albums.filter((a) => (!day || a.day === day) && (!q || (a.title + ' ' + (a.description || '')).includes(q)));
    const s = useSort(filtered, { new: 'ts', title: 'title', count: 'count' }, { key: 'new', dir: 'desc', id: `albums-${mode}`, firstDir: { new: 'desc', count: 'desc' } });
    const open = albums.find((a) => a.id === openId);
    const totalPhotos = albums.reduce((t, a) => t + a.count, 0);

    // بستنِ آلبومی که حذف شد
    useEffect(() => { if (openId && !open) setOpenId(null); }, [albums.length]); // eslint-disable-line

    return (
        <div className={`ag ${teacher ? 'ag-t' : 'ag-s'}`}>
            <div className="ag-top">
                <div className="ag-hello">
                    <span className="ag-hello-ic">📸</span>
                    <div>
                        <b>گالریِ کلاس</b>
                        <small>{fa(albums.length)} آلبوم · {fa(totalPhotos)} عکس</small>
                    </div>
                </div>
                {teacher && <button type="button" className="ag-new" onClick={() => setCreating(true)}>➕ آلبومِ تازه</button>}
            </div>

            {albums.length > 0 && (
                <div className="ag-filters">
                    <select className="ag-select" value={day} onChange={(e) => setDay(e.target.value)} aria-label="تاریخِ آپلود">
                        <option value="">📅 همه‌ی تاریخ‌ها</option>
                        {days.map((d) => <option key={d.day} value={d.day}>{fa(d.label)} — {fa(d.n)} آلبوم</option>)}
                    </select>
                    <input className="ag-search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 جست‌وجوی آلبوم…" />
                    <SortBar s={s} label="ترتیب:" options={[['new', 'تاریخ', 'desc'], ['title', 'عنوان'], ['count', 'تعدادِ عکس', 'desc']]} />
                </div>
            )}

            {albums.length === 0 && (
                <div className="ag-empty">
                    <div className="ag-empty-art">🖼️<span>✨</span></div>
                    <b>{teacher ? 'هنوز آلبومی نساخته‌ای' : 'هنوز آلبومی منتشر نشده'}</b>
                    <p>{teacher ? 'عکس‌های اردو، جشن یا کارهای کلاسی را در یک آلبوم بگذار؛ یکی را کاور کن و بقیه داخلش می‌نشینند.' : 'وقتی معلمت عکس‌های کلاس را بگذارد، اینجا آلبوم‌به‌آلبوم می‌بینی‌شان.'}</p>
                    {teacher && <button type="button" className="ag-new" onClick={() => setCreating(true)}>➕ ساختِ نخستین آلبوم</button>}
                </div>
            )}
            {albums.length > 0 && filtered.length === 0 && <div className="ag-empty small">با این فیلتر آلبومی پیدا نشد 🔍</div>}

            <div className="ag-shelf">
                {s.sorted.map((a, i) => <AlbumCard key={a.id} a={a} i={i} teacher={teacher} onOpen={() => setOpenId(a.id)} />)}
            </div>

            {creating && <Uploader classrooms={classrooms} onClose={() => setCreating(false)} onDone={(id) => { setCreating(false); setOpenId(id); }} />}
            {open && <AlbumView a={open} teacher={teacher} albums={albums} classrooms={classrooms} onClose={() => setOpenId(null)} onViewed={onViewed} />}
        </div>
    );
}

function AlbumCard({ a, i, teacher, onOpen }) {
    const [c1, c2, emo] = themeOf(a);
    const back = a.photos.filter((p) => p.id !== a.cover_id).slice(-2);
    return (
        <button type="button" className={`ag-card ${!a.is_visible || a.live === false ? 'dim' : ''}`} onClick={onOpen}
            style={{ '--c1': c1, '--c2': c2, animationDelay: `${Math.min(i, 12) * 45}ms` }}>
            <div className="ag-stack">
                {back.map((p, k) => <span key={p.id} className={`ag-layer l${k}`} style={{ backgroundImage: `url("${p.url}")` }} />)}
                {back.length < 2 && [0, 1].slice(back.length).map((k) => <span key={k} className={`ag-layer l${k} plain`} />)}
                <span className="ag-cover" style={{ backgroundImage: a.cover ? `url("${a.cover}")` : undefined }}>
                    <span className="ag-count">🖼️ {fa(a.count)}</span>
                    {teacher && !a.is_visible && <span className="ag-flag">🙈 مخفی</span>}
                    {teacher && a.is_visible && a.live === false && <span className="ag-flag warn">⏰ زمان‌دار</span>}
                </span>
                <span className="ag-tape" />
            </div>
            <div className="ag-card-b">
                <b>{emo} {a.title}</b>
                <small>📅 {fa(a.date)}{a.updated && a.updated !== a.date ? ` · آخرین عکس ${fa(a.updated)}` : ''}</small>
                {teacher && <small className="ag-views">👁️ {fa(a.photos.reduce((t, p) => t + (p.views || 0), 0))} بازدید</small>}
            </div>
        </button>
    );
}

/* ═══════════════════════════ داخلِ آلبوم ═══════════════════════════ */
function AlbumView({ a, teacher, albums, classrooms, onClose, onViewed }) {
    const [c1, c2, emo] = themeOf(a);
    const [lb, setLb] = useState(null); // اندیسِ عکسِ باز در نمایشگر
    const close = useBackClose('agAlbum', onClose);
    const [editing, setEditing] = useState(false);
    const [adding, setAdding] = useState(false);
    const [photoEdit, setPhotoEdit] = useState(null);
    const [selMode, setSelMode] = useState(false);
    const [sel, setSel] = useState([]);
    const [order, setOrder] = useState('asc');
    const photos = useMemo(() => {
        const list = [...a.photos].sort((x, y) => x.ts - y.ts || x.id - y.id);
        return order === 'asc' ? list : list.reverse();
    }, [a.photos, order]);

    useEffect(() => {
        const onKey = (e) => { if (e.key === 'Escape' && lb === null && !editing && !photoEdit && !adding) close(); };
        document.addEventListener('keydown', onKey);
        document.body.classList.add('ag-lock');
        return () => { document.removeEventListener('keydown', onKey); document.body.classList.remove('ag-lock'); };
    }, [lb, editing, photoEdit, adding]); // eslint-disable-line

    // گروه‌بندیِ داخلِ آلبوم بر اساسِ روزِ آپلود (برای آلبوم‌هایی که چند روز عکس گرفته‌اند)
    const groups = useMemo(() => {
        const g = [];
        photos.forEach((p, idx) => {
            if (!g.length || g[g.length - 1].date !== p.date) g.push({ date: p.date, items: [] });
            g[g.length - 1].items.push([p, idx]);
        });
        return g;
    }, [photos]);

    const post = (name, params, data = {}, opts = {}) => router.post(route(name, params), data, { preserveScroll: true, preserveState: true, ...opts });
    const toggleSel = (id) => setSel((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    const delSelected = () => {
        if (!sel.length || !confirm(`${fa(sel.length)} عکس از این آلبوم حذف شود؟`)) return;
        post('teacher.gallery.albums.photos.destroy', a.id, { ids: sel }, { onSuccess: () => { setSel([]); setSelMode(false); } });
    };
    const delAlbum = () => {
        if (!confirm(`آلبومِ «${a.title}» با ${fa(a.count)} عکس برای همیشه حذف شود؟`)) return;
        router.delete(route('teacher.gallery.albums.destroy', a.id), { preserveScroll: true, onSuccess: close });
    };

    return (
        <>
        {/* دکمه‌ی بستنِ ثابت، بیرون از پنجره‌ی اسکرول‌شونده: همیشه پیداست و زیرِ نوارِ وضعیتِ آیفون نمی‌رود */}
        {lb === null && (
            <button type="button" className="ag-close" onClick={close} aria-label="بستنِ آلبوم">
                <span>✕</span><b>بازگشت به گالری</b>
            </button>
        )}
        <div className="ag-modal" role="dialog" aria-modal="true" aria-label={a.title}>
            <div className="ag-sheet">
                <header className="ag-hero" style={{ '--c1': c1, '--c2': c2 }}>
                    {a.cover && <span className="ag-hero-bg" style={{ backgroundImage: `url("${a.cover}")` }} />}
                    <div className="ag-hero-in">
                        {a.cover && <img className="ag-hero-cover" src={a.cover} alt="" onClick={() => setLb(Math.max(0, photos.findIndex((p) => p.id === a.cover_id)))} />}
                        <div className="ag-hero-t">
                            <h2>{emo} {a.title}</h2>
                            {a.description && <p>{a.description}</p>}
                            <div className="ag-hero-meta">
                                <span>🖼️ {fa(a.count)} عکس</span>
                                <span>📅 {fa(a.date)}</span>
                                {teacher && <span>{a.is_visible ? (a.live === false ? `⏰ ${fa(a.publish_at)}` : '👁️ منتشرشده') : '🙈 مخفی'}</span>}
                                {teacher && a.classroom_id && <span>🏫 {classrooms.find((c) => c.id === a.classroom_id)?.name}</span>}
                            </div>
                        </div>
                    </div>
                    {teacher && (
                        <div className="ag-tools">
                            <button type="button" onClick={() => setAdding(true)}>➕ افزودنِ عکس</button>
                            <button type="button" onClick={() => setEditing(true)}>✏️ ویرایشِ آلبوم</button>
                            <button type="button" onClick={() => post('teacher.gallery.albums.visibility', a.id)}>{a.is_visible ? '🙈 مخفی‌کردن' : '👁️ نمایش'}</button>
                            <button type="button" className={selMode ? 'on' : ''} onClick={() => { setSelMode(!selMode); setSel([]); }}>☑️ انتخابِ چندتایی</button>
                            <button type="button" className="danger" onClick={delAlbum}>🗑️ حذفِ آلبوم</button>
                        </div>
                    )}
                </header>

                <div className="ag-body">
                    <div className="ag-body-bar">
                        <span>به ترتیبِ زمانِ آپلود</span>
                        <button type="button" className="ag-chip" onClick={() => setOrder(order === 'asc' ? 'desc' : 'asc')}>{order === 'asc' ? '⬇️ قدیمی ← جدید' : '⬆️ جدید ← قدیمی'}</button>
                        {selMode && (
                            <>
                                <button type="button" className="ag-chip" onClick={() => setSel(sel.length === photos.length ? [] : photos.map((p) => p.id))}>{sel.length === photos.length ? 'هیچ‌کدام' : 'انتخابِ همه'}</button>
                                <button type="button" className="ag-chip danger" disabled={!sel.length} onClick={delSelected}>🗑️ حذفِ {fa(sel.length)} عکس</button>
                            </>
                        )}
                    </div>
                    {groups.map((g) => (
                        <section key={g.date} className="ag-day">
                            {groups.length > 1 && <h4>📅 {fa(g.date)} <small>{fa(g.items.length)} عکس</small></h4>}
                            <div className="ag-masonry">
                                {g.items.map(([p, idx]) => (
                                    <figure key={p.id} className={`ag-ph ${sel.includes(p.id) ? 'sel' : ''} ${!p.is_visible ? 'hid' : ''}`}>
                                        <img src={p.url} alt={p.title} loading="lazy" onClick={() => (selMode ? toggleSel(p.id) : setLb(idx))} />
                                        {p.id === a.cover_id && <span className="ag-cover-badge">⭐ کاور</span>}
                                        {selMode && <span className="ag-check" onClick={() => toggleSel(p.id)}>{sel.includes(p.id) ? '✔' : ''}</span>}
                                        {!teacher && p.viewed && <span className="ag-seen" title="دیده‌ای">✓</span>}
                                        <figcaption>
                                            {p.title && p.title !== a.title && <b>{p.title}</b>}
                                            <small>🕒 {p.time}{teacher ? ` · 👁️ ${fa(p.views || 0)}` : ''}</small>
                                        </figcaption>
                                        {teacher && !selMode && (
                                            <div className="ag-ph-tools">
                                                {p.id !== a.cover_id && <button type="button" title="کاورِ آلبوم شود" onClick={() => post('teacher.gallery.albums.cover', [a.id, p.id])}>⭐</button>}
                                                <button type="button" title="ویرایشِ عنوان" onClick={() => setPhotoEdit(p)}>✏️</button>
                                                <button type="button" title={p.is_visible ? 'مخفی' : 'نمایش'} onClick={() => post('teacher.materials.visibility', p.id)}>{p.is_visible ? '🙈' : '👁️'}</button>
                                                <button type="button" title="حذف" className="danger" onClick={() => { if (confirm('این عکس حذف شود؟')) router.delete(route('teacher.materials.destroy', p.id), { preserveScroll: true, preserveState: true }); }}>🗑️</button>
                                            </div>
                                        )}
                                    </figure>
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            </div>

            {lb !== null && <Lightbox photos={photos} index={lb} album={a} onIndex={setLb} onClose={() => setLb(null)} onViewed={onViewed} />}
            {editing && <AlbumEdit a={a} classrooms={classrooms} onClose={() => setEditing(false)} />}
            {photoEdit && <PhotoEdit p={photoEdit} albums={albums} onClose={() => setPhotoEdit(null)} />}
            {adding && <Uploader classrooms={classrooms} album={a} onClose={() => setAdding(false)} onDone={() => setAdding(false)} />}
        </div>
        </>
    );
}

/* ═══════════════════════════ نمایشگرِ تمام‌صفحه ═══════════════════════════ */
function Lightbox({ photos, index, album, onIndex, onClose, onViewed }) {
    const p = photos[index];
    const touch = useRef(null);
    const stripRef = useRef(null);
    const go = useCallback((d) => onIndex((i) => (i + d + photos.length) % photos.length), [photos.length]); // eslint-disable-line
    const close = useBackClose('agLb', onClose);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') close();
            // راست‌به‌چپ: فلشِ چپ = عکسِ بعدی
            if (e.key === 'ArrowLeft') go(1);
            if (e.key === 'ArrowRight') go(-1);
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [go]); // eslint-disable-line

    useEffect(() => {
        if (p && onViewed) onViewed(p);
        stripRef.current?.querySelector('.on')?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        // پیش‌بارگذاریِ عکسِ بعد و قبل
        [1, -1].forEach((d) => { const n = photos[(index + d + photos.length) % photos.length]; if (n) { const im = new Image(); im.src = n.url; } });
    }, [index]); // eslint-disable-line

    if (!p) return null;
    return (
        <div className="ag-lb" onClick={close}
            onTouchStart={(e) => { touch.current = e.touches[0].clientX; }}
            onTouchEnd={(e) => { if (touch.current == null) return; const dx = e.changedTouches[0].clientX - touch.current; touch.current = null; if (Math.abs(dx) > 50) go(dx > 0 ? 1 : -1); }}>
            <div className="ag-lb-top" onClick={(e) => e.stopPropagation()}>
                <span className="ag-lb-n">{fa(index + 1)} / {fa(photos.length)}</span>
                <span className="ag-lb-t">{album.title}</span>
                <a className="ag-lb-btn" href={p.url} download target="_blank" rel="noreferrer" title="دریافتِ عکس">⬇️</a>
                <button type="button" className="ag-lb-btn close" onClick={close} aria-label="بستن">✕</button>
            </div>
            <div className="ag-lb-stage" onClick={(e) => e.stopPropagation()}>
                {photos.length > 1 && <button type="button" className="ag-lb-nav prev" onClick={() => go(-1)} aria-label="قبلی">›</button>}
                <img key={p.id} src={p.url} alt={p.title} className="ag-lb-img" />
                {photos.length > 1 && <button type="button" className="ag-lb-nav next" onClick={() => go(1)} aria-label="بعدی">‹</button>}
            </div>
            <div className="ag-lb-cap" onClick={(e) => e.stopPropagation()}>
                {p.title && p.title !== album.title && <b>{p.title}</b>}
                {p.description && <p>{p.description}</p>}
                <small>📅 {fa(p.date)} · 🕒 {p.time}</small>
            </div>
            {photos.length > 1 && (
                <div className="ag-lb-strip" ref={stripRef} onClick={(e) => e.stopPropagation()}>
                    {photos.map((x, i) => <img key={x.id} src={x.url} alt="" className={i === index ? 'on' : ''} onClick={() => onIndex(i)} loading="lazy" />)}
                </div>
            )}
        </div>
    );
}

/* ═══════════════════════════ فرم‌ها (معلم) ═══════════════════════════ */
function Modal({ title, onClose, children, wide }) {
    return (
        <div className="ag-dlg-wrap" onClick={onClose}>
            <div className={`ag-dlg ${wide ? 'wide' : ''}`} onClick={(e) => e.stopPropagation()}>
                <div className="ag-dlg-h"><b>{title}</b><button type="button" onClick={onClose} aria-label="بستن">✕</button></div>
                {children}
            </div>
        </div>
    );
}

function F({ label, err, children }) {
    return <div className="field"><label>{label}</label>{children}{err && <div className="ag-err">{err}</div>}</div>;
}

function PublishField({ date, time, setDate, setTime }) {
    return (
        <F label="زمانِ انتشار (اختیاری)">
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                <div style={{ flex: '1 1 150px' }}><JalaliDatePicker value={date} onChange={setDate} placeholder="همین حالا" /></div>
                <input type="time" className="input" value={time} onChange={(e) => setTime(e.target.value)} style={{ width: 120 }} dir="ltr" disabled={!date} />
                {date && <button type="button" className="btn btn-ghost btn-sm" onClick={() => setDate('')}>انتشارِ فوری</button>}
            </div>
        </F>
    );
}

function AlbumEdit({ a, classrooms, onClose }) {
    const raw = a.publish_at_raw || '';
    const [d, setD] = useState({ title: a.title, description: a.description || '', classroom_id: a.classroom_id || '', theme: a.theme });
    const [pubDate, setPubDate] = useState(raw ? raw.slice(0, 10) : '');
    const [pubTime, setPubTime] = useState(raw ? raw.slice(11, 16) : '08:00');
    const [errs, setErrs] = useState({});
    const [busy, setBusy] = useState(false);
    const save = (e) => {
        e.preventDefault();
        router.post(route('teacher.gallery.albums.update', a.id), { ...d, publish_at: pubDate ? `${pubDate} ${pubTime || '00:00'}` : null }, {
            preserveScroll: true, preserveState: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onSuccess: onClose, onError: setErrs,
        });
    };
    return (
        <Modal title="✏️ ویرایشِ آلبوم" onClose={onClose}>
            <form onSubmit={save}>
                <F label="عنوانِ آلبوم" err={errs.title}><input className="input" value={d.title} onChange={(e) => setD({ ...d, title: e.target.value })} /></F>
                <F label="توضیح"><textarea className="input" rows="2" value={d.description} onChange={(e) => setD({ ...d, description: e.target.value })} /></F>
                {classrooms.length > 0 && (
                    <F label="کلاس">
                        <select className="input" value={d.classroom_id} onChange={(e) => setD({ ...d, classroom_id: e.target.value })}>
                            <option value="">همه‌ی کلاس‌ها</option>
                            {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </F>
                )}
                <F label="رنگِ آلبوم">
                    <div className="ag-themes">
                        {Object.entries(THEMES).map(([k, [x, y, e]]) => (
                            <button type="button" key={k} className={d.theme === k ? 'on' : ''} style={{ background: `linear-gradient(135deg,${x},${y})` }} onClick={() => setD({ ...d, theme: k })}>{e}</button>
                        ))}
                    </div>
                </F>
                <PublishField date={pubDate} time={pubTime} setDate={setPubDate} setTime={setPubTime} />
                <p className="ag-note">برای عوض‌کردنِ کاور، روی ⭐ کنارِ هر عکس داخلِ آلبوم بزن.</p>
                <div className="ag-dlg-f">
                    <button type="submit" className="btn" disabled={busy || !d.title.trim()}>{busy ? 'در حالِ ذخیره…' : '💾 ذخیره'}</button>
                    <button type="button" className="btn btn-ghost" onClick={onClose}>انصراف</button>
                </div>
            </form>
        </Modal>
    );
}

function PhotoEdit({ p, albums, onClose }) {
    const [d, setD] = useState({ title: p.title || '', description: p.description || '', album_id: albums.find((a) => a.photos.some((x) => x.id === p.id))?.id || '' });
    const [errs, setErrs] = useState({});
    const save = (e) => {
        e.preventDefault();
        router.post(route('teacher.gallery.photos.update', p.id), d, { preserveScroll: true, preserveState: true, onSuccess: onClose, onError: setErrs });
    };
    return (
        <Modal title="✏️ ویرایشِ عکس" onClose={onClose}>
            <form onSubmit={save}>
                <img src={p.url} alt="" className="ag-dlg-img" />
                <F label="عنوانِ عکس" err={errs.title}><input className="input" value={d.title} onChange={(e) => setD({ ...d, title: e.target.value })} autoFocus /></F>
                <F label="توضیح (اختیاری)"><textarea className="input" rows="2" value={d.description} onChange={(e) => setD({ ...d, description: e.target.value })} placeholder="مثلاً: لحظه‌ی برنده‌شدنِ تیمِ ستاره‌ها" /></F>
                {albums.length > 1 && (
                    <F label="آلبوم (برای انتقال عوض کن)">
                        <select className="input" value={d.album_id} onChange={(e) => setD({ ...d, album_id: Number(e.target.value) })}>
                            {albums.map((a) => <option key={a.id} value={a.id}>{a.title} ({fa(a.count)} عکس)</option>)}
                        </select>
                    </F>
                )}
                <div className="ag-dlg-f">
                    <button type="submit" className="btn" disabled={!d.title.trim()}>💾 ذخیره</button>
                    <button type="button" className="btn btn-ghost" onClick={onClose}>انصراف</button>
                </div>
            </form>
        </Modal>
    );
}

/**
 * ساختِ آلبومِ تازه یا افزودنِ عکس به آلبومِ موجود.
 * عکس‌ها در مرورگر کوچک می‌شوند و چند تکه با axios فرستاده می‌شوند؛ تکه‌ی اول
 * آلبوم را می‌سازد و شناسه‌اش برای تکه‌های بعدی استفاده می‌شود.
 */
function Uploader({ classrooms, album = null, onClose, onDone }) {
    const [d, setD] = useState({ title: '', description: '', classroom_id: '' });
    const [pubDate, setPubDate] = useState('');
    const [pubTime, setPubTime] = useState('08:00');
    const [pics, setPics] = useState([]);
    const [cover, setCover] = useState(null);
    const [busy, setBusy] = useState(null);
    const [err, setErr] = useState(null);
    const inputRef = useRef(null);

    useEffect(() => () => pics.forEach((p) => URL.revokeObjectURL(p.url)), []); // eslint-disable-line
    const add = (list) => {
        const fresh = Array.from(list || []).filter((f) => f.type.startsWith('image/'))
            .map((file) => ({ file, url: URL.createObjectURL(file), key: `${file.name}-${file.size}-${file.lastModified}` }));
        setPics((cur) => { const k = new Set(cur.map((x) => x.key)); return [...cur, ...fresh.filter((x) => !k.has(x.key))].slice(0, 100); });
        setErr(null);
    };
    const drop = (key) => setPics((cur) => cur.filter((x) => x.key !== key));
    const coverKey = cover && pics.some((p) => p.key === cover) ? cover : pics[0]?.key;

    const submit = async () => {
        if (!album && !d.title.trim()) { setErr('عنوانِ آلبوم را بنویس.'); return; }
        if (!pics.length) { setErr('دستِ‌کم یک عکس انتخاب کن.'); return; }
        setErr(null);
        const total = pics.length;
        try {
            // ترتیبِ انتخاب حفظ می‌شود؛ کاور فقط علامت می‌خورد
            const coverAt = album ? -1 : pics.findIndex((p) => p.key === coverKey);
            const files = [];
            for (const p of pics) {
                setBusy({ done: files.length, total, phase: 'آماده‌سازیِ عکس‌ها' });
                files.push(await compressImage(p.file));
            }
            const chunks = chunkFiles(files);
            let albumId = album?.id || null, sent = 0;
            for (let i = 0; i < chunks.length; i++) {
                const coverLocal = coverAt >= sent && coverAt < sent + chunks[i].length ? coverAt - sent : null;
                setBusy({ done: sent, total, phase: 'بارگذاری' });
                const fd = new FormData();
                chunks[i].forEach((f) => fd.append('files[]', f));
                if (albumId) fd.append('album_id', albumId);
                else {
                    fd.append('title', d.title); fd.append('description', d.description || '');
                    if (d.classroom_id) fd.append('classroom_id', d.classroom_id);
                    if (pubDate) fd.append('publish_at', `${pubDate} ${pubTime || '00:00'}`);
                }
                if (coverLocal !== null) fd.append('cover_local', String(coverLocal));
                fd.append('final', i === chunks.length - 1 ? '1' : '0');
                fd.append('total', String(total));
                fd.append('is_new', album ? '0' : '1');
                const { data } = await axios.post(route('teacher.gallery.upload'), fd, { headers: { Accept: 'application/json' } });
                albumId = data.album_id; sent += chunks[i].length;
            }
            setBusy({ done: total, total, phase: 'تمام شد' });
            router.reload({ preserveScroll: true, onFinish: () => onDone(albumId) });
        } catch (e) {
            setBusy(null);
            const r = e?.response?.data;
            setErr(r?.errors ? Object.values(r.errors)[0]?.[0] : (r?.message || 'بارگذاری ناموفق بود؛ اتصال را بررسی کن و دوباره بفرست.'));
        }
    };

    return (
        <Modal title={album ? `➕ افزودنِ عکس به «${album.title}»` : '📸 آلبومِ تازه'} onClose={busy ? () => {} : onClose} wide>
            {!album && (
                <div className="ag-up-grid">
                    <F label="عنوانِ آلبوم"><input className="input" value={d.title} onChange={(e) => setD({ ...d, title: e.target.value })} placeholder="مثلاً: اردوی علمی به رصدخانه" autoFocus /></F>
                    {classrooms.length > 0 && (
                        <F label="کلاس">
                            <select className="input" value={d.classroom_id} onChange={(e) => setD({ ...d, classroom_id: e.target.value })}>
                                <option value="">همه‌ی کلاس‌ها</option>
                                {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </F>
                    )}
                    <F label="توضیح (اختیاری)"><textarea className="input" rows="2" value={d.description} onChange={(e) => setD({ ...d, description: e.target.value })} /></F>
                    <PublishField date={pubDate} time={pubTime} setDate={setPubDate} setTime={setPubTime} />
                </div>
            )}
            <label className="gl-drop"
                onDragOver={(e) => { e.preventDefault(); e.currentTarget.classList.add('on'); }}
                onDragLeave={(e) => e.currentTarget.classList.remove('on')}
                onDrop={(e) => { e.preventDefault(); e.currentTarget.classList.remove('on'); add(e.dataTransfer.files); }}>
                <input ref={inputRef} type="file" accept="image/*" multiple hidden onChange={(e) => { add(e.target.files); e.target.value = ''; }} />
                <span className="gl-drop-ic">🖼️</span>
                <b>{pics.length ? '➕ عکس‌های بیشتر' : 'انتخابِ عکس‌ها'}</b>
                <small>چند عکس را با هم از گوشی انتخاب کن یا اینجا رها کن</small>
            </label>
            {pics.length > 0 && (
                <>
                    <div className="gl-picked-h">
                        <span>{fa(pics.length)} عکس · {album ? 'به ترتیبِ زمانِ آپلود داخلِ آلبوم می‌نشینند.' : <>روی ⭐ بزن تا <b>کاورِ آلبوم</b> شود.</>}</span>
                        {!busy && <button type="button" className="btn btn-ghost btn-sm" onClick={() => setPics([])}>پاک‌کردنِ همه</button>}
                    </div>
                    <div className="gl-picked">
                        {pics.map((p) => (
                            <div key={p.key} className={`gl-thumb ${!album && p.key === coverKey ? 'cover' : ''}`}>
                                <img src={p.url} alt="" />
                                {!album && <button type="button" className="star" title="کاور" onClick={() => setCover(p.key)}>{p.key === coverKey ? '⭐' : '☆'}</button>}
                                {!busy && <button type="button" onClick={() => drop(p.key)} title="برداشتن">✕</button>}
                            </div>
                        ))}
                    </div>
                </>
            )}
            {busy && (
                <div className="gl-prog">
                    <div className="gl-prog-bar"><div style={{ width: `${Math.round((busy.done / busy.total) * 100)}%` }} /></div>
                    <span>{busy.phase}… {fa(busy.done)} از {fa(busy.total)}</span>
                </div>
            )}
            {err && <div className="ag-err" style={{ marginTop: 8 }}>{err}</div>}
            <div className="ag-dlg-f">
                <button type="button" className="btn" onClick={submit} disabled={!!busy || !pics.length}>
                    {busy ? 'در حالِ بارگذاری…' : album ? `➕ افزودنِ ${fa(pics.length)} عکس` : `📸 ساختِ آلبوم با ${fa(pics.length || 0)} عکس`}
                </button>
                {!busy && <button type="button" className="btn btn-ghost" onClick={onClose}>انصراف</button>}
            </div>
            <p className="ag-note">عکس‌های بزرگ پیش از ارسال خودکار کوچک می‌شوند. برای همه‌ی عکس‌ها فقط یک اعلان به دانش‌آموزان می‌رود.</p>
        </Modal>
    );
}
