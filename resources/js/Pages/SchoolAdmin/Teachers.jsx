import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
import Avatar from '@/Components/Avatar';
import { useSort, SortBar, firstName, lastName } from '@/lib/useSort';
import '@/../css/supervise.css';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Teachers() {
    const { teachers = [], classes = [], supReady = false, school, grades = [], booksByGrade = {}, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(flash.flash); }, [flash]);

    const { data, setData, post, processing, errors, reset } = useForm({ kind: 'teacher', name: '', phone: '', class_name: '', grade: '', password: '', supervise: [] });
    const onlySup = data.kind === 'supervisor';

    // 🛡️ ناظرِ کدام کلاس‌ها (برای معلم‌های موجود)
    const [supId, setSupId] = useState(null);
    const [supSel, setSupSel] = useState([]);
    const openSup = (t) => { if (supId === t.id) return setSupId(null); setSupId(t.id); setSupSel(t.supervises || []); };
    const saveSup = (t) => router.put(route('school.teachers.supervise', t.id), { classes: supSel }, { preserveScroll: true, onSuccess: () => setSupId(null) });
    const className = (id) => classes.find((c) => c.id === id)?.name;
    const submit = (e) => { e.preventDefault(); post(route('school.teachers.store'), { preserveScroll: true, onSuccess: () => reset() }); };

    const edit = useForm({ name: '', phone: '', class_name: '', grade: '', password: '' });
    const [editId, setEditId] = useState(null);
    const startEdit = (t) => { setEditId(t.id); edit.setData({ name: t.name || '', phone: t.phone || '', class_name: t.class_name || '', grade: t.grade || '', password: '' }); edit.clearErrors(); };
    const saveEdit = (e) => { e.preventDefault(); edit.put(route('manage.users.update', editId), { preserveScroll: true, onSuccess: () => setEditId(null) }); };
    const delTeacher = (t) => { if (confirm(`معلم «${t.name}» و کلاسش حذف شود؟ این کار برگشت‌ناپذیر است.`)) router.delete(route('manage.users.destroy', t.id), { preserveScroll: true }); };

    const gradeBooks = data.grade ? (booksByGrade[data.grade] || []) : [];
    const [tq, setTq] = useState('');
    const filteredTeachers = teachers.filter((t) => !tq || (t.name || '').includes(tq) || (t.class_name || '').includes(tq) || (t.phone || '').includes(tq));
    const ts = useSort(filteredTeachers, { name: (r) => firstName(r.name), family: (r) => lastName(r.name), cls: 'class_name', grade: 'grade', students: 'students' }, { id: 'school-teachers', firstDir: { students: 'desc' } });
    const shownTeachers = ts.sorted;

    return (
        <DashLayout title="معلم‌ها و کلاس‌ها" roleLabel="مدیر مدرسه" menu={schoolMenu} active="teachers">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner.message}</b></div>}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.4fr', gap: 20, alignItems: 'start' }} className="themes-grid">
                <form onSubmit={submit} className="panel">
                    <h3>➕ معلم و کلاس جدید</h3>
                    {school?.level && <div style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 10 }}>مقطع مدرسه: <b style={{ color: 'var(--navy-700)' }}>{school.level}</b></div>}
                    {supReady && (
                        <div className="sup-kind">
                            <button type="button" className={!onlySup ? 'on' : ''} onClick={() => setData('kind', 'teacher')}>👩‍🏫 معلم (با کلاس)</button>
                            <button type="button" className={onlySup ? 'on' : ''} onClick={() => setData('kind', 'supervisor')}>🛡️ فقط ناظر (بدونِ کلاس)</button>
                        </div>
                    )}
                    <Field label={onlySup ? 'نام ناظر' : 'نام معلم'} err={errors.name}><input className="input" value={data.name} onChange={(e) => setData('name', e.target.value)} /></Field>
                    <Field label="شماره موبایل" err={errors.phone}><input className="input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="09xxxxxxxxx" /></Field>
                    {!onlySup && <>
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

                    </>}

                    {supReady && classes.length > 0 && (
                        <Field label={onlySup ? '🛡️ ناظرِ این کلاس‌ها' : '🛡️ ناظرِ کلاس‌های دیگر هم باشد؟ (اختیاری)'} err={errors.supervise}>
                            <ClassPicker classes={classes} value={data.supervise} onChange={(v) => setData('supervise', v)} />
                        </Field>
                    )}

                    <Field label="رمز (اختیاری — خودکار)" err={errors.password}><input className="input" value={data.password} onChange={(e) => setData('password', e.target.value)} /></Field>
                    <button type="submit" disabled={processing} className="btn" style={{ width: '100%' }}>{onlySup ? 'ساختِ ناظر' : 'ساخت معلم و کلاس'}</button>
                    {supReady && <small className="sup-note">ناظر فقط کلاس‌هایی را که انتخاب می‌کنید می‌بیند: واردِ کلاسِ آنلاینِ آن‌ها می‌شود و کنترلش می‌کند، حضور و غیاب می‌زند، هشدارها، دانش‌آموزان و فیلمِ کلاس‌ها را می‌بیند. معلم یا ناظرِ دیگری نمی‌سازد.</small>}
                </form>

                <div className="panel">
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 10 }}>
                        <h3 style={{ margin: 0 }}>👩‍🏫 معلم‌ها ({fa(teachers.length)})</h3>
                        <input className="input" value={tq} onChange={(e) => setTq(e.target.value)} placeholder="🔍 جست‌وجوی نام/کلاس…" style={{ marginInlineStart: 'auto', width: 'auto', maxWidth: 200, padding: '8px 12px' }} />
                    </div>
                    {teachers.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز معلمی ساخته نشده.</p>}
                    {teachers.length > 1 && <SortBar s={ts} options={[['name', 'نام'], ['family', 'نام خانوادگی'], ['cls', 'کلاس'], ['grade', 'پایه'], ['students', 'تعداد دانش‌آموز']]} />}
                    {teachers.length > 0 && shownTeachers.length === 0 && <p style={{ color: 'var(--muted)' }}>معلمی با این نام پیدا نشد.</p>}
                    {shownTeachers.map((t) => (
                        <div key={t.id} style={{ padding: '12px 0', borderBottom: '1px solid var(--line)' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 11, minWidth: 0 }}>
                                    <Avatar src={t.avatar} name={t.name} size={42} />
                                    <div style={{ minWidth: 0 }}>
                                        <div style={{ fontWeight: 800 }}>{t.name}</div>
                                        <div style={{ color: 'var(--muted)', fontSize: 13 }}>{t.phone} · {t.class_name ?? 'بدون کلاس'}{t.grade ? ` · پایه ${t.grade}` : ''}</div>
                                        {t.supervises?.length > 0 && <div className="sup-tag" title={t.supervises.map(className).filter(Boolean).join('، ')}>🛡️ ناظرِ {fa(t.supervises.length)} کلاس: {t.supervises.map(className).filter(Boolean).slice(0, 3).join('، ')}{t.supervises.length > 3 ? '، …' : ''}</div>}
                                    </div>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <div style={{ textAlign: 'left' }}>
                                        <span className="tag tag-info">کد: {t.join_code}</span>
                                        <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 4 }}>{fa(t.students)} دانش‌آموز</div>
                                    </div>
                                    {supReady && <button onClick={() => openSup(t)} className={`btn btn-ghost btn-sm ${supId === t.id ? 'on' : ''}`} title="ناظرِ کدام کلاس‌ها">🛡️</button>}
                                    <button onClick={() => (editId === t.id ? setEditId(null) : startEdit(t))} className="btn btn-ghost btn-sm" title="ویرایش">✏️</button>
                                    <button onClick={() => delTeacher(t)} className="btn btn-ghost btn-sm" title="حذف" style={{ color: '#e8505b' }}>🗑️</button>
                                </div>
                            </div>

                            {supId === t.id && (
                                <div className="sup-box">
                                    <b>🛡️ «{t.name}» ناظرِ کدام کلاس‌ها باشد؟</b>
                                    <ClassPicker classes={classes.filter((c) => c.teacher_id !== t.id)} value={supSel} onChange={setSupSel} />
                                    <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                                        <button type="button" onClick={() => saveSup(t)} className="btn btn-sm">💾 ذخیره</button>
                                        {(t.supervises?.length > 0) && <button type="button" onClick={() => { setSupSel([]); router.put(route('school.teachers.supervise', t.id), { classes: [] }, { preserveScroll: true, onSuccess: () => setSupId(null) }); }} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>برداشتنِ نظارت</button>}
                                        <button type="button" onClick={() => setSupId(null)} className="btn btn-ghost btn-sm">انصراف</button>
                                    </div>
                                </div>
                            )}

                            {editId === t.id && (
                                <form onSubmit={saveEdit} style={{ marginTop: 12, background: 'var(--cream)', borderRadius: 12, padding: 14 }}>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                                        <Field label="نام معلم" err={edit.errors.name}><input className="input" value={edit.data.name} onChange={(e) => edit.setData('name', e.target.value)} /></Field>
                                        <Field label="موبایل" err={edit.errors.phone}><input className="input" value={edit.data.phone} onChange={(e) => edit.setData('phone', e.target.value)} dir="ltr" /></Field>
                                        <Field label="نام کلاس" err={edit.errors.class_name}><input className="input" value={edit.data.class_name} onChange={(e) => edit.setData('class_name', e.target.value)} /></Field>
                                        <Field label="پایه" err={edit.errors.grade}>
                                            <select className="input" value={edit.data.grade} onChange={(e) => edit.setData('grade', e.target.value)}>
                                                <option value="">— انتخاب پایه —</option>
                                                {grades.map((g) => <option key={g} value={g}>{g}</option>)}
                                            </select>
                                        </Field>
                                        <Field label="رمز جدید (اختیاری)" err={edit.errors.password}><input className="input" value={edit.data.password} onChange={(e) => edit.setData('password', e.target.value)} placeholder="بدون تغییر" /></Field>
                                    </div>
                                    <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
                                        <button type="submit" disabled={edit.processing} className="btn btn-sm">💾 ذخیره</button>
                                        <button type="button" onClick={() => setEditId(null)} className="btn btn-ghost btn-sm">انصراف</button>
                                    </div>
                                </form>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}

/** انتخابِ چند کلاس با «همه / هیچ». */
function ClassPicker({ classes, value = [], onChange }) {
    const all = classes.length > 0 && classes.every((c) => value.includes(c.id));
    const toggle = (id) => onChange(value.includes(id) ? value.filter((v) => v !== id) : [...value, id]);
    if (!classes.length) return <div style={{ color: 'var(--muted)', fontSize: 13 }}>کلاسِ دیگری در مدرسه نیست.</div>;
    return (
        <div className="sup-pick">
            <label className="sup-all"><input type="checkbox" checked={all} onChange={() => onChange(all ? [] : classes.map((c) => c.id))} /> همه‌ی کلاس‌ها ({fa(classes.length)})</label>
            <div className="sup-list">
                {classes.map((c) => (
                    <label key={c.id} className={value.includes(c.id) ? 'on' : ''}>
                        <input type="checkbox" checked={value.includes(c.id)} onChange={() => toggle(c.id)} />
                        <span><b>{c.name}</b>{(c.grade || c.teacher) && <small>{c.grade ? `پایه‌ی ${c.grade}` : ''}{c.grade && c.teacher ? ' · ' : ''}{c.teacher || ''}</small>}</span>
                    </label>
                ))}
            </div>
        </div>
    );
}

function Field({ label, err, children }) {
    return <div className="field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
