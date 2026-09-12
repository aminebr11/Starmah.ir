import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect, useRef, Fragment } from 'react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
import PersonCell from '@/Components/PersonCell';
import ListSearch, { normalizeFa } from '@/Components/ListSearch';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Students() {
    const { students = [], classrooms = [], teachers = [], school, audit = [], themes = [], grades = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const [q, setQ] = useState('');
    const [printId, setPrintId] = useState(null);
    const printedAt = new Date().toLocaleDateString('fa-IR');

    // گزارش‌ساز: انتخابِ جهت و ستون‌های فهرستِ چاپی
    const ROSTER_COLS = [
        { key: 'name', label: 'نام و نام‌خانوادگی' }, { key: 'national_id', label: 'کدِ ملی' },
        { key: 'birth', label: 'تاریخِ تولد' }, { key: 'grade', label: 'پایه' },
        { key: 'class', label: 'کلاس / معلم' }, { key: 'team', label: 'تیم' },
        { key: 'phone', label: 'موبایل' }, { key: 'guardian', label: 'سرپرست' },
        { key: 'g_phone', label: 'موبایلِ سرپرست' }, { key: 'xp', label: 'امتیاز' },
    ];
    const [builder, setBuilder] = useState(false);
    const [orient, setOrient] = useState('portrait');
    const [cols, setCols] = useState(ROSTER_COLS.map((c) => c.key));
    const toggleCol = (k) => setCols((cs) => cs.includes(k) ? cs.filter((x) => x !== k) : [...cs, k]);
    const openRoster = () => {
        const ordered = ROSTER_COLS.filter((c) => cols.includes(c.key)).map((c) => c.key);
        const url = `/print/roster?orient=${orient}&cols=${ordered.join(',')}&back=${encodeURIComponent(window.location.href)}`;
        window.open(url, '_blank', 'noopener');
    };

    // چاپِ لیستِ یک کلاس
    useEffect(() => {
        if (printId !== null) {
            const t = setTimeout(() => { window.print(); setPrintId(null); }, 120);
            return () => clearTimeout(t);
        }
    }, [printId]);

    // پرونده‌ی کاملِ دانش‌آموز (نمایش / ویرایش / چاپ) — به‌جای ویرایشِ چندفیلدیِ قبلی
    const [openId, setOpenId] = useState(null);
    const openStudent = students.find((s) => s.id === openId) || null;
    const del = (s) => { if (confirm(`دانش‌آموز «${s.name}» حذف شود؟`)) router.delete(route('manage.users.destroy', s.id), { preserveScroll: true }); };
    const moveStudent = (s, cid) => { if (cid && router.post(route('manage.users.move', s.id), { classroom_id: cid }, { preserveScroll: true })); };
    const reassignTeacher = (cid, tid) => { if (tid) router.post(route('manage.classrooms.teacher', cid), { teacher_id: tid }, { preserveScroll: true }); };

    // جست‌وجوی نرمال‌شده: «ی/ك» عربی و نیم‌فاصله دیگر مانعِ یافتن نمی‌شوند
    const nq = normalizeFa(q);
    const shown = students.filter((s) => !nq
        || normalizeFa(s.name).includes(nq)
        || normalizeFa(s.phone).includes(nq)
        || normalizeFa(s.national_id).includes(nq)
        || normalizeFa(s.class).includes(nq)
        || normalizeFa(s.guardian_name).includes(nq));

    // گروه‌بندی بر اساس کلاس
    const groups = classrooms.map((c) => ({ cls: c, list: shown.filter((s) => s.classroom_id === c.id) }));
    const noClass = shown.filter((s) => !s.classroom_id);

    const printClass = classrooms.find((c) => c.id === printId);
    const printList = printClass ? students.filter((s) => s.classroom_id === printClass.id) : [];

    return (
        <DashLayout title="دانش‌آموزان" roleLabel="مدیر مدرسه" menu={schoolMenu} active="students"
            actions={<button onClick={() => setBuilder((b) => !b)} className="btn btn-sm">🖨️ گزارش‌سازِ فهرست</button>}>
            {banner && <div className="panel no-print" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            {/* گزارش‌سازِ فهرستِ چاپی */}
            {builder && (
                <div className="panel no-print" style={{ border: '2px solid var(--gold)', background: '#fffdf6' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 12 }}>
                        <h3 style={{ margin: 0 }}>🖨️ گزارش‌سازِ فهرستِ دانش‌آموزان</h3>
                        <span style={{ fontSize: 12.5, color: 'var(--muted)' }}>جهتِ صفحه و ستون‌های دلخواه را انتخاب کن، سپس «باز کردنِ گزارش».</span>
                    </div>

                    <div style={{ display: 'flex', gap: 8, marginBottom: 14, flexWrap: 'wrap' }}>
                        <button onClick={() => setOrient('portrait')} className={`btn btn-sm ${orient === 'portrait' ? '' : 'btn-ghost'}`}>📄 عمودی (Portrait)</button>
                        <button onClick={() => setOrient('landscape')} className={`btn btn-sm ${orient === 'landscape' ? '' : 'btn-ghost'}`}>📃 افقی (Landscape)</button>
                        <span style={{ fontSize: 12, color: 'var(--muted)', alignSelf: 'center' }}>
                            {orient === 'landscape' ? 'برای جدول‌های پهن با ستونِ زیاد مناسب است.' : 'برای فهرستِ ساده و ستونِ کم مناسب است.'}
                        </span>
                    </div>

                    <div style={{ fontWeight: 700, fontSize: 13, marginBottom: 6 }}>ستون‌های گزارش ({fa(cols.length)} انتخاب‌شده):</div>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 14 }}>
                        {ROSTER_COLS.map((c) => (
                            <label key={c.key} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, padding: '6px 12px', borderRadius: 20, cursor: 'pointer',
                                border: cols.includes(c.key) ? '1.5px solid var(--gold)' : '1px solid var(--line)', background: cols.includes(c.key) ? '#fff8e8' : '#fff' }}>
                                <input type="checkbox" checked={cols.includes(c.key)} onChange={() => toggleCol(c.key)} />{c.label}
                            </label>
                        ))}
                    </div>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <button onClick={openRoster} disabled={!cols.length} className="btn">📄 باز کردنِ گزارش</button>
                        <button onClick={() => setCols(ROSTER_COLS.map((c) => c.key))} className="btn btn-ghost btn-sm">همه‌ی ستون‌ها</button>
                        <button onClick={() => setCols(['name', 'class', 'guardian', 'g_phone'])} className="btn btn-ghost btn-sm">فقط تماسِ سرپرست</button>
                    </div>
                </div>
            )}

            <div className="panel no-print" style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                <h3 style={{ margin: 0 }}>🎓 دانش‌آموزان مدرسه ({fa(students.length)})</h3>
                <div style={{ marginInlineStart: 'auto', minWidth: 220, flex: '0 1 300px' }}>
                    <ListSearch value={q} onChange={setQ} placeholder="جست‌وجوی نام، موبایل، کد ملی، کلاس یا سرپرست…" />
                </div>
                {q && <span className="list-count">{fa(shown.length)} از {fa(students.length)}</span>}
            </div>

            {students.length === 0 && <div className="panel no-print"><p style={{ color: 'var(--muted)' }}>هنوز دانش‌آموزی ثبت‌نام نکرده.</p></div>}

            {/* هر کلاس در یک بخش */}
            {groups.map(({ cls, list }) => (
                <div key={cls.id} className="panel no-print">
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 6 }}>
                        <h3 style={{ margin: 0 }}>🏫 {cls.name}{cls.grade ? ` — پایه ${cls.grade}` : ''} <span style={{ fontSize: 13, fontWeight: 500, color: 'var(--muted)' }}>({fa(list.length)} دانش‌آموز)</span></h3>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginInlineStart: 'auto', flexWrap: 'wrap' }}>
                            <label style={{ fontSize: 12.5, color: 'var(--muted)' }}>معلم:</label>
                            <select className="input" style={{ width: 'auto', padding: '7px 10px', fontSize: 13 }} value={cls.teacher_id ?? ''} onChange={(e) => reassignTeacher(cls.id, e.target.value)}>
                                <option value="">— انتخاب معلم —</option>
                                {teachers.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                            </select>
                            <button onClick={() => setPrintId(cls.id)} className="btn btn-ghost btn-sm">🖨️ چاپ لیست کلاس</button>
                        </div>
                    </div>
                    <StudentTable list={list} classrooms={classrooms} currentClassId={cls.id} onOpen={setOpenId} del={del} moveStudent={moveStudent} />
                </div>
            ))}

            {noClass.length > 0 && (
                <div className="panel no-print">
                    <h3 style={{ marginTop: 0 }}>❓ بدون کلاس ({fa(noClass.length)})</h3>
                    <StudentTable list={noClass} classrooms={classrooms} currentClassId={0} onOpen={setOpenId} del={del} moveStudent={moveStudent} />
                </div>
            )}

            {/* سوابق تغییرات */}
            <div className="panel no-print">
                <h3 style={{ marginTop: 0 }}>📋 سوابق تغییرات (چه کسی چه چیزی را تغییر داد)</h3>
                {audit.length === 0 ? <p style={{ color: 'var(--muted)' }}>هنوز تغییری ثبت نشده.</p> : (
                    <div style={{ overflowX: 'auto' }}>
                        <table className="tbl">
                            <thead><tr><th>زمان</th><th>کاربر</th><th>اقدام</th><th>توضیح</th></tr></thead>
                            <tbody>{audit.map((a, i) => (
                                <tr key={i}><td style={{ whiteSpace: 'nowrap', fontSize: 12.5, color: 'var(--muted)' }}>{a.when}</td>
                                    <td style={{ fontWeight: 700 }}>{a.actor} <span style={{ fontSize: 11, color: 'var(--muted)' }}>({a.role})</span></td>
                                    <td><span className="tag tag-info">{a.action}</span></td><td style={{ fontSize: 13 }}>{a.summary}</td></tr>
                            ))}</tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* ===== لیستِ چاپِ کلاس (فقط هنگام چاپ) ===== */}
            {printClass && (
                <div className="roster-print">
                    <div className="roster-head">
                        <img src="/brand/logo-emblem.png" alt="" />
                        <div style={{ flex: 1, textAlign: 'center' }}>
                            <div className="rh-title">لیست دانش‌آموزان کلاس</div>
                            <div className="rh-sub">{school?.name} — {printClass.name}{printClass.grade ? ` (پایه ${printClass.grade})` : ''}</div>
                        </div>
                        <div className="rh-meta"><div>معلم: <b>{printClass.teacher || '—'}</b></div><div>تعداد: <b>{fa(printList.length)}</b></div><div>تاریخ چاپ: {printedAt}</div></div>
                    </div>
                    <table className="roster-tbl">
                        <thead><tr><th>#</th><th>نام و نام خانوادگی</th><th>کد ملی</th><th>موبایل</th><th>تاریخ تولد</th><th>نام ولی</th><th>تلفن ولی</th></tr></thead>
                        <tbody>{printList.map((s, i) => (
                            <tr key={s.id}><td>{fa(i + 1)}</td><td>{s.name}</td><td>{s.national_id || '—'}</td><td dir="ltr">{s.phone || '—'}</td>
                                <td>{s.jbirth || '—'}</td><td>{s.guardian_name || '—'}</td><td dir="ltr">{s.guardian_phone || '—'}</td></tr>
                        ))}</tbody>
                    </table>
                    <div className="roster-signs"><div>امضای معلم<span /></div><div>امضای مدیر مدرسه<span /></div><div>مهر مدرسه<span /></div></div>
                </div>
            )}
            {openStudent && (
                <StudentRecord s={openStudent} themes={themes} grades={grades} onClose={() => setOpenId(null)} />
            )}
        </DashLayout>
    );
}

function StudentTable({ list, classrooms, currentClassId, onOpen, del, moveStudent }) {
    if (list.length === 0) return <p style={{ color: 'var(--muted)' }}>دانش‌آموزی در این کلاس نیست.</p>;
    return (
        <div style={{ overflowX: 'auto' }}>
            <table className="tbl">
                <thead><tr><th>#</th><th>نام</th><th>موبایل</th><th>کد ملی</th><th>سرپرست</th><th>امتیاز</th><th>تغییر کلاس</th><th style={{ textAlign: 'left' }}>عملیات</th></tr></thead>
                <tbody>{list.map((s, i) => (
                    <tr key={s.id}>
                        <td>{fa(i + 1)}</td>
                        <td>
                            <button type="button" onClick={() => onOpen(s.id)} title="بازکردنِ پرونده"
                                style={{ border: 0, background: 'none', padding: 0, cursor: 'pointer', font: 'inherit', textAlign: 'start' }}>
                                <PersonCell name={s.name} avatar={s.avatar} size={32} />
                            </button>
                        </td>
                        <td dir="ltr">{s.phone || '—'}</td>
                        <td dir="ltr">{s.national_id || '—'}</td>
                        <td style={{ fontSize: 12.5 }}>{s.guardian_name || '—'}{s.guardian_phone ? <div dir="ltr" style={{ color: 'var(--muted)', fontSize: 11.5 }}>{s.guardian_phone}</div> : null}</td>
                        <td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.xp)}</td>
                        <td>
                            <select className="input" style={{ width: 'auto', padding: '6px 9px', fontSize: 12.5 }} value="" onChange={(e) => moveStudent(s, e.target.value)}>
                                <option value="">انتقال به…</option>
                                {classrooms.filter((c) => c.id !== currentClassId).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </td>
                        <td style={{ textAlign: 'left', whiteSpace: 'nowrap' }}>
                            <button onClick={() => onOpen(s.id)} className="btn btn-ghost btn-sm" title="پرونده‌ی کامل">📋</button>
                            <a href={`/print/student/${s.id}/card`} target="_blank" rel="noreferrer" className="btn btn-ghost btn-sm" title="چاپِ شناسنامه" style={{ marginInlineStart: 4 }}>🖨️</a>
                            <button onClick={() => del(s)} className="btn btn-ghost btn-sm" title="حذف" style={{ color: '#e8505b', marginInlineStart: 4 }}>🗑️</button>
                        </td>
                    </tr>
                ))}</tbody>
            </table>
        </div>
    );
}

function F({ label, err, children }) {
    return <div className="field" style={{ margin: 0 }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}

/* ═══════════════════ پرونده‌ی کاملِ دانش‌آموز ═══════════════════ */
/**
 * همان اطلاعاتی که هنگامِ ثبت‌نام گرفته شده — نمایش، ویرایش و چاپ.
 * پیش از این فقط نام، موبایل، کدِ ملی و رمز قابلِ ویرایش بودند و بقیه‌ی
 * فیلدها (عکس، جنسیت، تاریخِ تولد، سرپرست، نشانی) هیچ‌جا دیده نمی‌شدند.
 */
function StudentRecord({ s, themes = [], grades = [], onClose }) {
    const [mode, setMode] = useState('view');   // view | edit
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
                            <F label="تاریخِ تولد (میلادی)" err={form.errors.birth_date}><input className="input" type="date" dir="ltr" value={form.data.birth_date} onChange={(e) => form.setData('birth_date', e.target.value)} /></F>
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
