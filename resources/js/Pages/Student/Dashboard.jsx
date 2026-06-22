import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Themed, { studentNav } from '@/Layouts/Themed';
import { fa, ui, rankColor } from '@/theme';

export default function Dashboard() {
    const { auth, theme, stats, leaderboard, sample } = usePage().props;
    const w = (k, d = '') => theme?.narrative?.[k] ?? d;
    const skin = theme?.skin ?? {};
    const [picked, setPicked] = useState(null);

    return (
        <Themed title="خانه" nav={studentNav('home')} active="home">
            {/* خوش‌آمد */}
            <div style={ui.row('space-between')}>
                <div style={ui.row()}>
                    <div style={ui.avatar(skin)}>{skin.mascot ?? '🌙'}</div>
                    <div>
                        <div style={{ fontWeight: 800, fontSize: 16 }}>سلام {auth?.user?.name} 👋</div>
                        <div style={ui.muted}>{w('rank_title', 'ستاره')} {w('league', '')}</div>
                    </div>
                </div>
                <Link href={route('world.choose')} style={{ ...ui.pill, textDecoration: 'none' }}>🎨 تغییر دنیا</Link>
            </div>

            {/* بنر XP */}
            <div style={{ ...ui.card, marginTop: 16, position: 'relative', overflow: 'hidden', background: 'linear-gradient(120deg,var(--p2),var(--bg2))' }}>
                <span style={{ position: 'absolute', left: -8, top: -14, fontSize: 90, opacity: .2 }}>{skin.hero}</span>
                <div style={ui.muted}>{w('xp_label', 'ستاره‌های این فصل')}</div>
                <div style={{ fontWeight: 800, fontSize: 34 }}>
                    {fa(stats?.xp ?? 0)} <span style={{ fontSize: 16, opacity: .85 }}>{w('xp_unit', 'ستاره')}</span>
                </div>
                <div style={{ ...ui.pill, marginTop: 8, display: 'inline-block' }}>
                    {stats?.classroom ? `کلاس ${stats.classroom}` : 'بدون کلاس'} • معلم: {stats?.teacher ?? '—'}
                </div>
            </div>

            {/* آمار */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10, marginTop: 14 }}>
                <Stat b={fa(stats?.xp ?? 0)} s={w('xp_unit', 'ستاره')} />
                <Stat b={fa(stats?.badges ?? 0)} s="نشان" />
                <Stat b={`#${fa(myRank(leaderboard, auth?.user?.id))}`} s="رتبه" />
            </div>

            {/* تمرین امروز */}
            <SectionTitle>{w('mission_title', 'تمرین امروز')}</SectionTitle>
            {sample ? (
                <div style={ui.card}>
                    <div style={{ ...ui.pill, display: 'inline-block', marginBottom: 10 }}>{skin.mascot} {sample.skill}</div>
                    <div style={{ fontWeight: 800, fontSize: 16, lineHeight: 2.1 }}>{sample.prompt}</div>
                    <div style={{ display: 'grid', gap: 8, marginTop: 14 }}>
                        {sample.choices.map((c, i) => {
                            const st = picked == null ? '' : c.correct ? 'ok' : (picked === i ? 'no' : '');
                            return <button key={i} onClick={() => picked == null && setPicked(i)} style={opt(st)}>{fa(c.value)}</button>;
                        })}
                    </div>
                    <Link href={route('practice.start')} style={{ ...ui.btn, marginTop: 14, textDecoration: 'none' }}>▶️ شروع تمرین کامل</Link>
                </div>
            ) : <div style={ui.card}><div style={ui.muted}>هنوز سؤالی موجود نیست.</div></div>}

            {/* جدول کوتاه */}
            <SectionTitle>{w('leaderboard', 'جدول رقابت')}</SectionTitle>
            {(leaderboard ?? []).slice(0, 3).map((s) => (
                <div key={s.id} style={lbRow(s.is_me)}>
                    <div style={{ width: 26, textAlign: 'center', fontWeight: 800, color: rankColor(s.rank) }}>{fa(s.rank)}</div>
                    <div style={ui.avatar(skin, 34)}>{s.is_me ? '😎' : '🦁'}</div>
                    <div style={{ flex: 1, fontWeight: 700 }}>{s.is_me ? `تو (${s.name})` : s.name}</div>
                    <b style={{ color: 'var(--acc)' }}>{fa(s.xp)}</b>
                </div>
            ))}
        </Themed>
    );
}

const myRank = (lb, id) => (lb ?? []).find((x) => x.id === id)?.rank ?? '-';
const opt = (st) => ({
    padding: 14, borderRadius: 14, fontWeight: 700, cursor: 'pointer', fontFamily: 'inherit', fontSize: 15, color: '#fff',
    background: st === 'ok' ? 'rgba(31,217,104,.16)' : st === 'no' ? 'rgba(255,59,59,.14)' : 'rgba(255,255,255,.06)',
    border: `1.5px solid ${st === 'ok' ? '#1fd968' : st === 'no' ? '#ff4d4d' : 'rgba(255,255,255,.12)'}`,
});
const lbRow = (me) => ({ display: 'flex', alignItems: 'center', gap: 12, padding: 12, borderRadius: 16, marginBottom: 8,
    background: me ? 'linear-gradient(135deg,rgba(255,210,63,.18),rgba(255,255,255,.05))' : 'rgba(255,255,255,.06)',
    border: `1px solid ${me ? 'var(--acc)' : 'rgba(255,255,255,.12)'}` });

function Stat({ b, s }) {
    return (
        <div style={{ ...ui.card, padding: '12px 8px', textAlign: 'center', borderRadius: 18 }}>
            <b style={{ display: 'block', fontSize: 20, color: 'var(--acc)' }}>{b}</b>
            <span style={{ fontSize: 11, color: 'rgba(255,255,255,.6)' }}>{s}</span>
        </div>
    );
}
export function SectionTitle({ children }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '20px 4px 12px', fontWeight: 800, fontSize: 15 }}>
            <span style={{ width: 5, height: 18, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))' }} />
            {children}
        </div>
    );
}
