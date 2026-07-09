import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const AUD = [['all', '👥 همه'], ['teachers', '👩‍🏫 معلم‌ها'], ['students', '🎓 دانش‌آموزان']];

export default function Announcements() {
    const { announcements = [], grades = [], aiReady = false, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [topic, setTopic] = useState('');
    const [aiLoading, setAiLoading] = useState(false);

    const form = useForm({ title: '', body: '', audience: 'all', grade: '' });

    useEffect(() => {
        const f = flash?.flash;
        if (!f) return;
        if (typeof f === 'string') { setBanner({ ok: true, msg: f }); return; }
        if (f.type === 'ai') { form.setData('body', f.message); setBanner({ ok: true, msg: '✨ متن با هوش مصنوعی تولید شد — می‌توانید ویرایش کنید.' }); }
        else if (f.type === 'error') setBanner({ ok: false, msg: f.message });
        else if (f.message) setBanner({ ok: true, msg: f.message });
    }, [flash]);

    const submit = (e) => { e.preventDefault(); form.post(route('school.announcements.store'), { preserveScroll: true, onSuccess: () => { form.reset(); setBanner({ ok: true, msg: 'اطلاعیه ارسال شد ✅' }); } }); };
    const del = (id) => { if (confirm('این اطلاعیه حذف شود؟')) router.delete(route('school.announcements.destroy', id), { preserveScroll: true }); };
    const genAI = () => {
        if (!topic.trim()) { alert('موضوع اطلاعیه را بنویسید'); return; }
        setAiLoading(true);
        router.post(route('school.announcements.ai'), { topic }, { preserveScroll: true, onFinish: () => setAiLoading(false) });
    };

    return (
        <DashLayout title="اطلاعیه‌ها و پیام‌ها" roleLabel="مدیر مدرسه" menu={schoolMenu} active="announcements">
            {banner && <div className="panel" style={{ borderColor: banner.ok ? 'var(--gold)' : '#e8505b', background: banner.ok ? '#fff8e8' : '#fdecec' }}><b>{banner.msg}</b></div>}

            <div style={{ display: 'grid', gridTemplateColumns: '1.1fr 1fr', gap: 20, alignItems: 'start' }} className="themes-grid">
                {/* سازنده‌ی اطلاعیه */}
                <form onSubmit={submit} className="panel">
                    <h3>📢 اطلاعیه‌ی جدید</h3>

                    {/* دستیار هوش مصنوعی */}
                    <div style={{ background: 'linear-gradient(135deg,#f3efff,#fff)', border: '1px solid #e3dcff', borderRadius: 14, padding: 14, marginBottom: 14 }}>
                        <div style={{ fontWeight: 800, color: '#4c2fb0', marginBottom: 8 }}>🤖 نگارش با هوش مصنوعی</div>
                        {aiReady ? (
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <input className="input" style={{ flex: 1, minWidth: 180 }} value={topic} onChange={(e) => setTopic(e.target.value)} placeholder="موضوع: مثلاً تعطیلی روز پنجشنبه" />
                                <button type="button" onClick={genAI} disabled={aiLoading} className="btn btn-sm">{aiLoading ? 'در حال نوشتن…' : '✨ بنویس'}</button>
                            </div>
                        ) : (
                            <div style={{ color: 'var(--muted)', fontSize: 13 }}>برای فعال‌سازی، ادمین کل باید کلید API را در «تنظیمات پلتفرم» وارد کند.</div>
                        )}
                    </div>

                    <Field label="عنوان" err={form.errors.title}>
                        <input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="عنوان اطلاعیه" />
                    </Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        <Field label="مخاطب" err={form.errors.audience}>
                            <select className="input" value={form.data.audience} onChange={(e) => form.setData('audience', e.target.value)}>
                                {AUD.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </Field>
                        <Field label="فقط یک پایه (اختیاری)">
                            <select className="input" value={form.data.grade} onChange={(e) => form.setData('grade', e.target.value)}>
                                <option value="">همه‌ی پایه‌ها</option>
                                {grades.map((g) => <option key={g} value={g}>{g}</option>)}
                            </select>
                        </Field>
                    </div>
                    <Field label="متن اطلاعیه" err={form.errors.body}>
                        <textarea className="input" rows="6" value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} placeholder="متن اطلاعیه را بنویسید یا با دکمه‌ی «بنویس» تولید کنید…" />
                    </Field>
                    <button type="submit" disabled={form.processing} className="btn" style={{ width: '100%' }}>📤 ارسال اطلاعیه</button>
                </form>

                {/* فهرست اطلاعیه‌ها */}
                <div className="panel">
                    <h3>🗂️ اطلاعیه‌های ارسال‌شده ({fa(announcements.length)})</h3>
                    {announcements.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز اطلاعیه‌ای ارسال نشده.</p>}
                    {announcements.map((a) => (
                        <div key={a.id} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 14, marginBottom: 10, position: 'relative' }}>
                            <button onClick={() => del(a.id)} title="حذف" style={{ position: 'absolute', top: 10, insetInlineStart: 10, border: 0, background: 'none', color: '#e8505b', cursor: 'pointer', fontSize: 15 }}>✕</button>
                            <div style={{ fontWeight: 800 }}>{a.title}</div>
                            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', margin: '6px 0' }}>
                                <span className="tag tag-info">{a.audience}</span>
                                {a.grade && <span className="tag tag-warn">پایه {a.grade}</span>}
                                <span style={{ color: 'var(--muted-2)', fontSize: 12, alignSelf: 'center' }}>{fa(a.date)}</span>
                            </div>
                            <div style={{ color: 'var(--muted)', fontSize: 13.5, whiteSpace: 'pre-wrap' }}>{a.body}</div>
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
