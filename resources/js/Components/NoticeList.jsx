import { useState } from 'react';
import { router } from '@inertiajs/react';

const FILTERS = [
    { v: 'all', t: '📋 همه' },
    { v: 'personal', t: '✉️ شخصی' },
    { v: 'public', t: '📢 اطلاعیه‌ها' },
];

/** فهرست کارتابل اعلان‌ها/پیام‌ها — فیلتر + جست‌وجو + باز شدن متن با کلیک. */
export default function NoticeList({ notices = [] }) {
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
        <div className="panel">
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 12 }}>
                <h3 style={{ margin: 0 }}>📢 اعلان‌ها و پیام‌های من</h3>
                <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 جست‌وجو…"
                    style={{ marginInlineStart: 'auto', width: 'auto', maxWidth: 220, padding: '8px 12px' }} />
                {notices.length > 0 && <button onClick={clearAll} className="btn btn-sm" style={{ background: '#e8505b' }}>🗑️ حذف همه</button>}
            </div>

            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 14 }}>
                {FILTERS.map((f) => (
                    <button key={f.v} onClick={() => setFilter(f.v)}
                        className={`tag ${filter === f.v ? 'tag-warn' : 'tag-info'}`}
                        style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '8px 14px' }}>
                        {f.t} ({fa(count(f.v))})
                    </button>
                ))}
            </div>

            {list.length === 0 && <p style={{ color: 'var(--muted)' }}>موردی برای نمایش نیست.</p>}
            {list.map((n) => {
                const isOpen = open === n.id;
                return (
                    <div key={n.id} style={{ border: '1px solid var(--line)', borderRadius: 14, marginBottom: 10, overflow: 'hidden', borderRight: `4px solid ${n.personal ? '#8b7cf6' : '#f5b53f'}` }}>
                        <button onClick={() => setOpen(isOpen ? null : n.id)}
                            style={{ width: '100%', textAlign: 'right', fontFamily: 'inherit', cursor: 'pointer', background: isOpen ? '#fff8e8' : '#fff', border: 0, padding: '14px 16px', display: 'flex', alignItems: 'center', gap: 10 }}>
                            <span style={{ fontSize: 20 }}>{n.personal ? '✉️' : '📢'}</span>
                            <span style={{ flex: 1 }}>
                                <span style={{ fontWeight: 800, display: 'block' }}>
                                    {n.title}
                                    {n.personal && <span className="tag" style={{ marginInlineStart: 8, background: '#efe9ff', color: '#4c2fb0', fontSize: 11 }}>شخصی</span>}
                                </span>
                                <span style={{ color: 'var(--muted-2)', fontSize: 12 }}>از {n.sender || 'مدرسه'} · {n.date}</span>
                            </span>
                            <span onClick={(ev) => { ev.stopPropagation(); dismiss(n.id); }} title="حذف این اعلان"
                                style={{ flex: 'none', display: 'grid', placeItems: 'center', border: 0, background: '#fdecee', color: '#e8505b', borderRadius: 8, width: 30, height: 30, fontSize: 14, cursor: 'pointer' }}>🗑️</span>
                            <span style={{ color: 'var(--muted)', fontSize: 18 }}>{isOpen ? '▲' : '▼'}</span>
                        </button>
                        {isOpen && (
                            <div style={{ padding: '0 16px 16px', color: 'var(--ink)', fontSize: 14.5, lineHeight: 1.9, whiteSpace: 'pre-wrap' }}>{n.body}</div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
