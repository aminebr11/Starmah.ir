import { usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const FILTERS = [
    { v: 'all', t: '📋 همه' },
    { v: 'personal', t: '✉️ شخصی' },
    { v: 'public', t: '📢 اطلاعیه‌ها' },
];

/** کارتابل اعلان‌های دانش‌آموز — قالب‌های رنگیِ متنوع (هماهنگ با صفحه‌ی خانه). */
export default function Notices() {
    const { notices = [] } = usePage().props;
    const [open, setOpen] = useState(notices[0]?.id ?? null);
    const [filter, setFilter] = useState('all');
    const [q, setQ] = useState('');

    const list = notices.filter((n) => {
        if (filter === 'personal' && !n.personal) return false;
        if (filter === 'public' && n.personal) return false;
        if (q && !((n.title || '').includes(q) || (n.body || '').includes(q))) return false;
        return true;
    });
    const count = (v) => notices.filter((n) => v === 'all' || (v === 'personal' ? n.personal : !n.personal)).length;
    const dismiss = (id) => router.post(route('notices.dismiss', id), {}, { preserveScroll: true });
    const clearAll = () => { if (confirm('همه‌ی اعلان‌ها حذف شوند؟')) router.post(route('notices.clear'), {}, { preserveScroll: true }); };

    return (
        <ThemedDash title="اعلان‌ها و پیام‌ها" active="notices">
            <div className="k3-card">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <div style={{ fontWeight: 900, fontSize: 18 }}>📢 اعلان‌ها و پیام‌های من</div>
                    <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 جست‌وجو…"
                        style={{ marginInlineStart: 'auto', width: 'auto', maxWidth: 220, padding: '9px 12px', borderRadius: 12, border: '1px solid rgba(255,255,255,.2)', background: 'rgba(255,255,255,.1)', color: '#fff', fontFamily: 'inherit' }} />
                    {notices.length > 0 && <button onClick={clearAll} style={{ cursor: 'pointer', fontFamily: 'inherit', padding: '9px 14px', borderRadius: 12, border: 0, background: 'rgba(232,80,91,.85)', color: '#fff', fontWeight: 800, fontSize: 12.5 }}>🗑️ حذف همه</button>}
                </div>
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 12 }}>
                    {FILTERS.map((f) => (
                        <button key={f.v} onClick={() => setFilter(f.v)}
                            style={{ cursor: 'pointer', fontFamily: 'inherit', padding: '7px 14px', borderRadius: 20, fontWeight: 700, fontSize: 13, border: 0,
                                background: filter === f.v ? 'linear-gradient(135deg,var(--p1),var(--p2))' : 'rgba(255,255,255,.1)', color: '#fff' }}>
                            {f.t} ({fa(count(f.v))})
                        </button>
                    ))}
                </div>
            </div>

            {list.length === 0 && <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .8 }}>موردی برای نمایش نیست 📭</div>}

            <div style={{ display: 'grid', gap: 12, marginTop: 14 }}>
                {list.map((n, i) => {
                    const isOpen = open === n.id;
                    const cls = n.personal ? 'nt-c1' : `nt-c${i % 6}`;
                    return (
                        <div key={n.id} className={`notice-tpl ${cls}`} style={{ cursor: 'pointer', padding: 0 }} onClick={() => setOpen(isOpen ? null : n.id)}>
                            <div style={{ padding: '14px 16px', display: 'flex', alignItems: 'flex-start', gap: 10 }}>
                                <span style={{ fontSize: 22, flex: 'none' }}>{n.personal ? '✉️' : '📢'}</span>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div className="nt-title">
                                        {n.title}
                                        {n.personal && <span style={{ marginInlineStart: 8, background: 'rgba(255,255,255,.28)', borderRadius: 20, padding: '2px 9px', fontSize: 11, fontWeight: 800 }}>شخصی</span>}
                                    </div>
                                    <div className="nt-meta">از {n.sender || 'مدرسه'} · {n.date}</div>
                                    {isOpen && <div className="nt-body" style={{ whiteSpace: 'pre-wrap', marginTop: 8 }}>{n.body}</div>}
                                    {n.link && (
                                        <a href={n.link} onClick={(ev) => ev.stopPropagation()}
                                            style={{ display: 'inline-block', marginTop: 10, background: 'rgba(255,255,255,.9)', color: '#4c1d95', fontWeight: 800, fontSize: 12.5, borderRadius: 20, padding: '7px 16px', textDecoration: 'none' }}>
                                            ← رفتن به این مورد
                                        </a>
                                    )}
                                </div>
                                <div style={{ flex: 'none', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8 }}>
                                    <button onClick={(ev) => { ev.stopPropagation(); dismiss(n.id); }} title="حذف این اعلان"
                                        style={{ cursor: 'pointer', border: 0, background: 'rgba(255,255,255,.18)', color: '#fff', borderRadius: 8, width: 28, height: 28, fontSize: 14 }}>🗑️</button>
                                    <span style={{ opacity: .85, fontSize: 16 }}>{isOpen ? '▲' : '▼'}</span>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </ThemedDash>
    );
}
