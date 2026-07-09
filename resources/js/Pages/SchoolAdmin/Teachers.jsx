import { usePage, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Teachers() {
    const { teachers = [], school, grades = [], booksByGrade = {}, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(flash.flash); }, [flash]);

    const { data, setData, post, processing, errors, reset } = useForm({ name: '', phone: '', class_name: '', grade: '', password: '' });
    const submit = (e) => { e.preventDefault(); post(route('school.teachers.store'), { preserveScroll: true, onSuccess: () => reset() }); };

    const gradeBooks = data.grade ? (booksByGrade[data.grade] || []) : [];

    return (
        <DashLayout title="معلم‌ها و کلاس‌ها" roleLabel="مدیر مدرسه" menu={schoolMenu} active="teachers">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner.message}</b></div>}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.4fr', gap: 20, alignItems: 'start' }} className="themes-grid">
                <form onSubmit={submit} className="panel">
                    <h3>➕ معلم و کلاس جدید</h3>
                    {school?.level && <div style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 10 }}>مقطع مدرسه: <b style={{ color: 'var(--navy-700)' }}>{school.level}</b></div>}
                    <Field label="نام معلم" err={errors.name}><input className="input" value={data.name} onChange={(e) => setData('name', e.target.value)} /></Field>
                    <Field label="شماره موبایل" err={errors.phone}><input className="input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="09xxxxxxxxx" /></Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        <Field label="نام کلاس" err={errors.class_name}><input className="input" value={data.class_name} onChange={(e) => setData('class_name', e.target.value)} placeholder="چهارم الف" /></Field>
                        <Field label="پایه" err={errors.grade}>
                            <select className="input" value={data.grade} onChange={(e) => setData('grade', e.target.value)}>
                                <option value="">— انتخاب پایه —</option>
                                {grades.map((g) => <option key={g} value={g}>{g}</option>)}
                            </select>
                        </Field>
                    </div>

                    {/* دروسِ پایه‌ی انتخاب‌شده باز می‌شوند */}
                    {data.grade && (
                        <div style={{ background: 'var(--cream)', borderRadius: 12, padding: 12, marginBottom: 12 }}>
                            <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 8 }}>📚 دروس پایه‌ی {data.grade} ({fa(gradeBooks.length)})</div>
                            {gradeBooks.length === 0 ? (
                                <div style={{ color: 'var(--muted)', fontSize: 12 }}>برای این پایه درسی تعریف نشده. ادمین کل می‌تواند اضافه کند.</div>
                            ) : (
                                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                    {gradeBooks.map((b, i) => <span key={i} className="tag tag-info">{b.icon} {b.name}</span>)}
                                </div>
                            )}
                        </div>
                    )}

                    <Field label="رمز (اختیاری — خودکار)" err={errors.password}><input className="input" value={data.password} onChange={(e) => setData('password', e.target.value)} /></Field>
                    <button type="submit" disabled={processing} className="btn" style={{ width: '100%' }}>ساخت معلم و کلاس</button>
                </form>

                <div className="panel">
                    <h3>👩‍🏫 معلم‌ها ({fa(teachers.length)})</h3>
                    {teachers.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز معلمی ساخته نشده.</p>}
                    {teachers.map((t) => (
                        <div key={t.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8, padding: '12px 0', borderBottom: '1px solid var(--line)' }}>
                            <div><div style={{ fontWeight: 800 }}>{t.name}</div><div style={{ color: 'var(--muted)', fontSize: 13 }}>{t.phone} · {t.class_name ?? 'بدون کلاس'}{t.grade ? ` · پایه ${t.grade}` : ''}</div></div>
                            <div style={{ textAlign: 'left' }}>
                                <span className="tag tag-info">کد: {t.join_code}</span>
                                <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 4 }}>{fa(t.students)} دانش‌آموز</div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}

function Field({ label, err, children }) {
    return <div className="field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
