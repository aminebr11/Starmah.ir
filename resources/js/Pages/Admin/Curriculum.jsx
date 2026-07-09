import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Curriculum() {
    const { levels = {}, books = [], flash } = usePage().props;
    const levelNames = Object.keys(levels);
    const [level, setLevel] = useState(levelNames[0] || '');
    const [grade, setGrade] = useState((levels[levelNames[0]] || [])[0] || '');
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const grades = levels[level] || [];
    const list = books.filter((b) => b.level === level && b.grade === grade);

    const form = useForm({ level, grade, name: '', icon: '📘' });
    const add = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, level, grade }));
        form.post(route('admin.curriculum.store'), { preserveScroll: true, onSuccess: () => form.setData('name', '') });
    };
    const remove = (id) => { if (confirm('این درس حذف شود؟')) router.delete(route('admin.curriculum.destroy', id), { preserveScroll: true }); };

    const pickLevel = (l) => { setLevel(l); setGrade((levels[l] || [])[0] || ''); };

    return (
        <DashLayout title="دروس و کتاب‌ها" roleLabel="ادمین کل" menu={adminMenu} active="curriculum">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="panel">
                <h3>🎚️ انتخاب مقطع و پایه</h3>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 12 }}>
                    {levelNames.map((l) => (
                        <button key={l} onClick={() => pickLevel(l)} className={`tag ${level === l ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 16px', fontSize: 14 }}>{l}</button>
                    ))}
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {grades.map((g) => (
                        <button key={g} onClick={() => setGrade(g)} className={`tag ${grade === g ? 'tag-ok' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '8px 14px' }}>پایه‌ی {g}</button>
                    ))}
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.6fr', gap: 20, alignItems: 'start' }} className="themes-grid">
                <form onSubmit={add} className="panel">
                    <h3>➕ افزودن درس به پایه‌ی {grade}</h3>
                    <div className="field"><label>آیکون</label>
                        <input className="input" value={form.data.icon} onChange={(e) => form.setData('icon', e.target.value)} placeholder="📘" />
                    </div>
                    <div className="field"><label>نام درس / کتاب</label>
                        <input className="input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="مثلاً ریاضی" />
                        {form.errors.name && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{form.errors.name}</div>}
                    </div>
                    <button type="submit" disabled={form.processing} className="btn" style={{ width: '100%' }}>➕ افزودن</button>
                </form>

                <div className="panel">
                    <h3>📚 دروس پایه‌ی {grade} ({fa(list.length)})</h3>
                    {list.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز درسی برای این پایه ثبت نشده.</p>}
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(160px,1fr))', gap: 12 }}>
                        {list.map((b) => (
                            <div key={b.id} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 14, display: 'flex', alignItems: 'center', gap: 10, position: 'relative' }}>
                                <span style={{ fontSize: 26 }}>{b.icon || '📘'}</span>
                                <span style={{ fontWeight: 700 }}>{b.name}</span>
                                <button onClick={() => remove(b.id)} title="حذف" style={{ position: 'absolute', top: 6, insetInlineEnd: 6, background: 'none', border: 0, color: '#e8505b', cursor: 'pointer', fontSize: 15 }}>✕</button>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </DashLayout>
    );
}
