import { useState, useEffect, useRef } from 'react';
import { Link, router, usePage } from '@inertiajs/react';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * زنگوله‌ی اعلان — مشترکِ همه‌ی نقش‌ها.
 *
 * پیش از این فقط لِی‌اوتِ دانش‌آموز زنگوله‌ی بازشو داشت و معلم/مدیر فقط یک
 * لینک به صفحه‌ی اعلان‌ها می‌دیدند؛ چون آن صفحه هم فقط «اطلاعیه‌ها» را
 * می‌خواند، پیامِ والدین و کاربرگِ ارسالی عملاً هیچ‌جا دیده نمی‌شد.
 *
 * `tone="dark"` برای لِی‌اوتِ تیره‌ی دانش‌آموز، `"light"` برای داشبوردِ
 * معلم/مدیر.
 */
export default function BellMenu({ tone = 'light' }) {
    const { notifications = [], unreadNotices = 0 } = usePage().props;
    const [open, setOpen] = useState(false);
    const box = useRef(null);

    // کلیک بیرون یا Esc → بسته شود
    useEffect(() => {
        if (!open) return undefined;
        const onDown = (e) => { if (box.current && !box.current.contains(e.target)) setOpen(false); };
        const onKey = (e) => { if (e.key === 'Escape') setOpen(false); };
        document.addEventListener('pointerdown', onDown);
        document.addEventListener('keydown', onKey);
        return () => { document.removeEventListener('pointerdown', onDown); document.removeEventListener('keydown', onKey); };
    }, [open]);

    const dark = tone === 'dark';
    const unread = unreadNotices || 0;

    /** کلیک روی اعلان: اول «مطالعه شد» ثبت می‌شود، بعد به مقصد می‌رویم. */
    const go = (n, e) => {
        e.preventDefault();
        setOpen(false);
        const visit = () => router.visit(n.href);
        if (n.read) return visit();
        return router.post(route('notices.read', n.id), {}, {
            preserveScroll: true, preserveState: true, onFinish: visit,
        });
    };

    const readAll = () => router.post(route('notices.read-all'), {}, { preserveScroll: true });

    const btnStyle = dark
        ? { background: 'rgba(255,255,255,.1)', border: '1px solid rgba(255,255,255,.2)', color: '#fff' }
        : { background: '#fff', border: '1px solid var(--line)', color: 'var(--navy-800)' };

    return (
        <div style={{ position: 'relative' }} ref={box}>
            <button onClick={() => setOpen(!open)} className={unread > 0 ? 'bell-live' : ''}
                aria-label="اعلان‌ها" title="اعلان‌ها و پیام‌ها"
                style={{ ...btnStyle, position: 'relative', width: 40, height: 40, borderRadius: 12, cursor: 'pointer', fontSize: 18 }}>
                🔔
                {unread > 0 && (
                    <span style={{ position: 'absolute', top: -4, insetInlineEnd: -4, background: '#e8505b', color: '#fff', borderRadius: 20, minWidth: 18, height: 18, fontSize: 11, fontWeight: 800, display: 'grid', placeItems: 'center', padding: '0 4px', boxShadow: '0 0 0 2px rgba(255,255,255,.35)' }}>
                        {fa(unread)}
                    </span>
                )}
            </button>

            {open && (
                <div style={{ position: 'absolute', top: 48, insetInlineEnd: 0, width: 320, maxWidth: '88vw', background: '#fff', color: 'var(--ink)', borderRadius: 16, boxShadow: '0 20px 50px -20px rgba(0,0,0,.5)', zIndex: 120, overflow: 'hidden' }}>
                    <div style={{ padding: '11px 14px', fontWeight: 800, borderBottom: '1px solid #eef2f8', display: 'flex', alignItems: 'center', gap: 8 }}>
                        🔔 اعلان‌ها
                        {unread > 0 && <span style={{ background: '#e8505b', color: '#fff', borderRadius: 20, padding: '1px 8px', fontSize: 11 }}>{fa(unread)} جدید</span>}
                        {unread > 0 && (
                            <button onClick={readAll} style={{ marginInlineStart: 'auto', border: 0, background: 'none', color: '#2555c0', fontWeight: 700, fontSize: 11.5, cursor: 'pointer', fontFamily: 'inherit' }}>
                                ✓ خواندنِ همه
                            </button>
                        )}
                    </div>

                    {notifications.length === 0 && (
                        <div style={{ padding: 18, color: '#6b7794', fontSize: 13, textAlign: 'center' }}>اعلان تازه‌ای نداری 🎉</div>
                    )}

                    <div style={{ maxHeight: 360, overflowY: 'auto' }}>
                        {notifications.map((n) => (
                            <a key={n.id} href={n.href} onClick={(e) => go(n, e)}
                                style={{ padding: '11px 14px', borderBottom: '1px solid #f3f6fb', display: 'flex', alignItems: 'flex-start', gap: 10, color: 'inherit', textDecoration: 'none', background: n.read ? '#fff' : '#f4f8ff', borderInlineStart: `4px solid ${n.color}` }}>
                                <span style={{ fontSize: 18, flex: 'none' }}>{n.icon}</span>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontSize: 13, fontWeight: 700, display: 'flex', gap: 6 }}>
                                        <span style={{ flex: 1 }}>{n.title}</span>
                                        {!n.read && <span style={{ width: 8, height: 8, borderRadius: 8, background: '#e8505b', flex: 'none', marginTop: 4 }} />}
                                    </div>
                                    {n.body && <div style={{ fontSize: 12, color: '#6b7794', marginTop: 2, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{n.body}</div>}
                                    <div style={{ fontSize: 10.5, color: '#9aa6bd', marginTop: 2 }}>{n.date}</div>
                                </div>
                            </a>
                        ))}
                    </div>

                    <Link href="/notices" onClick={() => setOpen(false)}
                        style={{ display: 'block', padding: '11px 14px', textAlign: 'center', color: '#2555c0', fontWeight: 800, fontSize: 13, borderTop: '1px solid #eef2f8' }}>
                        مشاهده‌ی همه ←
                    </Link>
                </div>
            )}
        </div>
    );
}
