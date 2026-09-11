import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect, useRef, Fragment } from 'react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
import PersonCell from '@/Components/PersonCell';
import ListSearch, { normalizeFa } from '@/Components/ListSearch';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Students() {
    const { students = [], classrooms = [], teachers = [], school, audit = [], flash } = usePage().props;
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

    const edit = useForm({ name: '', phone: '', national_id: '', password: '', parent_pin: '' });
    const [editId, setEditId] = useState(null);
    const startEdit = (s) => { setEditId(s.id); edit.setData({ name: s.name || '', phone: s.phone || '', national_id: s.national_id || '', password: '', parent_pin: s.parent_pin || '' }); edit.clearErrors(); };
    const saveEdit = (e) => { e.preventDefault(); edit.put(route('manage.users.update', editId), { preserveScroll: true, onSuccess: () => setEditId(null) }); };
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
                    <StudentTable list={list} classrooms={classrooms} currentClassId={cls.id} {...{ editId, edit, startEdit, saveEdit, del, moveStudent }} />
                </div>
            ))}

            {noClass.length > 0 && (
                <div className="panel no-print">
                    <h3 style={{ marginTop: 0 }}>❓ بدون کلاس ({fa(noClass.length)})</h3>
                    <StudentTable list={noClass} classrooms={classrooms} currentClassId={0} {...{ editId, edit, startEdit, saveEdit, del, moveStudent }} />
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
        </DashLayout>
    );
}

function StudentTable({ list, classrooms, currentClassId, editId, edit, startEdit, saveEdit, del, moveStudent }) {
    if (list.length === 0) return <p style={{ color: 'var(--muted)' }}>دانش‌آموزی در این کلاس نیست.</p>;
    return (
        <div style={{ overflowX: 'auto' }}>
            <table className="tbl">
                <thead><tr><th>#</th><th>نام</th><th>موبایل</th><th>کد ملی</th><th>امتیاز</th><th>تغییر کلاس</th><th style={{ textAlign: 'left' }}>عملیات</th></tr></thead>
                <tbody>{list.map((s, i) => (
                    <Fragment key={s.id}>
                        <tr>
                            <td>{fa(i + 1)}</td><td><PersonCell name={s.name} avatar={s.avatar} size={32} /></td><td dir="ltr">{s.phone || '—'}</td><td dir="ltr">{s.national_id || '—'}</td>
                            <td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.xp)}</td>
                            <td>
                                <select className="input" style={{ width: 'auto', padding: '6px 9px', fontSize: 12.5 }} value="" onChange={(e) => moveStudent(s, e.target.value)}>
                                    <option value="">انتقال به…</option>
                                    {classrooms.filter((c) => c.id !== currentClassId).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            </td>
                            <td style={{ textAlign: 'left', whiteSpace: 'nowrap' }}>
                                <button onClick={() => (editId === s.id ? startEdit({ id: null }) : startEdit(s))} className="btn btn-ghost btn-sm" title="ویرایش">✏️</button>
                                <button onClick={() => del(s)} className="btn btn-ghost btn-sm" title="حذف" style={{ color: '#e8505b', marginInlineStart: 4 }}>🗑️</button>
                            </td>
                        </tr>
                        {editId === s.id && (
                            <tr><td colSpan={7} style={{ background: 'var(--cream)' }}>
                                <form onSubmit={saveEdit} style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 10, alignItems: 'end', padding: 8 }}>
                                    <F label="نام" err={edit.errors.name}><input className="input" value={edit.data.name} onChange={(e) => edit.setData('name', e.target.value)} /></F>
                                    <F label="موبایل" err={edit.errors.phone}><input className="input" value={edit.data.phone} onChange={(e) => edit.setData('phone', e.target.value)} dir="ltr" /></F>
                                    <F label="کد ملی" err={edit.errors.national_id}><input className="input" value={edit.data.national_id} onChange={(e) => edit.setData('national_id', e.target.value)} dir="ltr" /></F>
                                    <F label="رمز جدید (اختیاری)"><input className="input" value={edit.data.password} onChange={(e) => edit.setData('password', e.target.value)} placeholder="بدون تغییر" /></F>
                                    <F label="🔐 رمزِ بخشِ والدین" err={edit.errors.parent_pin}><input className="input" value={edit.data.parent_pin} onChange={(e) => edit.setData('parent_pin', e.target.value.replace(/\D/g, '').slice(0, 8))} dir="ltr" placeholder="مثلاً 12345" style={{ letterSpacing: 3, fontWeight: 700 }} /></F>
                                    <div style={{ display: 'flex', gap: 8 }}><button type="submit" disabled={edit.processing} className="btn btn-sm">💾 ذخیره</button></div>
                                </form>
                            </td></tr>
                        )}
                    </Fragment>
                ))}</tbody>
            </table>
        </div>
    );
}

function F({ label, err, children }) {
    return <div className="field" style={{ margin: 0 }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
