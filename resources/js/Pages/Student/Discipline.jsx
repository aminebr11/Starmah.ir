import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 18, color: '#fff' };

/** موارد انضباطی دانش‌آموز: جدید (۲۴ ساعت) + سوابق. */
export default function Discipline() {
    const { recent = [], history = [], totals = {} } = usePage().props;

    const Item = (r, i, last) => (
        <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 0', borderBottom: last ? 0 : '1px solid rgba(255,255,255,.08)' }}>
            <span style={{ fontSize: 22 }}>{r.kind === 'positive' ? '🌟' : '⚠️'}</span>
            <div style={{ flex: 1 }}>
                <div style={{ fontWeight: 700 }}>{r.title}</div>
                {r.note && <div style={{ opacity: .7, fontSize: 12 }}>{r.note}</div>}
                <div style={{ opacity: .6, fontSize: 12 }}>{r.date}</div>
            </div>
            <b style={{ color: r.points >= 0 ? '#7be05a' : '#ff8d8d', fontSize: 16 }}>{r.points >= 0 ? '+' : ''}{fa(r.points)}</b>
        </div>
    );

    return (
        <ThemedDash title="موارد انضباطی من" active="discipline">
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 6 }}>
                <div style={{ ...card, textAlign: 'center' }}>
                    <div style={{ fontSize: 26 }}>🌟</div>
                    <div style={{ fontSize: 24, fontWeight: 800, color: '#7be05a' }}>+{fa(totals.positive ?? 0)}</div>
                    <div style={{ opacity: .7, fontSize: 12 }}>مجموع تشویق</div>
                </div>
                <div style={{ ...card, textAlign: 'center' }}>
                    <div style={{ fontSize: 26 }}>⚠️</div>
                    <div style={{ fontSize: 24, fontWeight: 800, color: '#ff8d8d' }}>{fa(totals.negative ?? 0)}</div>
                    <div style={{ opacity: .7, fontSize: 12 }}>مجموع کسر امتیاز</div>
                </div>
            </div>

            {/* اعلان‌های جدید (۲۴ ساعت اخیر) */}
            <Title>🔔 جدید (۲۴ ساعت اخیر)</Title>
            <div style={{ ...card, border: recent.length ? '2px solid var(--acc)' : card.border }}>
                {recent.length ? recent.map((r, i) => Item(r, i, i === recent.length - 1)) : <span style={{ opacity: .7 }}>مورد جدیدی نیست 🎉</span>}
            </div>

            {/* سوابق */}
            <Title>📚 سوابق موارد انضباطی</Title>
            <div style={card}>
                {history.length ? history.map((r, i) => Item(r, i, i === history.length - 1)) : <span style={{ opacity: .7 }}>سابقه‌ای ثبت نشده.</span>}
            </div>
        </ThemedDash>
    );
}

function Title({ children }) {
    return <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '20px 4px 12px', fontWeight: 800, fontSize: 16, color: '#fff' }}>
        <span style={{ width: 5, height: 18, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))' }} />{children}</div>;
}
