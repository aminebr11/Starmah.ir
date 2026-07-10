import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const AUD = [['all', '👥 همه'], ['teachers', '👩‍🏫 معلم‌ها'], ['students', '🎓 دانش‌آموزان'], ['personal', '✉️ پیام شخصی']];

export default function Announcements() {
    const { announcements = [], grades = [], people = [], aiReady = false, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [topic, setTopic] = useState('');
    const [aiLoading, setAiLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [listFilter, setListFilter] = useState('all');
    const [listSearch, setListSearch] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [editingId, setEditingId] = useState(null);

    const form = useForm({ title: '', body: '', audience: 'all', grade: '', recipient_ids: [] });

    const filteredList = announcements.filter((a) => {
        const isPersonal = a.audience === 'پیام شخصی';
        if (listFilter === 'personal' && !isPersonal) return false;
        if (listFilter === 'public' && isPersonal) return false;
        if (listSearch && !((a.title || '').includes(listSearch) || (a.body || '').includes(listSearch))) return false;
        if (dateFrom && a.created_ts && a.created_ts < Math.floor(new Date(dateFrom).getTime() / 1000)) return false;
        if (dateTo && a.created_ts && a.created_ts > Math.floor(new Date(dateTo).getTime() / 1000) + 86400) return false;
        return true;
    });

    const startEdit = (a) => {
        setEditingId(a.id);
        form.setData({ title: a.title, body: a.body, audience: a.audience_key || 'all', grade: a.grade || '', recipient_ids: a.recipient_ids || [] });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    const cancelEdit = () => { setEditingId(null); form.reset(); form.clearErrors(); };

    const toggleRecipient = (id) => {
        const cur = form.data.recipient_ids;
        form.setData('recipient_ids', cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    };
    const filteredPeople = people.filter((p) => p.name.includes(search));

    useEffect(() => {
        const f = flash?.flash;
        if (!f) return;
        if (typeof f === 'string') { setBanner({ ok: true, msg: f }); return; }
        if (f.type === 'ai') { form.setData('body', f.message); setBanner({ ok: true, msg: '✨ متن با هوش مصنوعی تولید شد — می‌توانید ویرایش کنید.' }); }
        else if (f.type === 'error') setBanner({ ok: false, msg: f.message });
        else if (f.message) setBanner({ ok: true, msg: f.message });
    }, [flash]);

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); setEditingId(null); } };
        if (editingId) form.put(route('school.announcements.update', editingId), opts);
        else form.post(route('school.announcements.store'), { ...opts, onSuccess: () => { form.reset(); setBanner({ ok: true, msg: 'اطلاعیه ارسال شد ✅' }); } });
    };
    const del = (id) => { if (confirm('این اطلاعیه حذف شود؟')) router.delete(route('school.announcements.destroy', id), { preserveScroll: true }); };
    const genAI = () => {
        if (!topic.trim()) { alert('موضوع اطلاعیه را بنویسید'); return; }
        setAiLoading(true);
        router.post(route('school.announcements.ai'), { topic }, {
            preserveState: true,   // مهم: تا عنوان/مخاطب پاک نشود
            preserveScroll: true,
            onSuccess: (page) => {
                const f = page.props?.flash?.flash;
                if (f?.type === 'ai') { form.setData('body', f.message); setBanner({ ok: true, msg: '✨ متن تولید شد — عنوان را هم پر کنید و ارسال بزنید.' }); }
                else if (f?.type === 'error') setBanner({ ok: false, msg: f.message });
            },
            onFinish: () => setAiLoading(false),
        });
    };

    return (
        <DashLayout title="اطلاعیه‌ها و پیام‌ها" roleLabel="مدیر مدرسه" menu={schoolMenu} active="announcements">
            {banner && <div className="panel" style={{ borderColor: banner.ok ? 'var(--gold)' : '#e8505b', background: banner.ok ? '#fff8e8' : '#fdecec' }}><b>{banner.msg}</b></div>}

            <div style={{ display: 'grid', gridTemplateColumns: '1.1fr 1fr', gap: 20, alignItems: 'start' }} className="themes-grid">
                {/* سازنده‌ی اطلاعیه */}
                <form onSubmit={submit} className="panel">
                    <h3>{editingId ? '✏️ ویرایش اطلاعیه' : '📢 اطلاعیه‌ی جدید'}</h3>

                    {/* دستیار هوش مصنوعی */}
                    <div style={{ background: 'linear-gradient(135deg,#f3efff,#fff)', border: '1px solid #e3dcff', borderRadius: 14, padding: 14, marginBottom: 14 }}>
                        <div style={{ fontWeight: 800, color: '#4c2fb0', marginBottom: 8 }}>🤖 نگارش با هوش مصنوعی</div>
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            <input className="input" style={{ flex: 1, minWidth: 180 }} value={topic} onChange={(e) => setTopic(e.target.value)} placeholder="موضوع: مثلاً تعطیلی روز پنجشنبه" />
                            <button type="button" onClick={genAI} disabled={aiLoading} className="btn btn-sm">{aiLoading ? 'در حال نوشتن…' : '✨ بنویس'}</button>
                        </div>
                        {!aiReady && <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 6 }}>💡 برای کیفیت بهتر، ادمین کل کلید API را در «تنظیمات پلتفرم» وارد کند (فعلاً پیش‌نویس ساده تولید می‌شود).</div>}
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
                        {form.data.audience !== 'personal' && (
                            <Field label="فقط یک پایه (اختیاری)">
                                <select className="input" value={form.data.grade} onChange={(e) => form.setData('grade', e.target.value)}>
                                    <option value="">همه‌ی پایه‌ها</option>
                                    {grades.map((g) => <option key={g} value={g}>{g}</option>)}
                                </select>
                            </Field>
                        )}
                    </div>

                    {/* انتخاب گیرندگان برای پیام شخصی */}
                    {form.data.audience === 'personal' && (
                        <Field label={`گیرندگان (${fa(form.data.recipient_ids.length)} نفر انتخاب شده)`} err={form.errors.recipient_ids}>
                            <input className="input" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="🔍 جست‌وجوی نام…" style={{ marginBottom: 8 }} />
                            <div style={{ maxHeight: 190, overflowY: 'auto', border: '1px solid var(--line)', borderRadius: 12, padding: 8, display: 'flex', flexDirection: 'column', gap: 4 }}>
                                {filteredPeople.length === 0 && <div style={{ color: 'var(--muted)', fontSize: 13, padding: 6 }}>کسی پیدا نشد.</div>}
                                {filteredPeople.map((p) => {
                                    const sel = form.data.recipient_ids.includes(p.id);
                                    return (
                                        <button type="button" key={p.id} onClick={() => toggleRecipient(p.id)}
                                            style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 10px', borderRadius: 10, cursor: 'pointer', textAlign: 'right', fontFamily: 'inherit',
                                                border: sel ? '1px solid var(--gold)' : '1px solid transparent', background: sel ? '#fff8e8' : 'transparent' }}>
                                            <span>{sel ? '✅' : (p.role === 'teacher' ? '👩‍🏫' : '🎓')}</span>
                                            <span style={{ fontWeight: 700, flex: 1 }}>{p.name}</span>
                                            <span className="tag tag-info" style={{ fontSize: 11 }}>{p.role === 'teacher' ? 'معلم' : 'دانش‌آموز'}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        </Field>
                    )}
                    <Field label="متن اطلاعیه" err={form.errors.body}>
                        <textarea className="input" rows="6" value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} placeholder="متن اطلاعیه را بنویسید یا با دکمه‌ی «بنویس» تولید کنید…" />
                    </Field>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button type="submit" disabled={form.processing} className="btn" style={{ flex: 1 }}>{editingId ? '💾 ذخیره‌ی ویرایش' : '📤 ارسال اطلاعیه'}</button>
                        {editingId && <button type="button" onClick={cancelEdit} className="btn btn-ghost">انصراف</button>}
                    </div>
                </form>

                {/* فهرست اطلاعیه‌ها */}
                <div className="panel">
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 10 }}>
                        <h3 style={{ margin: 0 }}>🗂️ ارسال‌شده ({fa(announcements.length)})</h3>
                        <input className="input" value={listSearch} onChange={(e) => setListSearch(e.target.value)} placeholder="🔍 جست‌وجو…" style={{ marginInlineStart: 'auto', width: 'auto', maxWidth: 180, padding: '8px 12px' }} />
                    </div>
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 10 }}>
                        {[['all', 'همه'], ['public', '📢 عمومی'], ['personal', '✉️ شخصی']].map(([v, t]) => (
                            <button key={v} onClick={() => setListFilter(v)} className={`tag ${listFilter === v ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '7px 13px' }}>{t}</button>
                        ))}
                    </div>
                    {/* فیلتر بازه‌ی تاریخ */}
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center', marginBottom: 12, fontSize: 13, color: 'var(--muted)' }}>
                        <span>از:</span><input type="date" className="input" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} style={{ width: 'auto', padding: '7px 9px' }} />
                        <span>تا:</span><input type="date" className="input" value={dateTo} onChange={(e) => setDateTo(e.target.value)} style={{ width: 'auto', padding: '7px 9px' }} />
                        {(dateFrom || dateTo) && <button onClick={() => { setDateFrom(''); setDateTo(''); }} className="btn btn-ghost btn-sm">پاک کردن</button>}
                    </div>
                    {filteredList.length === 0 && <p style={{ color: 'var(--muted)' }}>موردی برای نمایش نیست.</p>}
                    {filteredList.map((a) => (
                        <div key={a.id} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 14, marginBottom: 10 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8 }}>
                                <div style={{ fontWeight: 800 }}>{a.title}</div>
                                <div style={{ display: 'flex', gap: 6, flexShrink: 0 }}>
                                    <button onClick={() => startEdit(a)} title="ویرایش" style={{ border: 0, background: 'none', cursor: 'pointer', fontSize: 15 }}>✏️</button>
                                    <button onClick={() => del(a.id)} title="حذف" style={{ border: 0, background: 'none', color: '#e8505b', cursor: 'pointer', fontSize: 15 }}>🗑️</button>
                                </div>
                            </div>
                            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', margin: '6px 0' }}>
                                <span className="tag tag-info">{a.audience}</span>
                                {a.grade && <span className="tag tag-warn">پایه {a.grade}</span>}
                                <span style={{ color: 'var(--muted-2)', fontSize: 12, alignSelf: 'center' }}>{a.date}</span>
                            </div>
                            {a.recipients?.length > 0 && (
                                <div style={{ color: 'var(--muted)', fontSize: 12, marginBottom: 4 }}>👥 {a.recipients.join('، ')}</div>
                            )}
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
