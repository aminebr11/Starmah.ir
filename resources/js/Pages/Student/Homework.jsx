import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

export default function Homework() {
    const { items = [] } = usePage().props;
    const active = items.filter((i) => !i.overdue);
    const past = items.filter((i) => i.overdue);

    return (
        <ThemedDash title="تکالیف" active="homework">
            <div className="k3-card">
                <div style={{ fontWeight: 900, fontSize: 18, marginBottom: 4 }}>📝 تکالیف من</div>
                <div style={{ opacity: .8, fontSize: 13 }}>{active.length > 0 ? `${active.length} تکلیف در پیش داری — پرشور انجامش بده!` : 'فعلاً تکلیف در پیش نداری 🎉'}</div>
            </div>

            {active.map((it) => <HwCard key={it.id} it={it} />)}

            {past.length > 0 && (
                <>
                    <div style={{ fontWeight: 800, opacity: .8, margin: '18px 4px 6px' }}>📁 تکالیف گذشته</div>
                    {past.map((it) => <HwCard key={it.id} it={it} dim />)}
                </>
            )}

            {items.length === 0 && (
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
