import { usePage, useForm, router, Link } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';
import { compressImage, chunkFiles } from '@/lib/imageCompress';
import GalleryByDate from '@/Components/GalleryByDate';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** نمایشِ مدت به‌صورت دقیقه:ثانیه */
const secFmt = (s) => (s >= 60 ? `${fa(Math.floor(s / 60))}:${fa(String(s % 60).padStart(2, '0'))}` : `${fa(s)}ث`);

/** همان فرمولِ سرور (ContentProgressService::xpFor) برای پیش‌نمایش به معلم. */
const autoXp = (seconds) => Math.max(5, Math.min(25, Math.ceil((seconds || 0) / 60) * 2));

const TABS = [
    { v: 'material', ic: '📄', t: 'جزوه و فایل', accept: '.pdf,.doc,.docx,.ppt,.pptx,.zip,image/*', hint: 'فایل PDF، ورد، پاورپوینت یا تصویر' },
    { v: 'podcast', ic: '🎧', t: 'پادکست صوتی و تصویری', accept: 'audio/*,video/*', hint: 'فایل صوتی (MP3/M4A) یا تصویری (MP4) — هر دو پخشِ درون‌برنامه‌ای دارند', playable: true },
    { v: 'video', ic: '🎬', t: 'ویدیوی درسی', accept: 'video/*', hint: 'فایل MP4/WebM — یا نشانیِ ویدیوی بیرونی', playable: true },
    { v: 'gallery', ic: '🖼️', t: 'گالری تصاویر', accept: 'image/*', hint: 'عکس‌های کلاس (JPG/PNG)' },
    { v: 'homework', ic: '📝', t: 'تکلیف', accept: '.pdf,.doc,.docx,image/*', hint: 'شرح تکلیف + فایل ضمیمه (اختیاری)' },
    { v: 'worksheet', ic: '🎨', t: 'کاربرگ', worksheet: true },
];

export default function Materials() {
    const { items = [], classrooms = [], worksheets = [], flash } = usePage().props;
    const [tab, setTab] = useState('material');
    const [banner, setBanner] = useState(null);
    const fileRef = useRef(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const active = TABS.find((t) => t.v === tab);
    const list = items.filter((i) => i.type === tab);

    const form = useForm({
        type: tab, title: '', description: '', classroom_id: '', external_url: '',
        due_at: '', file: null, duration_seconds: '', xp_reward: '',
    });

    // زمانِ انتشار: تاریخِ شمسی + ساعت. خالی یعنی «همین حالا منتشر شود».
    const [pubDate, setPubDate] = useState('');
    const [pubTime, setPubTime] = useState('08:00');

    /**
     * مدتِ فایلِ صوتی/تصویری را همین‌جا در مرورگر می‌خوانیم و همراهِ فرم
     * می‌فرستیم. سرور برای تشخیصِ «تکمیل» به این مدت نیاز دارد و خودش
     * نمی‌تواند بدونِ ابزارِ رسانه‌ای آن را حساب کند.
     */
    const readDuration = (file) => new Promise((resolve) => {
        if (!file || !/^(audio|video)\//.test(file.type)) return resolve(null);
        const el = document.createElement(file.type.startsWith('video') ? 'video' : 'audio');
        el.preload = 'metadata';
        el.onloadedmetadata = () => {
            URL.revokeObjectURL(el.src);
            resolve(Number.isFinite(el.duration) ? Math.round(el.duration) : null);
        };
        el.onerror = () => resolve(null);
        el.src = URL.createObjectURL(file);
    });

    const onPickFile = async (e) => {
        const f = e.target.files?.[0] ?? null;
        form.setData('file', f);
        const d = await readDuration(f);
        form.setData('duration_seconds', d ?? '');
    };

    /* ───── گالری: انتخابِ چند عکس با یک مشخصات ───── */
    const [pics, setPics] = useState([]); // [{ file, url }]
    const [gBusy, setGBusy] = useState(null); // { done, total, phase }
    const [gErr, setGErr] = useState(null);
    const addPics = (fileList) => {
        const fresh = Array.from(fileList || []).filter((f) => f.type.startsWith('image/'))
            .map((file) => ({ file, url: URL.createObjectURL(file), key: `${file.name}-${file.size}-${file.lastModified}` }));
        setPics((cur) => {
            const keys = new Set(cur.map((p) => p.key));
            return [...cur, ...fresh.filter((p) => !keys.has(p.key))].slice(0, 100);
        });
        setGErr(null);
    };
    const dropPic = (key) => setPics((cur) => { const p = cur.find((x) => x.key === key); if (p) URL.revokeObjectURL(p.url); return cur.filter((x) => x.key !== key); });
    const clearPics = () => { pics.forEach((p) => URL.revokeObjectURL(p.url)); setPics([]); if (fileRef.current) fileRef.current.value = ''; };

    const postChunk = (data) => new Promise((resolve, reject) => {
        router.post(route('teacher.materials.store'), data, {
            preserveScroll: true, forceFormData: true,
            onSuccess: (page) => {
                const errs = page?.props?.errors || {};
                Object.keys(errs).length ? reject(errs) : resolve();
            },
            onError: (errs) => reject(errs),
        });
    });

    const submitGallery = async () => {
        if (!form.data.title.trim()) { setGErr('عنوان را بنویس؛ روی همه‌ی عکس‌ها می‌نشیند.'); return; }
        if (!pics.length) { setGErr('دستِ‌کم یک عکس انتخاب کن.'); return; }
        setGErr(null);
        const total = pics.length;
        try {
            setGBusy({ done: 0, total, phase: 'آماده‌سازیِ عکس‌ها' });
            const files = [];
            for (const p of pics) {
                files.push(await compressImage(p.file));
                setGBusy({ done: files.length, total, phase: 'آماده‌سازیِ عکس‌ها' });
            }
            const chunks = chunkFiles(files);
            const base = {
                type: 'gallery', title: form.data.title, description: form.data.description || '',
                classroom_id: form.data.classroom_id || '',
                publish_at: pubDate ? `${pubDate} ${pubTime || '00:00'}` : '',
            };
            let sent = 0;
            for (let i = 0; i < chunks.length; i++) {
                const last = i === chunks.length - 1;
                setGBusy({ done: sent, total, phase: 'بارگذاری' });
                await postChunk({ ...base, files: chunks[i], silent: last ? 0 : 1, notify_count: last ? total : '' });
                sent += chunks[i].length;
            }
            setGBusy(null);
            clearPics();
            form.reset(); setPubDate(''); setPubTime('08:00');
        } catch (errs) {
            setGBusy(null);
            setGErr(typeof errs === 'object' ? (errs.files || errs.title || Object.values(errs)[0] || 'بارگذاری ناموفق بود.') : 'بارگذاری ناموفق بود؛ اتصال را بررسی کن و دوباره بفرست.');
        }
    };

    const submit = (e) => {
        e.preventDefault();
        if (tab === 'gallery') { submitGallery(); return; }
        form.transform((d) => ({
            ...d, type: tab,
            publish_at: pubDate ? `${pubDate} ${pubTime || '00:00'}` : null,
        }));
        form.post(route('teacher.materials.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                setPubDate(''); setPubTime('08:00');
                if (fileRef.current) fileRef.current.value = '';
            },
        });
    };

    const remove = (id) => {
        if (!confirm('این محتوا حذف شود؟')) return;
        router.delete(route('teacher.materials.destroy', id), { preserveScroll: true });
    };

    /** نمایش/مخفی‌کردنِ یک پست برای دانش‌آموزان (بدونِ حذف). */
    const toggleVisible = (id) => router.post(route('teacher.materials.visibility', id), {}, { preserveScroll: true });

    const [editing, setEditing] = useState(null);
    const [showViewers, setShowViewers] = useState(null);

    return (
        <DashLayout title="محتوای کلاس" roleLabel="معلم" menu={teacherMenu} active="materials">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            {/* تب‌های نوع محتوا */}
            <div className="dash-cards content-tabs" style={{ marginBottom: 4 }}>
                {TABS.map((t) => {
                    const count = t.worksheet ? worksheets.length : items.filter((i) => i.type === t.v).length;
                    return (
                        <button key={t.v} onClick={() => setTab(t.v)}
                            className="dcard" style={{ cursor: 'pointer', textAlign: 'center', border: tab === t.v ? '2px solid var(--gold)' : '1px solid var(--line)', background: tab === t.v ? '#fff8e8' : '#fff', fontFamily: 'inherit' }}>
                            <div style={{ fontSize: 30 }}>{t.ic}</div>
                            <div style={{ fontWeight: 800, marginTop: 6, color: 'var(--navy-800)' }}>{t.t}</div>
                            <div style={{ color: 'var(--muted)', fontSize: 12 }}>{fa(count)} مورد</div>
                        </button>
                    );
                })}
            </div>

            {tab === 'worksheet' ? <WorksheetPanel worksheets={worksheets} /> : (
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.4fr', gap: 20, alignItems: 'start' }} className="themes-grid">
                {/* فرم بارگذاری */}
                <form onSubmit={submit} className="panel">
                    <h3>{active.ic} افزودن {active.t}</h3>
                    <Field label="عنوان" err={form.errors.title}>
                        <input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder={`عنوان ${active.t}`} />
                    </Field>
                    <Field label="توضیح (اختیاری)" err={form.errors.description}>
                        <textarea className="input" rows="2" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="توضیح کوتاه…" />
                    </Field>
                    {classrooms.length > 0 && (
                        <Field label="کلاس (اختیاری)">
                            <select className="input" value={form.data.classroom_id} onChange={(e) => form.setData('classroom_id', e.target.value)}>
                                <option value="">همه‌ی کلاس‌ها</option>
                                {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </Field>
                    )}
                    {tab === 'homework' && (
                        <Field label="مهلت تحویل (اختیاری)">
                            <JalaliDatePicker value={form.data.due_at} onChange={(v) => form.setData('due_at', v)} placeholder="انتخاب مهلت" />
                        </Field>
                    )}
                    <Field label="زمان انتشار (اختیاری)" err={form.errors.publish_at}>
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            <div style={{ flex: '1 1 150px' }}>
                                <JalaliDatePicker value={pubDate} onChange={setPubDate} placeholder="تاریخ انتشار" />
                            </div>
                            <input type="time" className="input" value={pubTime} onChange={(e) => setPubTime(e.target.value)}
                                style={{ width: 120 }} dir="ltr" disabled={!pubDate} />
                            {pubDate && <button type="button" className="btn btn-ghost btn-sm" onClick={() => setPubDate('')}>پاک‌کردن</button>}
                        </div>
                        <div className="xp-note">{pubDate ? '⏰ در تاریخ و ساعتِ انتخابی منتشر و اعلان می‌شود.' : 'بدون انتخابِ زمان، بلافاصله منتشر می‌شود.'}</div>
                    </Field>
                    {tab === 'gallery' ? (
                        <Field label="عکس‌ها — می‌توانی چند عکس را با هم انتخاب کنی" err={gErr}>
                            <label className="gl-drop"
                                onDragOver={(e) => { e.preventDefault(); e.currentTarget.classList.add('on'); }}
                                onDragLeave={(e) => e.currentTarget.classList.remove('on')}
                                onDrop={(e) => { e.preventDefault(); e.currentTarget.classList.remove('on'); addPics(e.dataTransfer.files); }}>
                                <input ref={fileRef} type="file" accept="image/*" multiple hidden
                                    onChange={(e) => { addPics(e.target.files); e.target.value = ''; }} />
                                <span className="gl-drop-ic">🖼️</span>
                                <b>{pics.length ? '➕ افزودنِ عکس‌های بیشتر' : 'انتخابِ عکس‌ها'}</b>
                                <small>از گالریِ گوشی چند عکس را با هم انتخاب کن یا اینجا رها کن</small>
                            </label>
                            {pics.length > 0 && (
                                <>
                                    <div className="gl-picked-h">
                                        <span>{fa(pics.length)} عکس انتخاب شد — عنوان، توضیح، کلاس و زمانِ انتشار روی همه می‌نشیند.</span>
                                        <button type="button" className="btn btn-ghost btn-sm" onClick={clearPics} disabled={!!gBusy}>پاک‌کردنِ همه</button>
                                    </div>
                                    <div className="gl-picked">
                                        {pics.map((p) => (
                                            <div key={p.key} className="gl-thumb">
                                                <img src={p.url} alt="" />
                                                {!gBusy && <button type="button" onClick={() => dropPic(p.key)} title="برداشتن">✕</button>}
                                            </div>
                                        ))}
                                    </div>
                                </>
                            )}
                            {gBusy && (
                                <div className="gl-prog">
                                    <div className="gl-prog-bar"><div style={{ width: `${Math.round((gBusy.done / gBusy.total) * 100)}%` }} /></div>
                                    <span>{gBusy.phase}… {fa(gBusy.done)} از {fa(gBusy.total)}</span>
                                </div>
                            )}
                        </Field>
                    ) : (
                    <Field label={`فایل — ${active.hint}`} err={form.errors.file}>
                        <input ref={fileRef} type="file" accept={active.accept} className="input" style={{ padding: 9 }}
                            onChange={onPickFile} />
                        {form.data.duration_seconds > 0 && (
                            <div className="xp-note ok">
                                ⏱️ مدت تشخیص داده شد: <b>{secFmt(form.data.duration_seconds)}</b>
                            </div>
                        )}
                    </Field>
                    )}
                    {tab !== 'gallery' && (
                    <Field label="یا لینک بیرونی (اختیاری)" err={form.errors.external_url}>
                        <input className="input" value={form.data.external_url} onChange={(e) => form.setData('external_url', e.target.value)} placeholder="https://…" dir="ltr" />
                    </Field>
                    )}

                    {/* امتیاز — فقط برای محتوای پخش‌شونده معنا دارد */}
                    {active.playable && (
                        <Field label="امتیازِ تکمیل (اختیاری)" err={form.errors.xp_reward}>
                            <input className="input" type="number" min="0" max="100" inputMode="numeric"
                                value={form.data.xp_reward}
                                onChange={(e) => form.setData('xp_reward', e.target.value)}
                                placeholder={form.data.duration_seconds > 0 ? `خودکار: ${autoXp(form.data.duration_seconds)}` : 'خودکار'} />
                            <div className="xp-note">
                                خالی بگذارید تا خودکار از روی مدت حساب شود (هر دقیقه ۲ امتیاز، بین ۵ تا ۲۵).
                                <br />
                                امتیاز <b>فقط یک‌بار</b> و <b>فقط پس از پخشِ کامل بدونِ جلو زدن</b> داده می‌شود.
                            </div>
                        </Field>
                    )}
                    {form.progress && (
                        <div style={{ height: 6, background: 'var(--line)', borderRadius: 6, overflow: 'hidden', margin: '4px 0 12px' }}>
                            <div style={{ height: '100%', width: `${form.progress.percentage}%`, background: 'var(--gold)' }} />
                        </div>
                    )}
                    <button type="submit" disabled={form.processing || !!gBusy} className="btn" style={{ width: '100%' }}>
                        {form.processing || gBusy ? 'در حال بارگذاری…'
                            : tab === 'gallery' ? (pics.length > 1 ? `➕ افزودنِ ${fa(pics.length)} عکس به گالری` : '➕ افزودن به گالری')
                            : `➕ افزودن ${active.t}`}
                    </button>
                    <p style={{ color: 'var(--muted)', fontSize: 12, marginTop: 10 }}>
                        {tab === 'gallery'
                            ? 'عکس‌های بزرگ پیش از ارسال خودکار کوچک می‌شوند تا سریع بارگذاری شوند. هر عکس جدا قابلِ ویرایش، مخفی‌کردن و حذف است.'
                            : 'حداکثر حجم فایل: ۲۰ مگابایت. دانش‌آموزان کلاس این محتوا را می‌بینند.'}
                    </p>
                </form>

                {/* لیست محتوا */}
                <div className="panel">
                    <h3>{active.ic} {active.t}‌های بارگذاری‌شده</h3>
                    {list.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز چیزی در این بخش اضافه نکرده‌ای.</p>}

                    {tab === 'gallery' ? (
                        <GalleryByDate list={list} render={(i) => (
                                <div key={i.id} style={{ border: '1px solid var(--line)', borderRadius: 14, overflow: 'hidden', position: 'relative' }}>
                                    {i.url && <a href={i.url} target="_blank" rel="noreferrer"><img src={i.url} alt={i.title} loading="lazy" style={{ width: '100%', height: 110, objectFit: 'cover', display: 'block' }} /></a>}
                                    <div style={{ padding: '8px 10px' }}>
                                        <div style={{ fontWeight: 700, fontSize: 13 }}>{i.title}</div>
                                        <div style={{ color: 'var(--muted)', fontSize: 11 }}>{i.time ? `🕒 ${i.time}` : fa(i.date)} · 👁️ {fa(i.views_count)}</div>
                                        <StatusBadge item={i} />
                                        <button onClick={() => setEditing(i)} className="btn btn-ghost btn-sm" style={{ marginTop: 6, width: '100%', padding: '4px' }}>✏️ ویرایش</button>
                                        <button onClick={() => toggleVisible(i.id)} className="btn btn-ghost btn-sm" style={{ marginTop: 4, width: '100%', padding: '4px' }}>
                                            {i.is_visible ? '🙈 مخفی‌کردن' : '👁️ نمایش'}
                                        </button>
                                    </div>
                                    <button onClick={() => remove(i.id)} title="حذف"
                                        style={{ position: 'absolute', top: 6, insetInlineEnd: 6, background: 'rgba(232,80,91,.9)', color: '#fff', border: 0, borderRadius: 8, width: 26, height: 26, cursor: 'pointer' }}>✕</button>
                                </div>
                            )} />
                    ) : (
                        list.map((i) => (
                            <div key={i.id} style={{ padding: '13px 0', borderBottom: '1px solid var(--line)' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                                    <div style={{ minWidth: 0 }}>
                                        <div style={{ fontWeight: 800 }}>{active.ic} {i.title} <StatusBadge item={i} inline /></div>
                                        {i.description && <div style={{ color: 'var(--muted)', fontSize: 13 }}>{i.description}</div>}
                                        <div style={{ color: 'var(--muted-2)', fontSize: 12, marginTop: 2 }}>
                                            {fa(i.date)}
                                            {i.duration ? ` · ⏱️ ${secFmt(i.duration)}` : ''}
                                            {active.playable ? ` · ⭐ ${fa(i.xp_value)} امتیاز${i.xp_reward != null ? ' (دستی)' : ''}` : ''}
                                            {i.due_at && ` · مهلت: ${fa(i.due_at)}`}
                                            {' · '}
                                            <button onClick={() => setShowViewers(showViewers === i.id ? null : i.id)}
                                                style={{ border: 0, background: 'none', color: 'var(--navy-800)', cursor: 'pointer', fontFamily: 'inherit', fontSize: 12, fontWeight: 700 }}>
                                                👁️ {fa(i.views_count)} نفر باز کردند
                                                {active.playable ? ` · ✅ ${fa(i.completed_count ?? 0)} کامل دیدند` : ''}
                                            </button>
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                        {i.url && <a href={i.url} target="_blank" rel="noreferrer" className="btn btn-ghost btn-sm">{i.type === 'podcast' ? '▶️ پخش' : '⬇️ دریافت'}</a>}
                                        <button onClick={() => setEditing(i)} className="btn btn-ghost btn-sm">✏️ ویرایش</button>
                                        <button onClick={() => toggleVisible(i.id)} className="btn btn-ghost btn-sm" title={i.is_visible ? 'مخفی‌کردن از دانش‌آموزان' : 'نمایش به دانش‌آموزان'}>
                                            {i.is_visible ? '🙈 مخفی' : '👁️ نمایش'}
                                        </button>
                                        <button onClick={() => remove(i.id)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>حذف</button>
                                    </div>
                                </div>
                                {showViewers === i.id && <Viewers item={i} />}
                            </div>
                        ))
                    )}
                </div>
            </div>
            )}

            {editing && <EditModal item={editing} classrooms={classrooms} onClose={() => setEditing(null)} />}
        </DashLayout>
    );
}

/** بخشِ پنجمِ «مطالب و محتوا»: کاربرگ‌ها — ساخت، بایگانی و مدیریت. */
function WorksheetPanel({ worksheets }) {
    const published = worksheets.filter((w) => w.published).length;
    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <div className="panel" style={{ background: 'linear-gradient(135deg,#fff8e8,#fff)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 34 }}>🎨</span>
                    <div style={{ flex: 1, minWidth: 180 }}>
                        <h3 style={{ margin: 0 }}>کاربرگ‌ها (کاربرگ‌سازِ هوشمند)</h3>
                        <p style={{ margin: '4px 0 0', color: 'var(--muted)', fontSize: 13 }}>کاربرگ را دستی بساز، فایل آماده را بارگذاری کن، یا با هوش مصنوعی (به‌همراه تصویرِ تم‌دار) تولید کن. پس از انتشار برای کلاس، به دانش‌آموزان اعلان می‌شود.</p>
                    </div>
                    <Link href={route('teacher.worksheets.create')} className="btn">➕ ساخت کاربرگ جدید</Link>
                </div>
                <div style={{ display: 'flex', gap: 10, marginTop: 12, flexWrap: 'wrap' }}>
                    <span className="tag tag-info">مجموع: {fa(worksheets.length)}</span>
                    <span className="tag tag-ok">منتشرشده: {fa(published)}</span>
                    <span className="tag">پیش‌نویس: {fa(worksheets.length - published)}</span>
                </div>
            </div>

            <div className="panel">
                <h3>🗄️ بایگانیِ کاربرگ‌ها</h3>
                {worksheets.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز کاربرگی نساخته‌ای. با دکمه‌ی «ساخت کاربرگ جدید» شروع کن.</p>}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(250px,1fr))', gap: 12, marginTop: 8 }}>
                    {worksheets.map((w) => (
                        <div key={w.id} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 13 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <b style={{ flex: 1 }}>{w.title}</b>
                                <span className={`tag ${w.published ? 'tag-ok' : ''}`} style={{ fontSize: 11 }}>{w.published ? 'منتشر' : 'پیش‌نویس'}</span>
                            </div>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 6 }}>
                                {[w.subject, w.grade, w.lesson_no ? `درس ${w.lesson_no}` : null].filter(Boolean).join(' · ')}
                                {' · '}{fa(w.count)} سؤال{w.has_image ? ' · 🖼️' : ''}
                            </div>
                            <div style={{ fontSize: 11.5, color: 'var(--muted-2)', marginTop: 4 }}>{fa(w.date)} · 📥 {fa(w.submissions)} پاسخ</div>
                            <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                                <Link href={route('teacher.worksheets.show', w.id)} className="btn btn-ghost btn-sm">📄 مدیریت و پاسخ‌ها</Link>
                                {w.can_edit !== false && <Link href={`${route('teacher.worksheets.show', w.id)}?edit=1`} className="btn btn-ghost btn-sm">✏️ ویرایش</Link>}
                                {w.can_edit !== false && (
                                    <button type="button" className="btn btn-ghost btn-sm" style={{ color: '#b0333f' }}
                                        onClick={() => { if (confirm(`کاربرگ «${w.title}» از بایگانی و بانک حذف شود؟`)) router.delete(route('teacher.worksheets.destroy', w.id), { preserveScroll: true }); }}>
                                        🗑️ حذف
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

/** وضعیتِ انتشارِ یک پست: منتشرشده / زمان‌بندی‌شده / مخفی. */
function StatusBadge({ item, inline }) {
    const st = !item.is_visible
        ? { t: '🙈 مخفی', bg: '#f1f3f7', fg: '#5a6478' }
        : item.live === false
            ? { t: `⏰ انتشار: ${fa(item.publish_at || '')}`, bg: '#fff4e0', fg: '#a05c00' }
            : null;
    if (!st) return null;
    return (
        <span style={{
            display: 'inline-block', marginTop: inline ? 0 : 6, marginInlineStart: inline ? 6 : 0,
            background: st.bg, color: st.fg, borderRadius: 8, padding: '2px 8px', fontSize: 11, fontWeight: 700,
        }}>{st.t}</span>
    );
}

function Viewers({ item }) {
    if (!item.viewers || item.viewers.length === 0) {
        return <div style={{ marginTop: 8, background: '#f6f8fc', borderRadius: 10, padding: '8px 12px', fontSize: 12.5, color: 'var(--muted)' }}>هنوز کسی این محتوا را ندیده است.</div>;
    }
    return (
        <div style={{ marginTop: 8, background: '#f6f8fc', borderRadius: 10, padding: '8px 12px' }}>
            {item.viewers.map((v, k) => (
                <div key={k} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, padding: '3px 0' }}>
                    <span>👤 {v.name}</span>
                    <span style={{ color: 'var(--muted)' }}>
                        {v.completed ? '✅ کامل' : (v.seconds > 0 ? `⏳ ${secFmt(v.seconds)}` : '👁️ باز کرد')}{v.xp > 0 ? ` · ⚡${fa(v.xp)}` : ''}
                    </span>
                </div>
            ))}
        </div>
    );
}

function EditModal({ item, classrooms, onClose }) {
    const form = useForm({
        title: item.title || '', description: item.description || '',
        classroom_id: item.classroom_id || '', external_url: item.external_url || '',
        due_at: item.due_at_raw || '', file: null,
    });

    // زمانِ انتشار جدا نگه داشته می‌شود تا «خالی‌کردن» یعنی انتشارِ فوری
    const raw = item.publish_at_raw || '';
    const [pubDate, setPubDate] = useState(raw ? raw.slice(0, 10) : '');
    const [pubTime, setPubTime] = useState(raw ? raw.slice(11, 16) : '08:00');

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, publish_at: pubDate ? `${pubDate} ${pubTime || '00:00'}` : null }));
        form.post(route('teacher.materials.update', item.id), {
            preserveScroll: true, forceFormData: true, onSuccess: onClose,
        });
    };
    return (
        <div onClick={onClose} style={{ position: 'fixed', inset: 0, background: 'rgba(10,20,40,.55)', display: 'grid', placeItems: 'center', zIndex: 60, padding: 16 }}>
            <form onClick={(e) => e.stopPropagation()} onSubmit={submit} className="panel" style={{ maxWidth: 460, width: '100%', maxHeight: '90vh', overflowY: 'auto' }}>
                <h3>✏️ ویرایش محتوا</h3>
                <Field label="عنوان" err={form.errors.title}>
                    <input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label="توضیح" err={form.errors.description}>
                    <textarea className="input" rows="2" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </Field>
                {classrooms.length > 0 && (
                    <Field label="کلاس">
                        <select className="input" value={form.data.classroom_id} onChange={(e) => form.setData('classroom_id', e.target.value)}>
                            <option value="">همه‌ی کلاس‌ها</option>
                            {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </Field>
                )}
                {item.type === 'homework' && (
                    <Field label="مهلت تحویل">
                        <JalaliDatePicker value={form.data.due_at} onChange={(v) => form.setData('due_at', v)} placeholder="انتخاب مهلت" />
                    </Field>
                )}
                <Field label="زمان انتشار" err={form.errors.publish_at}>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <div style={{ flex: '1 1 150px' }}>
                            <JalaliDatePicker value={pubDate} onChange={setPubDate} placeholder="تاریخ انتشار" />
                        </div>
                        <input type="time" className="input" value={pubTime} onChange={(e) => setPubTime(e.target.value)}
                            style={{ width: 120 }} dir="ltr" disabled={!pubDate} />
                        {pubDate && <button type="button" className="btn btn-ghost btn-sm" onClick={() => setPubDate('')}>انتشارِ فوری</button>}
                    </div>
                    <div className="xp-note">{pubDate ? '⏰ در تاریخ و ساعتِ انتخابی منتشر می‌شود.' : 'بدون زمان، همین حالا منتشر است.'}</div>
                </Field>
                <Field label="جایگزینی فایل (اختیاری)" err={form.errors.file}>
                    <input type="file" className="input" style={{ padding: 9 }} onChange={(e) => form.setData('file', e.target.files[0] || null)} />
                </Field>
                <Field label="لینک بیرونی (اختیاری)" err={form.errors.external_url}>
                    <input className="input" value={form.data.external_url} onChange={(e) => form.setData('external_url', e.target.value)} placeholder="https://…" dir="ltr" />
                </Field>
                <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                    <button type="submit" disabled={form.processing} className="btn" style={{ flex: 1 }}>{form.processing ? 'در حال ذخیره…' : 'ذخیره تغییرات'}</button>
                    <button type="button" onClick={onClose} className="btn btn-ghost">انصراف</button>
                </div>
            </form>
        </div>
    );
}

function Field({ label, err, children }) {
    return <div className="field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
