import { Link, usePage } from '@inertiajs/react';
import openNotice from '@/lib/openNotice';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * خلاصه‌ی اعلان‌های پیشخوان (مدیرِ مدرسه، والد، ادمینِ کل) — فقط دیده‌نشده‌ها.
 * کلیک = «مطالعه شد» و رفتن به مقصد؛ حذف یا «همه را خواندم» در زنگوله یا صفحه‌ی
 * اعلان‌ها هم آن را از این‌جا برمی‌دارد. وقتی چیزِ دیده‌نشده‌ای نیست، چیزی نشان نمی‌دهد.
 */
export default function DashNotices() {
    const { dashNotices = [], unreadNotices = 0 } = usePage().props;
    if (!dashNotices.length) return null;
    return (
        <div className="panel notice-blink" style={{ borderColor: 'var(--gold)', marginBottom: 18 }}>
            <h3 style={{ display: 'flex', alignItems: 'center', gap: 8 }}>🔔 اعلان‌ها و پیام‌های دیده‌نشده
                <span className="tag" style={{ background: '#e8505b', color: '#fff' }}>{fa(unreadNotices)}</span>
                <Link href="/notices" className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>همه ←</Link>
            </h3>
            {dashNotices.map((n) => (
                <a key={n.id} href={n.href} onClick={(e) => openNotice(n, e)} style={{ display: 'block', padding: '10px 0', borderBottom: '1px solid var(--line)', color: 'var(--ink)' }}>
                    <div style={{ fontWeight: 800 }}>
                        {n.icon} {n.title}
                        <span style={{ color: 'var(--muted-2)', fontWeight: 400, fontSize: 12, marginInlineStart: 6 }}>· {n.date}</span>
                    </div>
                    {n.body && <div style={{ color: 'var(--muted)', fontSize: 13.5, marginTop: 2, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{n.body}</div>}
                </a>
            ))}
        </div>
    );
}
