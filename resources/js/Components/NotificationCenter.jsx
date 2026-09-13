import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const FILTERS = [
    { v: 'all', t: '📋 همه' },
    { v: 'unread', t: '● خوانده‌نشده' },
    { v: 'personal', t: '✉️ شخصی' },
    { v: 'public', t: '📢 اطلاعیه‌ها' },
];

/**
 * کارتابلِ اعلان‌ها — همان فیدِ زنگوله، ولی کامل و با کنترل.
 *
 * هر ردیف: علامتِ «مطالعه شد»، رفتن به صفحه‌ی مربوطه، و (برای اطلاعیه‌ها)
 * حذف. `tone="dark"` برای صفحه‌ی دانش‌آموز، `"light"` برای معلم/مدیر.
 */
export default function NotificationCenter({ notices = [], tone = 'light' }) {
    const dark = tone === 'dark';
    const [filter, setFilter] = useState('all');
    const [q, setQ] = useState('');
    const [open, setOpen] = useState(null);

    const match = (n, v) => {
        if (v === 'unread') return !n.read;
        if (v === 'personal') return n.group === 'personal';
        if (v === 'public') return n.group !== 'personal';
        return true;
    };
    const count = (v) => notices.filter((n) => match(n, v)).length;

    const list = useMemo(() => notices.filter((n) => {
        if (!match(n, filter)) return false;
        if (q && !((n.title || '') + ' ' + (n.body || '')).includes(q)) return false;
        return true;
    }), [notices, filter, q]);

    const markRead = (n) => router.post(route('notices.read', n.id), {}, { preserveScroll: true });
    const readAll = () => router.post(route('notices.read-all'), {}, { preserveScroll: true });
    const openIt = (n) => {
        if (!n.read) {
            return router.post(route('notices.read', n.id), {}, {
                preserveScroll: true, preserveState: true, onFinish: () => router.visit(n.href),
            });
        }
        return router.visit(n.href);
    };
    const dismiss = (n) => router.post(route('notices.dismiss', n.ann), {}, { preserveScroll: true });

    const unread = notices.filter((n) => !n.read).length;

    // رنگ‌ها بر اساسِ زمینه‌ی صفحه
    const cardBg = (n) => (dark
        ? (n.read ? 'rgba(255,255,255,.05)' : 'rgba(255,255,255,.12)')
        : (n.read ? '#fff' : '#f4f8ff'));
    const border = dark ? '1px solid rgba(255,255,255,.14)' : '1px solid var(--line)';
    const muted = dark ? 'rgba(255,255,255,.65)' : 'var(--muted)';
    const chip = (on) => ({
        cursor: 'pointer', fontFamily: 'inherit', padding: '7px 14px', borderRadius: 20,
        fontWeight: 700, fontSize: 12.5, border: 0,
        background: on ? 'linear-gradient(135deg,var(--p1,#3d7bf0),var(--p2,#2555c0))' : (dark ? 'rgba(255,255,255,.1)' : '#eef2f8'),
        color: on ? '#fff' : (dark ? '#fff' : 'var(--navy-800)'),
    });

    return (
        <>
            <div className={dark ? 'k3-card' : 'panel'}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <div style={{ fontWeight: 900, fontSize: 17 }}>📢 اعلان‌ها و پیام‌های من</div>
                    {unread > 0 && <span style={{ background: '#e8505b', color: '#fff', borderRadius: 20, padding: '2px 10px', fontSize: 11.5, fontWeight: 800 }}>{fa(unread)} خوانده‌نشده</span>}
                    <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 جست‌وجو…"
                        style={{ marginInlineStart: 'auto', width: 'auto', maxWidth: 220, padding: '9px 12px', borderRadius: 12, fontFamily: 'inherit',
                            border: dark ? '1px solid rgba(255,255,255,.2)' : '1px solid var(--line)',
                            background: dark ? 'rgba(255,255,255,.1)' : '#fff', color: dark ? '#fff' : 'var(--ink)' }} />
                    {unread > 0 && (
                        <button onClick={readAll} style={{ ...chip(false), fontWeight: 800 }}>✓ همه را خواندم</button>
                    )}
                </div>
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 12 }}>
                    {FILTERS.map((f) => (
                        <button key={f.v} onClick={() => setFilter(f.v)} style={chip(filter === f.v)}>
                            {f.t} ({fa(count(f.v))})
                        </button>
                    ))}
                </div>
            </div>

            {list.length === 0 && (
                <div className={dark ? 'k3-card' : 'panel'} style={{ marginTop: 14, textAlign: 'center', opacity: .85 }}>
                    {q ? 'با این جست‌وجو چیزی پیدا نشد 🔍' : 'موردی برای نمایش نیست 📭'}
                </div>
            )}

            <div style={{ display: 'grid', gap: 10, marginTop: 14 }}>
                {list.map((n) => {
                    const isOpen = open === n.id;
                    return (
                        <div key={n.id} style={{ border, borderInlineStart: `5px solid ${n.color}`, borderRadius: 14, background: cardBg(n), overflow: 'hidden' }}>
                            <div style={{ padding: '13px 15px', display: 'flex', alignItems: 'flex-start', gap: 10 }}>
                                <span style={{ fontSize: 21, flex: 'none' }}>{n.icon}</span>
                                <button onClick={() => setOpen(isOpen ? null : n.id)}
                                    style={{ flex: 1, minWidth: 0, textAlign: 'start', background: 'none', border: 0, cursor: 'pointer', fontFamily: 'inherit', color: 'inherit', padding: 0 }}>
                                    <div style={{ fontWeight: 800, fontSize: 14.5, display: 'flex', gap: 7, alignItems: 'center', flexWrap: 'wrap' }}>
                                        <span>{n.title}</span>
                                        {!n.read && <span style={{ background: '#e8505b', color: '#fff', borderRadius: 20, padding: '1px 8px', fontSize: 10.5, fontWeight: 800 }}>جدید</span>}
                                    </div>
                                    <div style={{ fontSize: 11.5, color: muted, marginTop: 3 }}>
                                        {n.sender ? `از ${n.sender} · ` : ''}{fa(n.date)}
                                    </div>
                                    {n.body && (
                                        <div style={{ fontSize: 13.5, marginTop: 7, lineHeight: 1.9, whiteSpace: 'pre-wrap',
                                            ...(isOpen ? {} : { overflow: 'hidden', textOverflow: 'ellipsis', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical' }) }}>
                                            {n.body}
                                        </div>
                                    )}
                                </button>
                            </div>

                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', padding: '0 15px 13px 15px' }}>
                                <button onClick={() => openIt(n)} style={{ ...chip(true), padding: '7px 15px' }}>← رفتن به این مورد</button>
                                {!n.read && <button onClick={() => markRead(n)} style={chip(false)}>✓ مطالعه شد</button>}
                                {n.ann && (
                                    <button onClick={() => dismiss(n)} title="حذفِ این اعلان"
                                        style={{ ...chip(false), marginInlineStart: 'auto', background: dark ? 'rgba(232,80,91,.25)' : '#fdecee', color: dark ? '#ffb3b3' : '#b0333f' }}>
                                        🗑️ حذف
                                    </button>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </>
    );
}
