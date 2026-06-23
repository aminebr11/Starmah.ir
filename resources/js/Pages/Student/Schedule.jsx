import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 16, color: '#fff' };

export default function Schedule() {
    const { days = [], entries = {}, today, jtoday } = usePage().props;
    return (
        <ThemedDash title="برنامه کلاسی" active="schedule">
            <div style={{ textAlign: 'center', marginBottom: 14 }}>
                <div style={{ fontSize: 34 }}>🗓️</div>
                <div style={{ opacity: .85 }}>امروز: <b style={{ color: 'var(--acc)' }}>{jtoday}</b></div>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(150px,1fr))', gap: 12 }}>
                {days.map((d, i) => (
                    <div key={i} style={{ ...card, border: i === today ? '2px solid var(--acc)' : card.border, padding: 12 }}>
                        <div style={{ fontWeight: 800, textAlign: 'center', marginBottom: 10, color: i === today ? 'var(--acc)' : '#fff' }}>
                            {d}{i === today && ' ⭐'}
                        </div>
                        {(entries[i] ?? []).map((e) => (
                            <div key={e.id} style={{ background: 'rgba(255,255,255,.1)', borderRadius: 10, padding: '8px 10px', marginBottom: 6 }}>
                                <div style={{ fontWeight: 700, fontSize: 13 }}>{e.title}</div>
                                {e.time && <div style={{ opacity: .7, fontSize: 11 }}>{e.time}</div>}
                            </div>
                        ))}
                        {!(entries[i] ?? []).length && <div style={{ opacity: .5, fontSize: 12, textAlign: 'center' }}>—</div>}
                    </div>
                ))}
            </div>
        </ThemedDash>
    );
}
