import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

function F({ label, err, children }) {
    return <div className="field" style={{ margin: 0 }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}

/* ═══════════════════ پرونده‌ی کاملِ دانش‌آموز ═══════════════════ */
/**
 * همان اطلاعاتی که هنگامِ ثبت‌نام گرفته شده — نمایش، ویرایش و چاپ.
 * پیش از این فقط نام، موبایل، کدِ ملی و رمز قابلِ ویرایش بودند و بقیه‌ی
 * فیلدها (عکس، جنسیت، تاریخِ تولد، سرپرست، نشانی) هیچ‌جا دیده نمی‌شدند.
 */
export default function StudentRecord({ s, themes = [], grades = [], onClose, initialMode = 'view' }) {
    const [mode, setMode] = useState(initialMode);   // view | edit
    const [photo, setPhoto] = useState(null);
    const form = useForm({
        first_name: s.first_name || '', last_name: s.last_name || '',
        phone: s.phone || '', password: '',
        gender: s.gender || '', national_id: s.national_id || '',
        birth_date: s.birth_date || '', grade: s.grade || '', theme_id: s.theme_id || '',
        father_name: s.father_name || '', mother_name: s.mother_name || '',
        parent_relation: s.parent_relation || '', parent_phone: s.guardian_phone || '',
        address: s.address || '', parent_pin: '',
        avatar: null, remove_avatar: false,
    });

    const pickPhoto = (e) => {
        const f = e.target.files?.[0];
        if (!f) return;
        form.setData('avatar', f); form.setData('remove_avatar', false);
        const r = new FileReader();
        r.onload = () => setPhoto(r.result);
        r.readAsDataURL(f);
    };
    const dropPhoto = () => { setPhoto(null); form.setData('avatar', null); form.setData('remove_avatar', true); };

    const save = (e) => {
        e.preventDefault();
        form.post(route('manage.students.profile', s.id), {
            preserveScroll: true, forceFormData: true,
            onSuccess: () => { setMode('view'); setPhoto(null); form.setData('password', ''); form.setData('parent_pin', ''); },
        });
    };

    const img = photo || (form.data.remove_avatar ? null : s.avatar);
    const printUrl = `/print/student/${s.id}/card?back=${encodeURIComponent(typeof window !== 'undefined' ? window.location.href : '/')}`;

    return (
        <div onClick={onClose} className="no-print"
            style={{ position: 'fixed', inset: 0, background: 'rgba(10,16,36,.55)', zIndex: 60, display: 'flex', alignItems: 'flex-start', justifyContent: 'center', padding: 16, overflowY: 'auto' }}>
            <div onClick={(e) => e.stopPropagation()} className="panel"
                style={{ maxWidth: 780, width: '100%', margin: '20px 0', maxHeight: 'none' }}>

                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 12 }}>
                    <h3 style={{ margin: 0 }}>📋 پرونده‌ی دانش‌آموز</h3>
                    <div style={{ marginInlineStart: 'auto', display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                        {mode === 'view'
                            ? <button onClick={() => setMode('edit')} className="btn btn-sm">✏️ ویرایش</button>
                            : <button onClick={() => { setMode('view'); form.clearErrors(); }} className="btn btn-ghost btn-sm">انصراف</button>}
                        <a href={printUrl} target="_blank" rel="noreferrer" className="btn btn-ghost btn-sm">🖨️ چاپِ شناسنامه</a>
                        <a href={`/print/student/${s.id}`} target="_blank" rel="noreferrer" className="btn btn-ghost btn-sm">📊 کارنامه</a>
                        <button onClick={onClose} className="btn btn-ghost btn-sm">✕</button>
                    </div>
                </div>

                {/* ── سربرگ: عکس و شناسه ── */}
                <div style={{ display: 'flex', gap: 14, alignItems: 'flex-start', flexWrap: 'wrap', borderBottom: '1px solid var(--line)', paddingBottom: 14, marginBottom: 14 }}>
                    <div style={{ textAlign: 'center' }}>
                        <div style={{ width: 104, height: 128, borderRadius: 14, overflow: 'hidden', border: '1px solid var(--line)', background: '#f4f7fd', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 40 }}>
                            {img ? <img src={img} alt={s.name} style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : '👤'}
                        </div>
                        {mode === 'edit' && (
                            <div style={{ marginTop: 6, display: 'flex', gap: 4, justifyContent: 'center' }}>
                                <label className="btn btn-ghost btn-sm" style={{ cursor: 'pointer' }}>
                                    📷 تغییر<input type="file" accept="image/*" onChange={pickPhoto} style={{ display: 'none' }} />
                                </label>
                                {img && <button type="button" onClick={dropPhoto} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>حذف</button>}
                            </div>
                        )}
                        {form.errors.avatar && <div style={{ color: '#e8505b', fontSize: 11.5, marginTop: 4 }}>{form.errors.avatar}</div>}
                    </div>
                    <div style={{ flex: 1, minWidth: 200 }}>
                        <div style={{ fontWeight: 900, fontSize: 19 }}>{s.name}</div>
                        <div style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 3 }}>
                            {s.class || 'بدونِ کلاس'}{s.teacher ? ` · معلم: ${s.teacher}` : ''}
                        </div>
                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 8 }}>
                            <span className="tag tag-info">⚡ {fa(s.xp)} امتیاز</span>
                            {s.team && <span className="tag">{s.team}</span>}
                            {s.grade && <span className="tag">پایه‌ی {s.grade}</span>}
                            <span className="tag">ثبت‌نام: {s.joined}</span>
                        </div>
                    </div>
                </div>

                {mode === 'view' ? (
                    <>
                        <Sec t="👤 مشخصاتِ فردی" rows={[
                            ['نام و نام‌خانوادگی', s.name],
                            ['کدِ ملی', s.national_id],
                            ['جنسیت', s.gender],
                            ['تاریخِ تولد', s.jbirth],
                            ['پایه', s.grade],
                            ['موبایلِ دانش‌آموز', s.phone],
                        ]} />
                        <Sec t="👨‍👩‍👧 سرپرست و تماس" rows={[
                            ['نامِ پدر', s.father_name],
                            ['نامِ مادر', s.mother_name],
                            ['نسبتِ سرپرست', s.parent_relation],
                            ['موبایلِ سرپرست', s.guardian_phone],
                            ['نشانی', s.address],
                            ['🔐 رمزِ بخشِ والدین', s.parent_pin],
                        ]} />
                        <Sec t="🏫 تحصیلی" rows={[
                            ['کلاس', s.class],
                            ['معلم', s.teacher],
                            ['تیم/گروه', s.team],
                        ]} />
                    </>
                ) : (
                    <form onSubmit={save}>
                        <Head t="👤 مشخصاتِ فردی" />
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(165px,1fr))', gap: 12 }}>
                            <F label="نام" err={form.errors.first_name}><input className="input" value={form.data.first_name} onChange={(e) => form.setData('first_name', e.target.value)} /></F>
                            <F label="نام خانوادگی" err={form.errors.last_name}><input className="input" value={form.data.last_name} onChange={(e) => form.setData('last_name', e.target.value)} /></F>
                            <F label="کدِ ملی" err={form.errors.national_id}><input className="input" dir="ltr" maxLength={10} value={form.data.national_id} onChange={(e) => form.setData('national_id', e.target.value)} /></F>
                            <F label="جنسیت">
                                <select className="input" value={form.data.gender} onChange={(e) => form.setData('gender', e.target.value)}>
                                    <option value="">—</option><option value="پسر">پسر</option><option value="دختر">دختر</option>
                                </select>
                            </F>
                            <F label="تاریخِ تولد" err={form.errors.birth_date}><JalaliDatePicker value={form.data.birth_date || ''} onChange={(v) => form.setData('birth_date', v)} placeholder="۱۳۹۰/۰۱/۰۱" /></F>
                            <F label="پایه" err={form.errors.grade}>
                                <select className="input" value={form.data.grade} onChange={(e) => form.setData('grade', e.target.value)}>
                                    <option value="">—</option>{grades.map((g) => <option key={g} value={g}>{g}</option>)}
                                </select>
                            </F>
                            <F label="موبایلِ دانش‌آموز" err={form.errors.phone}><input className="input" dir="ltr" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} /></F>
                            <F label="تیم/گروه">
                                <select className="input" value={form.data.theme_id} onChange={(e) => form.setData('theme_id', e.target.value)}>
                                    <option value="">—</option>{themes.map((t) => <option key={t.id} value={t.id}>{t.emoji} {t.name}</option>)}
                                </select>
                            </F>
                        </div>

                        <Head t="👨‍👩‍👧 سرپرست و تماس" />
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(165px,1fr))', gap: 12 }}>
                            <F label="نامِ پدر"><input className="input" value={form.data.father_name} onChange={(e) => form.setData('father_name', e.target.value)} /></F>
                            <F label="نامِ مادر"><input className="input" value={form.data.mother_name} onChange={(e) => form.setData('mother_name', e.target.value)} /></F>
                            <F label="نسبتِ سرپرست">
                                <select className="input" value={form.data.parent_relation} onChange={(e) => form.setData('parent_relation', e.target.value)}>
                                    <option value="">—</option><option value="پدر">پدر</option><option value="مادر">مادر</option><option value="ولی">ولی</option>
                                </select>
                            </F>
                            <F label="موبایلِ سرپرست" err={form.errors.parent_phone}><input className="input" dir="ltr" value={form.data.parent_phone} onChange={(e) => form.setData('parent_phone', e.target.value)} /></F>
                        </div>
                        <F label="نشانی"><input className="input" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} /></F>

                        <Head t="🔑 دسترسی‌ها" />
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(165px,1fr))', gap: 12 }}>
                            <F label="رمزِ تازه (خالی = بدونِ تغییر)"><input className="input" dir="ltr" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} placeholder="بدون تغییر" /></F>
                            <F label={`🔐 رمزِ بخشِ والدین${s.parent_pin ? ` (فعلی: ${s.parent_pin})` : ''}`} err={form.errors.parent_pin}>
                                <input className="input" dir="ltr" value={form.data.parent_pin} onChange={(e) => form.setData('parent_pin', e.target.value.replace(/\D/g, '').slice(0, 8))} placeholder="بدون تغییر" style={{ letterSpacing: 3, fontWeight: 700 }} />
                            </F>
                        </div>

                        <div style={{ display: 'flex', gap: 8, marginTop: 14 }}>
                            <button type="submit" disabled={form.processing} className="btn">{form.processing ? 'در حال ذخیره…' : '💾 ذخیره‌ی پرونده'}</button>
                            <button type="button" onClick={() => { setMode('view'); form.clearErrors(); }} className="btn btn-ghost">انصراف</button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}

const Head = ({ t }) => (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '16px 0 8px' }}>
        <b style={{ fontSize: 13.5 }}>{t}</b>
        <span style={{ flex: 1, height: 1, background: 'var(--line)' }} />
    </div>
);

function Sec({ t, rows }) {
    return (
        <div style={{ marginBottom: 14 }}>
            <Head t={t} />
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(210px,1fr))', gap: 0, border: '1px solid var(--line)', borderRadius: 10, overflow: 'hidden' }}>
                {rows.map(([k, v]) => (
                    <div key={k} style={{ padding: '8px 11px', fontSize: 12.5, borderBottom: '1px solid var(--line)', borderInlineEnd: '1px solid var(--line)' }}>
                        <span style={{ color: 'var(--muted)' }}>{k}: </span>
                        <b dir={/موبایل|کدِ ملی|رمز/.test(k) ? 'ltr' : undefined} style={{ display: /نشانی/.test(k) ? 'block' : 'inline' }}>{v || '—'}</b>
                    </div>
                ))}
            </div>
        </div>
    );
}
