import { usePage } from '@inertiajs/react';
import Themed, { studentNav } from '@/Layouts/Themed';
import { fa, ui, rankColor } from '@/theme';

export default function Leaderboard() {
    const { theme, classroom, leaderboard } = usePage().props;
    const w = (k, d = '') => theme?.narrative?.[k] ?? d;
    const skin = theme?.skin ?? {};
    const top = leaderboard.slice(0, 3);

    return (
        <Themed title="جدول" nav={studentNav('board')} active="board">
            <div style={{ textAlign: 'center' }}>
                <div style={{ fontSize: 40 }}>{skin.hero ?? '🏆'}</div>
                <div style={ui.h}>{w('leaderboard', 'جدول رقابت')}</div>
                <p style={ui.muted}>{classroom ? `کلاس ${classroom}` : ''} • فصل جاری</p>
            </div>

            {/* سکو */}
            <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'flex-end', gap: 10, margin: '18px 0' }}>
                {[1, 0, 2].map((pos) => {
                    const s = top[pos]; if (!s) return <div key={pos} />;
                    const h = pos === 0 ? 70 : pos === 2 ? 46 : 58;
                    const col = pos === 0 ? 'var(--acc)' : pos === 2 ? 'var(--p2)' : 'var(--p1)';
                    return (
                        <div key={pos} style={{ textAlign: 'center' }}>
                            {pos === 0 && <div style={{ fontSize: 22 }}>👑</div>}
                            <div style={{ ...ui.avatar(skin, pos === 0 ? 54 : 44), margin: 'auto' }}>{s.is_me ? '😎' : '🦁'}</div>
                            <div style={ui.muted}>{s.name.split(' ')[0]}</div>
                            <div style={{ height: h, width: 58, background: `linear-gradient(${col},transparent)`, borderRadius: '12px 12px 0 0', display: 'grid', placeItems: 'center', fontWeight: 800 }}>{fa(s.rank)}</div>
                        </div>
                    );
                })}
            </div>

            {leaderboard.map((s) => (
                <div key={s.id} style={lbRow(s.is_me)}>
                    <div style={{ width: 28, textAlign: 'center', fontWeight: 800, color: rankColor(s.rank) }}>{fa(s.rank)}</div>
                    <div style={ui.avatar(skin, 34)}>{s.is_me ? '😎' : '🦁'}</div>
                    <div style={{ flex: 1, fontWeight: 700 }}>{s.is_me ? `تو (${s.name})` : s.name}</div>
                    <b style={{ color: 'var(--acc)' }}>{fa(s.xp)}</b>
                </div>
            ))}
            <p style={{ ...ui.muted, textAlign: 'center', marginTop: 18 }}>
                کنار رقابت، مسیر «رکورد شخصی» هم داری — هر روز از دیروز بهتر! 💪
            </p>
        </Themed>
    );
}

const lbRow = (me) => ({ display: 'flex', alignItems: 'center', gap: 12, padding: 12, borderRadius: 16, marginBottom: 8,
    background: me ? 'linear-gradient(135deg,rgba(255,210,63,.18),rgba(255,255,255,.05))' : 'rgba(255,255,255,.06)',
    border: `1px solid ${me ? 'var(--acc)' : 'rgba(255,255,255,.12)'}` });
