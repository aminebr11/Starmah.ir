import { usePage, useForm, router, Link } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

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

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, type: tab }));
        form.post(route('teacher.materials.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => { form.reset(); if (fileRef.current) fileRef.current.value = ''; },
        });
    };

    const remove = (id) => {
        if (!confirm('این محتوا حذف شود؟')) return;
        router.delete(route('teacher.materials.destroy', id), { preserveScroll: true });
    };

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
                    <Field label={`فایل — ${active.hint}`} err={form.errors.file}>
                        <input ref={fileRef} type="file" accept={active.accept} className="input" style={{ padding: 9 }}
                            onChange={onPickFile} />
                        {form.data.duration_seconds > 0 && (
                            <div className="xp-note ok">
                                ⏱️ مدت تشخیص داده شد: <b>{secFmt(form.data.duration_seconds)}</b>
                            </div>
                        )}
                    </Field>
                    <Field label="یا لینک بیرونی (اختیاری)" err={form.errors.external_url}>
                        <input className="input" value={form.data.external_url} onChange={(e) => form.setData('external_url', e.target.value)} placeholder="https://…" dir="ltr" />
                    </Field>

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
                    <button type="submit" disabled={form.processing} className="btn" style={{ width: '100%' }}>
                        {form.processing ? 'در حال بارگذاری…' : `➕ افزودن ${active.t}`}
                    </button>
                    <p style={{ color: 'var(--muted)', fontSize: 12, marginTop: 10 }}>حداکثر حجم فایل: ۲۰ مگابایت. دانش‌آموزان کلاس این محتوا را می‌بینند.</p>
                </form>

                {/* لیست محتوا */}
                <div className="panel">
                    <h3>{active.ic} {active.t}‌های بارگذاری‌شده</h3>
                    {list.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز چیزی در این بخش اضافه نکرده‌ای.</p>}

                    {tab === 'gallery' ? (
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(140px,1fr))', gap: 12 }}>
                            {list.map((i) => (
                                <div key={i.id} style={{ border: '1px solid var(--line)', borderRadius: 14, overflow: 'hidden', position: 'relative' }}>
                                    {i.url && <img src={i.url} alt={i.title} style={{ width: '100%', height: 110, objectFit: 'cover' }} />}
                                    <div style={{ padding: '8px 10px' }}>
                                        <div style={{ fontWeight: 700, fontSize: 13 }}>{i.title}</div>
                                        <div style={{ color: 'var(--muted)', fontSize: 11 }}>{fa(i.date)} · 👁️ {fa(i.views_count)}</div>
                                        <button onClick={() => setEditing(i)} className="btn btn-ghost btn-sm" style={{ marginTop: 6, width: '100%', padding: '4px' }}>✏️ ویرایش</button>
                                    </div>
                                    <button onClick={() => remove(i.id)} title="حذف"
                                        style={{ position: 'absolute', top: 6, insetInlineEnd: 6, background: 'rgba(232,80,91,.9)', color: '#fff', border: 0, borderRadius: 8, width: 26, height: 26, cursor: 'pointer' }}>✕</button>
                                </div>
                            ))}
                        </div>
                    ) : (
                        list.map((i) => (
                            <div key={i.id} style={{ padding: '13px 0', borderBottom: '1px solid var(--line)' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                                    <div style={{ minWidth: 0 }}>
                                        <div style={{ fontWeight: 800 }}>{active.ic} {i.title}</div>
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
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
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
    const submit = (e) => {
        e.preventDefault();
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
