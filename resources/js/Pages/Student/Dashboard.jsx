import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ThemedDash from '@/Layouts/ThemedDash';
import TeamHeader from '@/Components/TeamHeader';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Dashboard() {
    const { auth, theme, me = {}, groups = [], sample, notices = [] } = usePage().props;
    const w = (k, d = '') => theme?.narrative?.[k] ?? d;
    const skin = theme?.skin ?? {};
    const [picked, setPicked] = useState(null);

    const maxTotal = Math.max(1, ...groups.map((g) => g.total));

    return (
        <ThemedDash title="خانه" active="home"
            actions={<Link href="/world" style={{ ...pill, textDecoration: 'none' }}>🎨 تغییر تیم</Link>}>

            {/* هدر تیمی — مطابق طرح ۴ هدر */}
            <TeamHeader />

            {/* بنر تیم من */}
            <div style={{ ...card, position: 'relative', overflow: 'hidden', background: 'linear-gradient(120deg,var(--p2),rgba(0,0,0,.2))' }}>
                <span style={{ position: 'absolute', insetInlineStart: -10, top: -16, fontSize: 100, opacity: .18 }}>{skin.hero}</span>
                <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, alignItems: 'center' }}>
                    <div>
                        <div style={{ fontSize: 13, opacity: .85 }}>سلام {auth?.user?.name} 👋 — تیم تو:</div>
                        <div style={{ fontSize: 26, fontWeight: 800 }}>{skin.character ?? theme?.emoji} {me.group ?? theme?.name}</div>
                        <div style={{ fontSize: 13, opacity: .85, marginTop: 4 }}>{me.classroom ? `کلاس ${me.classroom}` : ''} · معلم: {me.teacher ?? '—'}</div>
                    </div>
                    <div style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: 34, fontWeight: 800, color: 'var(--acc)' }}>{fa(me.xp ?? 0)}</div>
                        <div style={{ fontSize: 12, opacity: .85 }}>{w('xp_unit', 'امتیاز')} من</div>
                    </div>
                </div>
            </div>

            {/* آمار سریع */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 12, marginTop: 14 }}>
                <Stat b={me.rank_class ? `#${fa(me.rank_class)}` : '—'} s={`رتبه در کلاس (از ${fa(me.class_size ?? 0)})`} />
                <Stat b={me.rank_group ? `#${fa(me.rank_group)}` : '—'} s="رتبه در تیم" />
                <Stat b={fa(me.badges ?? 0)} s="نشان" />
            </div>

            {/* اعلان‌ها و پیام‌ها */}
            {notices.length > 0 && (
                <div style={{ ...card, marginTop: 14 }}>
                    <div style={{ display: 'flex', alignItems: 'center', marginBottom: 8 }}>
                        <div style={{ fontWeight: 800 }}>📢 اعلان‌ها و پیام‌ها</div>
                        <Link href="/notices" style={{ marginInlineStart: 'auto', color: 'var(--acc)', fontWeight: 700, fontSize: 13 }}>همه ←</Link>
                    </div>
                    {notices.map((n) => (
                        <Link key={n.id} href="/notices" style={{ display: 'block', padding: '9px 0', borderTop: '1px solid rgba(255,255,255,.12)', color: 'inherit' }}>
                            <div style={{ fontWeight: 700, fontSize: 14 }}>
                                {n.personal ? '✉️ ' : '📢 '}{n.title}
                                <span style={{ opacity: .7, fontWeight: 400, fontSize: 12, marginInlineStart: 6 }}>· {n.date}</span>
                            </div>
                            <div style={{ opacity: .82, fontSize: 12.5, marginTop: 2, overflow: 'hidden', textOverflow: 'ellipsis', display: '-webkit-box', WebkitLineClamp: 1, WebkitBoxOrient: 'vertical' }}>{n.body}</div>
                        </Link>
                    ))}
                </div>
            )}

            {/* رقابت تیم‌ها */}
            <SectionTitle>🏆 رقابت تیم‌های کلاس</SectionTitle>
            <div style={{ display: 'grid', gap: 12 }}>
                {groups.map((g, i) => (
                    <div key={i} style={{ ...card, padding: 14, border: g.mine ? '2px solid var(--acc)' : '1px solid rgba(255,255,255,.12)' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                            <div style={{ width: 30, textAlign: 'center', fontWeight: 800, color: i === 0 ? '#ffd23f' : 'rgba(255,255,255,.6)' }}>{i === 0 ? '👑' : fa(i + 1)}</div>
                            <div style={{ width: 42, height: 42, borderRadius: 12, display: 'grid', placeItems: 'center', fontSize: 22, background: g.color }}>{g.emoji}</div>
                            <div style={{ flex: 1 }}>
                                <div style={{ fontWeight: 800 }}>{g.name} {g.mine && <span style={{ fontSize: 11, color: 'var(--acc)' }}>(تیم تو)</span>}</div>
                                <div style={{ height: 7, borderRadius: 6, background: 'rgba(255,255,255,.12)', marginTop: 6, overflow: 'hidden' }}>
                                    <div style={{ width: `${(g.total / maxTotal) * 100}%`, height: '100%', background: g.color }} />
                                </div>
                            </div>
                            <div style={{ textAlign: 'center' }}>
                                <div style={{ fontWeight: 800, color: 'var(--acc)' }}>{fa(g.total)}</div>
                                <div style={{ fontSize: 11, opacity: .7 }}>{fa(g.count)} نفر</div>
                            </div>
                        </div>
                    </div>
                ))}
                {groups.length === 0 && <div style={{ ...card }}><span style={{ opacity: .7 }}>هنوز رقابتی شکل نگرفته.</span></div>}
            </div>

            {/* نفرات برتر تیم من */}
            {groups.find((g) => g.mine) && (
                <>
                    <SectionTitle>⭐ نفرات برتر تیم تو</SectionTitle>
                    <div style={card}>
                        {groups.find((g) => g.mine).top.map((m, i) => (
                            <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '9px 0', borderBottom: i < 4 ? '1px solid rgba(255,255,255,.08)' : 0 }}>
                                <span style={{ width: 24, fontWeight: 800, color: i === 0 ? '#ffd23f' : 'rgba(255,255,255,.6)' }}>{fa(i + 1)}</span>
                                <span style={{ flex: 1, fontWeight: m.me ? 800 : 600 }}>{m.me ? `تو (${m.name})` : m.name}</span>
                                <b style={{ color: 'var(--acc)' }}>{fa(m.xp)}</b>
                            </div>
                        ))}
                    </div>
                </>
            )}

            {/* مأموریت امروز */}
            <SectionTitle>{w('mission_title', 'مأموریت امروز')}</SectionTitle>
            {sample ? (
                <div style={card}>
                    <div style={{ ...pill, display: 'inline-block', marginBottom: 10 }}>{skin.mascot} {sample.skill}</div>
                    <div style={{ fontWeight: 800, fontSize: 16, lineHeight: 2.1 }}>{sample.prompt}</div>
                    <div style={{ display: 'grid', gap: 8, marginTop: 14, gridTemplateColumns: '1fr 1fr' }}>
                        {sample.choices.map((c, i) => {
                            const st = picked == null ? '' : c.correct ? 'ok' : (picked === i ? 'no' : '');
                            return <button key={i} onClick={() => picked == null && setPicked(i)} style={opt(st)}>{fa(c.value)}</button>;
                        })}
                    </div>
                    <Link href={route('practice.start')} style={{ ...btn, marginTop: 14, textDecoration: 'none' }}>▶️ شروع مأموریت کامل</Link>
                </div>
            ) : <div style={card}><span style={{ opacity: .7 }}>هنوز مأموریتی موجود نیست.</span></div>}
        </ThemedDash>
    );
}

/* استایل‌های درون‌خطی تم‌دار */
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 18, color: '#fff', backdropFilter: 'blur(6px)' };
const pill = { fontSize: 11, fontWeight: 700, padding: '5px 10px', borderRadius: 30, background: 'rgba(255,255,255,.12)', border: '1px solid rgba(255,255,255,.2)', color: '#fff' };
const btn = { display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, width: '100%', border: 0, cursor: 'pointer', fontFamily: 'inherit', fontWeight: 800, fontSize: 15, color: '#fff', padding: 14, borderRadius: 14, background: 'linear-gradient(135deg,var(--p1),var(--p2))' };
const opt = (st) => ({ padding: 13, borderRadius: 12, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit', fontSize: 15, color: '#fff',
    background: st === 'ok' ? 'rgba(43,182,115,.25)' : st === 'no' ? 'rgba(232,80,91,.25)' : 'rgba(255,255,255,.07)',
    border: `1.5px solid ${st === 'ok' ? '#2bb673' : st === 'no' ? '#e8505b' : 'rgba(255,255,255,.15)'}` });

function Stat({ b, s }) {
    return <div style={{ ...card, padding: '12px 8px', textAlign: 'center' }}><b style={{ display: 'block', fontSize: 22, color: 'var(--acc)' }}>{b}</b><span style={{ fontSize: 11, opacity: .7 }}>{s}</span></div>;
}
function SectionTitle({ children }) {
    return <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '22px 4px 12px', fontWeight: 800, fontSize: 16, color: '#fff' }}>
        <span style={{ width: 5, height: 18, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))' }} />{children}</div>;
}
