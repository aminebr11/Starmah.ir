import { Head, usePage, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

/**
 * داشبورد دانش‌آموز — تم‌دار و end-to-end.
 * کل ظاهر از روی prop سراسری `theme` (که موتور تم در بک‌اند می‌سازد) ساخته می‌شود:
 *   - رنگ‌ها از theme.skin به‌صورت CSS variables تزریق می‌شوند (لایه ۱)
 *   - واژگان از theme.narrative خوانده می‌شوند (لایه ۲)
 *   - سؤال نمونه از قبل توسط موتور روکش خورده است (لایه ۳)
 */
export default function Dashboard() {
    const { auth, theme, stats, leaderboard, sample } = usePage().props;
    const w = (k, d = '') => theme?.narrative?.[k] ?? d;
    const skin = theme?.skin ?? {};

    const cssVars = useMemo(() => ({
        '--bg1': skin.bg1 ?? '#0b1224',
        '--bg2': skin.bg2 ?? '#111a36',
        '--p1': skin.p1 ?? '#19c37d',
        '--p2': skin.p2 ?? '#0f9d63',
        '--acc': skin.acc ?? '#ffd23f',
        '--acc2': skin.acc2 ?? '#ff4d4d',
        '--ring': skin.ring ?? '#19c37d',
    }), [theme?.id]);

    const [picked, setPicked] = useState(null);

    return (
        <div dir="rtl" style={{ ...cssVars, minHeight: '100vh',
            background: 'linear-gradient(180deg,var(--bg1),var(--bg2))', color: '#fff',
            fontFamily: 'Vazirmatn, system-ui, sans-serif', transition: 'background .5s' }}>
            <Head title="داشبورد" />

            <div style={{ maxWidth: 460, margin: '0 auto', padding: '22px 16px 60px' }}>

                {/* هدر */}
                <div style={row('space-between')}>
                    <div style={row()}>
                        <div style={avatar(skin)}>{skin.mascot ?? '🌟'}</div>
                        <div>
                            <div style={{ fontWeight: 800, fontSize: 16 }}>سلام {auth?.user?.name} 👋</div>
                            <div style={muted}>{w('rank_title', 'قهرمان')} {w('league', '')}</div>
                        </div>
                    </div>
                    <Link href={route('logout')} method="post" as="button" style={pill}>خروج</Link>
                </div>

                {/* بنر XP */}
                <div style={{ ...card, marginTop: 16, position: 'relative', overflow: 'hidden',
                    background: 'linear-gradient(120deg,var(--p2),var(--bg2))' }}>
                    <span style={{ position: 'absolute', left: -8, top: -14, fontSize: 90, opacity: .22 }}>{skin.hero}</span>
                    <div style={muted}>{w('xp_label', 'امتیاز این فصل')}</div>
                    <div style={{ fontWeight: 800, fontSize: 34 }}>
                        {fa(stats?.xp ?? 0)} <span style={{ fontSize: 16, opacity: .8 }}>{w('xp_unit', 'امتیاز')}</span>
                    </div>
                    <div style={{ ...pill, marginTop: 8, display: 'inline-block' }}>
                        {stats?.classroom ? `کلاس ${stats.classroom}` : 'بدون کلاس'} • معلم: {stats?.teacher ?? '—'}
                    </div>
                </div>

                {/* آمار */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10, marginTop: 14 }}>
                    <Stat b={fa(stats?.xp ?? 0)} s={w('xp_unit', 'امتیاز')} />
                    <Stat b={fa(stats?.badges ?? 0)} s="نشان" />
                    <Stat b={`#${fa(myRank(leaderboard, auth?.user?.id))}`} s="رتبه" />
                </div>

                {/* مسابقه‌ی امروز (سؤال روکش‌خورده) */}
                <SectionTitle>{w('mission_title', 'تمرین امروز')}</SectionTitle>
                {sample ? (
                    <div style={card}>
                        <div style={{ ...pill, display: 'inline-block', marginBottom: 10 }}>{skin.mascot} {sample.skill}</div>
                        <div style={{ fontWeight: 800, fontSize: 16, lineHeight: 2.1 }}>{sample.prompt}</div>
                        <div style={{ display: 'grid', gap: 8, marginTop: 14 }}>
                            {sample.choices.map((c, i) => {
                                const state = picked == null ? '' : c.correct ? 'correct' : (picked === i ? 'wrong' : '');
                                return (
                                    <button key={i} onClick={() => picked == null && setPicked(i)} style={opt(state)}>
                                        {fa(c.value)}
                                    </button>
                                );
                            })}
                        </div>
                        {picked != null && (
                            <div style={{ marginTop: 12, textAlign: 'center', fontWeight: 800,
                                color: sample.choices[picked].correct ? 'var(--p1)' : 'var(--acc2)' }}>
                                {sample.choices[picked].correct ? `${w('reward_title', 'آفرین!')} +${fa(sample.xp)} ${w('xp_unit', '')}` : 'دوباره تلاش کن'}
                            </div>
                        )}
                    </div>
                ) : <div style={card}><div style={muted}>هنوز سؤالی موجود نیست.</div></div>}

                {/* جدول رقابتی */}
                <SectionTitle>{w('leaderboard', 'جدول رقابت')}</SectionTitle>
                {(leaderboard ?? []).map((s) => (
                    <div key={s.id} style={lbRow(s.is_me)}>
                        <div style={{ width: 26, textAlign: 'center', fontWeight: 800, color: rankColor(s.rank) }}>{fa(s.rank)}</div>
                        <div style={{ ...avatar(skin), width: 34, height: 34, fontSize: 16 }}>{s.is_me ? '😎' : '🦁'}</div>
                        <div style={{ flex: 1, fontWeight: 700 }}>{s.is_me ? `تو (${s.name})` : s.name}</div>
                        <b style={{ color: 'var(--acc)' }}>{fa(s.xp)}</b>
                    </div>
                ))}

                <div style={{ ...muted, textAlign: 'center', marginTop: 22, lineHeight: 2 }}>
                    🎨 این صفحه کاملاً از «موتور تم» ساخته شده. تم را در پروفایل عوض کنی، همه‌چیز عوض می‌شود.
                </div>
            </div>
        </div>
    );
}

/* ---------- helpers & styles ---------- */
const fa = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const myRank = (lb, id) => (lb ?? []).find((x) => x.id === id)?.rank ?? '-';
const rankColor = (r) => r === 1 ? '#ffd23f' : r === 2 ? '#cdd3dc' : r === 3 ? '#e29a5b' : 'rgba(255,255,255,.6)';

const row = (j = 'flex-start') => ({ display: 'flex', alignItems: 'center', gap: 10, justifyContent: j });
const muted = { color: 'rgba(255,255,255,.6)', fontSize: 12, lineHeight: 1.9 };
const card = { background: 'rgba(255,255,255,.06)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 22, padding: 16 };
const pill = { fontSize: 11, fontWeight: 700, padding: '5px 10px', borderRadius: 30, background: 'rgba(255,255,255,.1)',
    border: '1px solid rgba(255,255,255,.12)', color: '#fff', cursor: 'pointer', fontFamily: 'inherit' };
const avatar = (skin) => ({ width: 46, height: 46, borderRadius: 16, display: 'grid', placeItems: 'center',
    fontSize: 22, background: `linear-gradient(135deg,${skin.p1 ?? '#19c37d'},${skin.p2 ?? '#0f9d63'})`, flex: 'none' });
const opt = (state) => ({
    padding: 15, borderRadius: 16, fontWeight: 700, cursor: 'pointer', fontFamily: 'inherit', fontSize: 15, color: '#fff',
    background: state === 'correct' ? 'rgba(31,217,104,.16)' : state === 'wrong' ? 'rgba(255,59,59,.14)' : 'rgba(255,255,255,.06)',
    border: `1.5px solid ${state === 'correct' ? 'var(--p1)' : state === 'wrong' ? 'var(--acc2)' : 'rgba(255,255,255,.12)'}`,
});
const lbRow = (me) => ({ display: 'flex', alignItems: 'center', gap: 12, padding: 12, borderRadius: 16, marginBottom: 8,
    background: me ? 'linear-gradient(135deg,rgba(255,210,63,.18),rgba(255,255,255,.05))' : 'rgba(255,255,255,.06)',
    border: `1px solid ${me ? 'var(--acc)' : 'rgba(255,255,255,.12)'}` });

function Stat({ b, s }) {
    return (
        <div style={{ ...card, padding: '12px 8px', textAlign: 'center', borderRadius: 18 }}>
            <b style={{ display: 'block', fontSize: 20, color: 'var(--acc)' }}>{b}</b>
            <span style={{ fontSize: 11, color: 'rgba(255,255,255,.6)' }}>{s}</span>
        </div>
    );
}
function SectionTitle({ children }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '20px 4px 12px', fontWeight: 800, fontSize: 15 }}>
            <span style={{ width: 5, height: 18, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))' }} />
            {children}
        </div>
    );
}
