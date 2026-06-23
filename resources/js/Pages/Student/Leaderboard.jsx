import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 18, color: '#fff' };

/** رقابت — جدول کامل کلاس بر اساس امتیاز. */
export default function Leaderboard() {
    const { theme, classroom, leaderboard = [] } = usePage().props;
    const w = (k, d = '') => theme?.narrative?.[k] ?? d;
    const skin = theme?.skin ?? {};
    const top = leaderboard.slice(0, 3);

    return (
        <ThemedDash title={w('leaderboard', 'رقابت تیم‌ها')} active="board">
            <div style={{ textAlign: 'center', marginBottom: 8 }}>
                <div style={{ fontSize: 40 }}>{skin.hero ?? '🏆'}</div>
                <p style={{ opacity: .8, fontSize: 14 }}>{classroom ? `کلاس ${classroom}` : ''} · فصل جاری</p>
            </div>

            {/* سکوی قهرمانی */}
            <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'flex-end', gap: 10, margin: '10px 0 18px' }}>
                {[1, 0, 2].map((pos) => {
                    const s = top[pos]; if (!s) return <div key={pos} />;
                    const h = pos === 0 ? 80 : pos === 2 ? 50 : 64;
                    return (
                        <div key={pos} style={{ textAlign: 'center' }}>
                            {pos === 0 && <div style={{ fontSize: 22 }}>👑</div>}
                            <div style={{ width: pos === 0 ? 54 : 44, height: pos === 0 ? 54 : 44, borderRadius: '50%', margin: 'auto', display: 'grid', placeItems: 'center', fontSize: 22, background: 'linear-gradient(135deg,var(--p1),var(--p2))' }}>{s.is_me ? '😎' : '🧑'}</div>
                            <div style={{ fontSize: 12, opacity: .85, margin: '4px 0' }}>{s.name.split(' ')[0]}</div>
                            <div style={{ height: h, width: 58, background: 'linear-gradient(var(--p1),transparent)', borderRadius: '12px 12px 0 0', display: 'grid', placeItems: 'center', fontWeight: 800 }}>{fa(s.rank)}</div>
                        </div>
                    );
                })}
            </div>

            <div style={{ ...card, padding: 8 }}>
                {leaderboard.map((s) => (
                    <div key={s.id} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '11px 12px', borderRadius: 12, marginBottom: 4,
                        background: s.is_me ? 'linear-gradient(135deg,rgba(255,255,255,.14),rgba(255,255,255,.04))' : 'transparent',
                        border: s.is_me ? '1px solid var(--acc)' : '1px solid transparent' }}>
                        <span style={{ width: 26, textAlign: 'center', fontWeight: 800, color: s.rank <= 3 ? 'var(--acc)' : 'rgba(255,255,255,.6)' }}>{fa(s.rank)}</span>
                        <span style={{ width: 32, height: 32, borderRadius: '50%', display: 'grid', placeItems: 'center', background: 'linear-gradient(135deg,var(--p1),var(--p2))' }}>{s.is_me ? '😎' : '🧑'}</span>
                        <span style={{ flex: 1, fontWeight: 700 }}>{s.is_me ? `تو (${s.name})` : s.name}</span>
                        <b style={{ color: 'var(--acc)' }}>{fa(s.xp)}</b>
                    </div>
                ))}
                {leaderboard.length === 0 && <div style={{ padding: 14, opacity: .7 }}>هنوز رقابتی شکل نگرفته.</div>}
            </div>
            <p style={{ textAlign: 'center', opacity: .7, fontSize: 13, marginTop: 16 }}>کنار رقابت تیمی، مسیر «رکورد شخصی» هم داری — هر روز از دیروز بهتر! 💪</p>
        </ThemedDash>
    );
}
