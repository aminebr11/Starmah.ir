import { usePage, useForm, router, Link } from '@inertiajs/react';
import { useState, useEffect, Fragment } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** ادمین کل: مدیریت کامل داده‌های یک مدرسه. */
export default function SchoolManage() {
    const { school, levels = [], grades = [], admins = [], teachers = [], students = [], classes = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const STATUS = { pending: 'در انتظار', active: 'فعال', suspended: 'معلق' };
    const sch = useForm({ name: school?.name || '', city: school?.city || '', level: school?.level || '', status: school?.status || 'active' });
    const [editSchool, setEditSchool] = useState(false);
    const saveSchool = (e) => { e.preventDefault(); sch.put(route('admin.schools.update', school.id), { preserveScroll: true, onSuccess: () => setEditSchool(false) }); };

    return (
        <DashLayout title={`مدیریت مدرسه — ${school?.name}`} roleLabel="ادمین کل" menu={adminMenu} active="schools"
            actions={<Link href={route('admin.schools')} className="btn btn-ghost btn-sm">← فهرست مدارس</Link>}>
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="panel">
                <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap', alignItems: 'center' }}>
                    <h3 style={{ margin: 0 }}>🏫 {school?.name}</h3>
                    {school?.city && <span className="tag tag-info">📍 {school.city}</span>}
                    {school?.level && <span className="tag tag-info">مقطع: {school.level}</span>}
                    <span className="tag tag-info">وضعیت: {STATUS[school?.status] ?? school?.status}</span>
                    <button onClick={() => setEditSchool(!editSchool)} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>✏️ ویرایش مدرسه</button>
                    <span style={{ color: 'var(--muted)' }}>{fa(teachers.length)} معلم · {fa(classes.length)} کلاس · {fa(students.length)} دانش‌آموز</span>
                </div>

                {editSchool && (
                    <form onSubmit={saveSchool} style={{ marginTop: 14, background: 'var(--cream)', borderRadius: 12, padding: 14, display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(170px,1fr))', gap: 12, alignItems: 'end' }}>
                        <div className="field" style={{ margin: 0 }}><label>نام مدرسه</label><input className="input" value={sch.data.name} onChange={(e) => sch.setData('name', e.target.value)} />{sch.errors.name && <Err>{sch.errors.name}</Err>}</div>
                        <div className="field" style={{ margin: 0 }}><label>شهر</label><input className="input" value={sch.data.city} onChange={(e) => sch.setData('city', e.target.value)} />{sch.errors.city && <Err>{sch.errors.city}</Err>}</div>
                        <div className="field" style={{ margin: 0 }}><label>نوع / مقطع مدرسه</label>
                            <select className="input" value={sch.data.level} onChange={(e) => sch.setData('level', e.target.value)}>
                                <option value="">— انتخاب مقطع —</option>
                                {levels.map((l) => <option key={l} value={l}>{l}</option>)}
                            </select>
                            {sch.errors.level && <Err>{sch.errors.level}</Err>}
                        </div>
                        <div className="field" style={{ margin: 0 }}><label>وضعیت</label>
                            <select className="input" value={sch.data.status} onChange={(e) => sch.setData('status', e.target.value)}>
                                {Object.entries(STATUS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                        </div>
                        <div style={{ display: 'flex', gap: 8 }}>
                            <button type="submit" disabled={sch.processing} className="btn btn-sm">💾 ذخیره</button>
                            <button type="button" onClick={() => setEditSchool(false)} className="btn btn-ghost btn-sm">انصراف</button>
                        </div>
                        <div style={{ gridColumn: '1 / -1', fontSize: 12, color: 'var(--muted)' }}>⚠️ تغییر مقطع مدرسه، بانک درس‌ها و پایه‌های مجاز کلاس‌ها را عوض می‌کند.</div>
                    </form>
                )}
            </div>

            {/* مدیر مدرسه */}
            <Section title={`👔 مدیر مدرسه (${fa(admins.length)})`}>
                {admins.length === 0 ? <Empty>مدیری ثبت نشده.</Empty> : admins.map((u) => (
                    <PersonRow key={u.id} p={u} grades={grades} fields={['name', 'phone', 'national_id', 'password']} />
                ))}
            </Section>

            {/* معلم‌ها */}
            <Section title={`👩‍🏫 معلم‌ها و کلاس‌ها (${fa(teachers.length)})`}>
                {teachers.length === 0 ? <Empty>معلمی ثبت نشده.</Empty> : teachers.map((t) => (
                    <PersonRow key={t.id} p={t} grades={grades} fields={['name', 'phone', 'national_id', 'class_name', 'grade', 'password']}
                        sub={`${t.class_name ?? 'بدون کلاس'}${t.grade ? ` · پایه ${t.grade}` : ''} · ${fa(t.students)} دانش‌آموز`} />
                ))}
            </Section>

            {/* دانش‌آموزان */}
            <Section title={`🎓 دانش‌آموزان (${fa(students.length)})`}>
                {students.length === 0 ? <Empty>دانش‌آموزی ثبت نشده.</Empty> : (
                    <div style={{ overflowX: 'auto' }}>
                        <table className="tbl">
                            <thead><tr><th>#</th><th>نام</th><th>موبایل</th><th>کلاس</th><th>امتیاز</th><th style={{ textAlign: 'left' }}>عملیات</th></tr></thead>
                            <tbody>{students.map((s, i) => <StudentRow key={s.id} s={s} i={i} />)}</tbody>
                        </table>
                    </div>
                )}
            </Section>
        </DashLayout>
    );
}

/** ردیف قابل‌ویرایش برای مدیر/معلم. */
function PersonRow({ p, grades, fields, sub }) {
    const [open, setOpen] = useState(false);
    const edit = useForm({ name: p.name || '', phone: p.phone || '', national_id: p.national_id || '', class_name: p.class_name || '', grade: p.grade || '', password: '' });
    const save = (e) => { e.preventDefault(); edit.put(route('manage.users.update', p.id), { preserveScroll: true, onSuccess: () => setOpen(false) }); };
    const del = () => { if (confirm(`«${p.name}» حذف شود؟`)) router.delete(route('manage.users.destroy', p.id), { preserveScroll: true }); };
    const L = { name: 'نام', phone: 'موبایل', national_id: 'کد ملی', class_name: 'نام کلاس', grade: 'پایه', password: 'رمز جدید (اختیاری)' };

    return (
        <div style={{ padding: '10px 0', borderBottom: '1px solid var(--line)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                <div><b>{p.name}</b><div style={{ color: 'var(--muted)', fontSize: 13 }} dir="ltr">{p.phone}</div>{sub && <div style={{ color: 'var(--muted)', fontSize: 12 }}>{sub}</div>}</div>
                <div style={{ display: 'flex', gap: 6 }}>
                    <button onClick={() => setOpen(!open)} className="btn btn-ghost btn-sm">✏️</button>
                    <button onClick={del} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                </div>
            </div>
            {open && (
                <form onSubmit={save} style={{ marginTop: 10, background: 'var(--cream)', borderRadius: 12, padding: 12, display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10, alignItems: 'end' }}>
                    {fields.map((f) => (
                        <div className="field" key={f} style={{ margin: 0 }}><label>{L[f]}</label>
                            {f === 'grade' ? (
                                <select className="input" value={edit.data.grade} onChange={(e) => edit.setData('grade', e.target.value)}>
                                    <option value="">— پایه —</option>{grades.map((g) => <option key={g} value={g}>{g}</option>)}
                                </select>
                            ) : (
                                <input className="input" value={edit.data[f]} onChange={(e) => edit.setData(f, e.target.value)} dir={['phone', 'national_id'].includes(f) ? 'ltr' : 'rtl'} placeholder={f === 'password' ? 'بدون تغییر' : ''} />
                            )}
                            {edit.errors[f] && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{edit.errors[f]}</div>}
                        </div>
                    ))}
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button type="submit" disabled={edit.processing} className="btn btn-sm">💾 ذخیره</button>
                        <button type="button" onClick={() => setOpen(false)} className="btn btn-ghost btn-sm">انصراف</button>
                    </div>
                </form>
            )}
        </div>
    );
}

function StudentRow({ s, i }) {
    const [open, setOpen] = useState(false);
    const edit = useForm({ name: s.name || '', phone: s.phone || '', national_id: s.national_id || '', password: '' });
    const save = (e) => { e.preventDefault(); edit.put(route('manage.users.update', s.id), { preserveScroll: true, onSuccess: () => setOpen(false) }); };
    const del = () => { if (confirm(`«${s.name}» حذف شود؟`)) router.delete(route('manage.users.destroy', s.id), { preserveScroll: true }); };
    return (
        <Fragment>
            <tr>
                <td>{fa(i + 1)}</td><td style={{ fontWeight: 700 }}>{s.name}</td><td dir="ltr">{s.phone || '—'}</td>
                <td>{s.class ?? '—'}</td><td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.xp)}</td>
                <td style={{ textAlign: 'left', whiteSpace: 'nowrap' }}>
                    <button onClick={() => setOpen(!open)} className="btn btn-ghost btn-sm">✏️</button>
                    <button onClick={del} className="btn btn-ghost btn-sm" style={{ color: '#e8505b', marginInlineStart: 4 }}>🗑️</button>
                </td>
            </tr>
            {open && (
                <tr><td colSpan={6} style={{ background: 'var(--cream)' }}>
                    <form onSubmit={save} style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10, alignItems: 'end', padding: 8 }}>
                        {['name', 'phone', 'national_id', 'password'].map((f) => (
                            <div className="field" key={f} style={{ margin: 0 }}><label>{{ name: 'نام', phone: 'موبایل', national_id: 'کد ملی', password: 'رمز جدید' }[f]}</label>
                                <input className="input" value={edit.data[f]} onChange={(e) => edit.setData(f, e.target.value)} dir={['phone', 'national_id'].includes(f) ? 'ltr' : 'rtl'} placeholder={f === 'password' ? 'بدون تغییر' : ''} />
                                {edit.errors[f] && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{edit.errors[f]}</div>}
                            </div>
                        ))}
                        <div style={{ display: 'flex', gap: 8 }}>
                            <button type="submit" disabled={edit.processing} className="btn btn-sm">💾 ذخیره</button>
                            <button type="button" onClick={() => setOpen(false)} className="btn btn-ghost btn-sm">انصراف</button>
                        </div>
                    </form>
                </td></tr>
            )}
        </Fragment>
    );
}

function Section({ title, children }) {
    return <div className="panel"><h3 style={{ marginTop: 0 }}>{title}</h3>{children}</div>;
}
function Empty({ children }) { return <p style={{ color: 'var(--muted)' }}>{children}</p>; }
function Err({ children }) { return <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{children}</div>; }
