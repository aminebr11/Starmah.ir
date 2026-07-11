import { usePage, Link, useForm, router } from '@inertiajs/react';
import { useState, useEffect, Fragment } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Classroom() {
    const { classroom, students = [], themes = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    // تغییر تیم دانش‌آموز — فقط معلم مجاز است
    const setTeam = (s, themeId) => {
        if (themeId) router.post(route('teacher.students.team', s.id), { theme_id: themeId }, { preserveScroll: true });
    };

    const edit = useForm({ name: '', phone: '', national_id: '', password: '' });
    const [editId, setEditId] = useState(null);
    const startEdit = (s) => { setEditId(s.id); edit.setData({ name: s.name || '', phone: s.phone || '', national_id: s.national_id || '', password: '' }); edit.clearErrors(); };
    const saveEdit = (e) => { e.preventDefault(); edit.put(route('manage.users.update', editId), { preserveScroll: true, onSuccess: () => setEditId(null) }); };
    const del = (s) => { if (confirm(`دانش‌آموز «${s.name}» حذف شود؟`)) router.delete(route('manage.users.destroy', s.id), { preserveScroll: true }); };

    return (
        <DashLayout title={`کلاس ${classroom?.name ?? ''}`} roleLabel="معلم" menu={teacherMenu} active="class">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}
            <div className="panel">
                <h3>🏫 {classroom?.name} — کد ورود: <span className="tag tag-info" style={{ marginInlineStart: 8 }}>{classroom?.join_code}</span>
                    <Link href={route('teacher.discipline')} className="btn btn-sm" style={{ marginInlineStart: 'auto' }}>⭐ ثبت انضباط</Link>
                </h3>
                {students.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز دانش‌آموزی به این کلاس نپیوسته.</p>}
                {students.length > 0 && (
                    <div style={{ overflowX: 'auto' }}>
                        <table className="tbl">
                            <thead><tr><th>#</th><th>نام</th><th>موبایل</th><th>تیم / گروه</th><th>امتیاز</th><th>تسلط</th><th style={{ textAlign: 'left' }}>عملیات</th></tr></thead>
                            <tbody>
                                {students.map((s, i) => (
                                    <Fragment key={s.id}>
                                        <tr>
                                            <td style={{ width: 30 }}>{fa(i + 1)}</td>
                                            <td style={{ fontWeight: 700 }}>{s.name}</td>
                                            <td dir="ltr">{s.phone || '—'}</td>
                                            <td>
                                                <select className="input" style={{ width: 'auto', padding: '6px 9px', fontSize: 12.5 }}
                                                    value={s.theme_id ?? ''} onChange={(e) => setTeam(s, e.target.value)}>
                                                    <option value="" disabled>— بدون تیم —</option>
                                                    {themes.map((t) => <option key={t.id} value={t.id}>{t.emoji} {t.name}</option>)}
                                                </select>
                                            </td>
                                            <td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.xp)}</td>
                                            <td>تسلط {fa(s.avg)}٪</td>
                                            <td style={{ textAlign: 'left', whiteSpace: 'nowrap' }}>
                                                <button onClick={() => (editId === s.id ? setEditId(null) : startEdit(s))} className="btn btn-ghost btn-sm" title="ویرایش">✏️</button>
                                                <button onClick={() => del(s)} className="btn btn-ghost btn-sm" title="حذف" style={{ color: '#e8505b', marginInlineStart: 4 }}>🗑️</button>
                                            </td>
                                        </tr>
                                        {editId === s.id && (
                                            <tr><td colSpan={7} style={{ background: 'var(--cream)' }}>
                                                <form onSubmit={saveEdit} style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 10, alignItems: 'end', padding: 8 }}>
                                                    <Field label="نام" err={edit.errors.name}><input className="input" value={edit.data.name} onChange={(e) => edit.setData('name', e.target.value)} /></Field>
                                                    <Field label="موبایل" err={edit.errors.phone}><input className="input" value={edit.data.phone} onChange={(e) => edit.setData('phone', e.target.value)} dir="ltr" /></Field>
                                                    <Field label="کد ملی" err={edit.errors.national_id}><input className="input" value={edit.data.national_id} onChange={(e) => edit.setData('national_id', e.target.value)} dir="ltr" /></Field>
                                                    <Field label="رمز جدید (اختیاری)" err={edit.errors.password}><input className="input" value={edit.data.password} onChange={(e) => edit.setData('password', e.target.value)} placeholder="بدون تغییر" /></Field>
                                                    <div style={{ display: 'flex', gap: 8 }}>
                                                        <button type="submit" disabled={edit.processing} className="btn btn-sm">💾 ذخیره</button>
                                                        <button type="button" onClick={() => setEditId(null)} className="btn btn-ghost btn-sm">انصراف</button>
                                                    </div>
                                                </form>
                                            </td></tr>
                                        )}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </DashLayout>
    );
}

function Field({ label, err, children }) {
    return <div className="field" style={{ margin: 0 }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
