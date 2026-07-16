import { usePage, Link } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const THEME_EMOJI = { stars: '🌙', pitch: '⚽', blocks: '🟩', speed: '🏎️', classic: '📘' };

export default function Homework() {
    const { items = [], worksheets = [] } = usePage().props;
    const active = items.filter((i) => !i.overdue);
    const past = items.filter((i) => i.overdue);

    return (
        <ThemedDash title="تکالیف" active="homework">
            <div className="k3-card">
                <div style={{ fontWeight: 900, fontSize: 18, marginBottom: 4 }}>📝 تکالیف من</div>
                <div style={{ opacity: .8, fontSize: 13 }}>{active.length > 0 ? `${active.length} تکلیف در پیش داری — پرشور انجامش بده!` : 'فعلاً تکلیف در پیش نداری 🎉'}</div>
            </div>

            {/* کاربرگ‌های منتشرشده — قابل چاپ و ارسال به معلم */}
            {worksheets.length > 0 && (
                <>
                    <div style={{ fontWeight: 800, margin: '16px 4px 6px' }}>🎨 کاربرگ‌ها</div>
                    <div style={{ display: 'grid', gap: 10, gridTemplateColumns: 'repeat(auto-fill,minmax(220px,1fr))' }}>
                        {worksheets.map((w) => (
                            <Link key={w.id} href={route('my.worksheet', w.id)} className="k3-card" style={{ display: 'block', color: '#fff', textDecoration: 'none', borderInlineStart: '5px solid #a24cf0' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <span style={{ fontSize: 24 }}>{THEME_EMOJI[w.theme] || '🎨'}</span>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontWeight: 800 }}>{w.title}</div>
                                        <div style={{ fontSize: 11.5, opacity: .75 }}>{[w.subject].filter(Boolean).join(' · ')} · {w.date}</div>
                                    </div>
                                </div>
                                <div style={{ marginTop: 8, fontSize: 12.5, fontWeight: 800, color: w.submitted ? '#7be0b0' : '#ffd27a' }}>
                                    {w.submitted ? '✅ فرستادی' : '🖨️ چاپ و ارسال به معلم ←'}
                                </div>
                            </Link>
                        ))}
                    </div>
                </>
            )}

            {active.length > 0 && <div style={{ fontWeight: 800, margin: '16px 4px 6px' }}>📚 تکالیف</div>}
            {active.map((it) => <HwCard key={it.id} it={it} />)}

            {past.length > 0 && (
                <>
                    <div style={{ fontWeight: 800, opacity: .8, margin: '18px 4px 6px' }}>📁 تکالیف گذشته</div>
                    {past.map((it) => <HwCard key={it.id} it={it} dim />)}
                </>
            )}

            {items.length === 0 && worksheets.length === 0 && (
                <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .8 }}>هنوز تکلیفی ثبت نشده 📭</div>
            )}
        </ThemedDash>
    );
}

function HwCard({ it, dim }) {
    return (
        <div className="k3-card" style={{ marginTop: 12, opacity: dim ? .72 : 1, borderInlineStart: `5px solid ${it.overdue ? '#e8505b' : '#2bb673'}` }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <span style={{ fontSize: 26 }}>📚</span>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 800, fontSize: 15.5 }}>{it.title}</div>
                    <div style={{ fontSize: 12, opacity: .75, marginTop: 2 }}>
                        ثبت: {it.date}
                        {it.due_at && <span style={{ marginInlineStart: 8, color: it.overdue ? '#ffb3b3' : '#7be0b0', fontWeight: 700 }}>⏰ مهلت: {it.due_at}</span>}
                    </div>
                </div>
                {it.overdue && <span className="tag" style={{ background: 'rgba(232,80,91,.25)', color: '#ffb3b3', flex: 'none' }}>گذشته</span>}
            </div>
            {it.description && <div style={{ fontSize: 13.5, opacity: .9, marginTop: 10, lineHeight: 2 }}>{it.description}</div>}
            {it.url && <a href={it.url} target="_blank" rel="noreferrer" className="k3-btn ghost" style={{ marginTop: 12, fontSize: 13 }}>{it.is_file ? '⬇️ دریافت فایل تکلیف' : '🔗 مشاهده'}</a>}
        </div>
    );
}
