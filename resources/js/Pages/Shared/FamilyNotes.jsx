import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu, schoolMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * «پیامِ محرمانه به والدین» — معلم/مدیر برای والدِ یک دانش‌آموز پیام می‌فرستد؛
 * والد آن را در «بخشِ والدینِ» پرتالِ دانش‌آموز (پشتِ رمز) می‌بیند و پاسخ می‌دهد.
 */
export default function FamilyNotes() {
    const { auth, students = [], selectedId, thread = [], flash } = usePage().props;
    const roles = auth?.roles ?? [];
    const isTeacher = roles.includes('teacher');
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const pick = (id) => router.get(route('family.notes'), { student: id }, { preserveState: true, preserveScroll: true });
    const selected = students.find((s) => s.id === selectedId);

    const form = useForm({ student_id: selectedId || '', title: '', body: '' });
    useEffect(() => { form.setData('student_id', selectedId || ''); }, [selectedId]);
    const send = (e) => {
        e.preventDefault();
        form.post(route('family.notes.store'), { preserveScroll: true, onSuccess: () => { form.setData('title', ''); form.setData('body', ''); } });
    };
    const del = (id) => { if (confirm('این پیام حذف شود؟')) router.delete(route('family.notes.destroy', id), { preserveScroll: true }); };

    const [q, setQ] = useState('');
    const shown = students.filter((s) => !q || s.name.includes(q));

    return (
        <DashLayout title="پیامِ محرمانه به والدین" roleLabel={isTeacher ? 'معلم' : 'مدیر مدرسه'}
            menu={isTeacher ? teacherMenu : schoolMenu} active="familynotes">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="panel" style={{ background: 'linear-gradient(135deg,#16264f,#0a1836)', border: 0, color: '#fff' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 32 }}>🔐</span>
                    <div>
                        <b style={{ fontSize: 16 }}>پیامِ محرمانه به والدین</b>
                        <div style={{ color: '#c4d2f0', fontSize: 12.5, marginTop: 2 }}>
                            پیامِ شما فقط در «بخشِ والدینِ» پرتالِ دانش‌آموز — پشتِ رمزِ والدین — نمایش داده می‌شود؛ خودِ دانش‌آموز آن را نمی‌بیند.
                        </div>
                    </div>
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(220px,300px) 1fr', gap: 16, alignItems: 'start', marginTop: 16 }} className="themes-grid">
                {/* فهرستِ دانش‌آموزان */}
                <div className="panel">
                    <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 جست‌وجوی دانش‌آموز…" style={{ marginBottom: 10 }} />
                    <div style={{ display: 'grid', gap: 6, maxHeight: 460, overflowY: 'auto' }}>
                        {shown.map((s) => (
                            <button key={s.id} onClick={() => pick(s.id)}
                                style={{ display: 'flex', alignItems: 'center', gap: 8, textAlign: 'start', cursor: 'pointer', fontFamily: 'inherit', fontSize: 13.5, fontWeight: 700,
                                    border: selectedId === s.id ? '2px solid var(--gold)' : '1px solid var(--line)',
                                    background: selectedId === s.id ? '#fff8e8' : '#fff', borderRadius: 12, padding: '9px 12px' }}>
                                <span style={{ flex: 1 }}>{s.name}</span>
                                {s.unread > 0 && <span style={{ background: '#e8505b', color: '#fff', borderRadius: 20, minWidth: 20, height: 20, fontSize: 11, fontWeight: 800, display: 'grid', placeItems: 'center', padding: '0 5px' }}>{fa(s.unread)}</span>}
                            </button>
                        ))}
                        {shown.length === 0 && <p style={{ color: 'var(--muted)', fontSize: 13 }}>دانش‌آموزی یافت نشد.</p>}
                    </div>
                </div>

                {/* گفت‌وگو */}
                <div className="panel">
                    {!selected ? (
                        <div style={{ textAlign: 'center', padding: 40, color: 'var(--muted)' }}>
                            <div style={{ fontSize: 44 }}>💌</div>
                            <p>یک دانش‌آموز را انتخاب کنید تا با والدینش گفت‌وگوی محرمانه داشته باشید.</p>
                        </div>
                    ) : (
                        <>
                            <h3 style={{ marginTop: 0 }}>گفت‌وگو با والدینِ {selected.name}</h3>

                            {/* فرمِ ارسال */}
                            <form onSubmit={send} style={{ background: '#f6f8fc', border: '1px solid var(--line)', borderRadius: 12, padding: 12, marginBottom: 14 }}>
                                <div className="field" style={{ marginBottom: 8 }}>
                                    <label>موضوع (اختیاری)</label>
                                    <input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: گزارشِ پیشرفتِ ریاضی" />
                                </div>
                                <div className="field" style={{ marginBottom: 8 }}>
                                    <label>متنِ پیامِ محرمانه *</label>
                                    <textarea className="input" rows={3} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} style={{ resize: 'vertical' }} />
                                    {form.errors.body && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 3 }}>{form.errors.body}</div>}
                                </div>
                                <button type="submit" disabled={form.processing || !form.data.body.trim()} className="btn">📤 ارسال به والدین</button>
                            </form>

                            {/* سوابق */}
                            <div style={{ display: 'grid', gap: 8, maxHeight: 400, overflowY: 'auto' }}>
                                {thread.length === 0 && <p style={{ color: 'var(--muted)', fontSize: 13 }}>هنوز پیامی ردوبدل نشده.</p>}
                                {thread.map((n) => (
                                    <div key={n.id} style={{ borderRadius: 12, padding: '10px 13px', fontSize: 13.5, lineHeight: 1.9,
                                        background: n.from_parent ? '#eef7ff' : '#fff8e8',
                                        border: `1px solid ${n.from_parent ? '#cfe3fa' : '#f3ddaa'}` }}>
                                        <div style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 12, color: 'var(--muted)', marginBottom: 3 }}>
                                            <b style={{ color: 'var(--navy-800)' }}>{n.from_parent ? '👨‍👩‍👧 والدِ دانش‌آموز' : `🏫 ${n.sender}`}</b>
                                            {n.title && <span>· {n.title}</span>}
                                            {!n.from_parent && (
                                                <span className="tag" style={{ fontSize: 10.5, background: n.seen ? '#e2f6ec' : '#eef2f8', color: n.seen ? '#177a4c' : 'var(--muted)' }}>
                                                    {n.seen ? '✓ خوانده شد' : 'هنوز خوانده نشده'}
                                                </span>
                                            )}
                                            <span style={{ marginInlineStart: 'auto' }}>{n.date}</span>
                                            {n.mine && <button onClick={() => del(n.id)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b', padding: '2px 7px' }}>🗑️</button>}
                                        </div>
                                        {n.body}
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </DashLayout>
    );
}
