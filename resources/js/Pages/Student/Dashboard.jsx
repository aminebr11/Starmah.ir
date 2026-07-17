import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ThemedDash from '@/Layouts/ThemedDash';
import TeamHeader from '@/Components/TeamHeader';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** مرحله‌بندی: امتیازِ هر مرحله را معلم تنظیم می‌کند (پیش‌فرض ۱۵۰) */
const levelOf = (xp, step) => Math.floor((xp ?? 0) / step) + 1;
const levelProgress = (xp, step) => ((xp ?? 0) % step) / step;

/** تزئین‌های شناور هر تم (بر اساس skin.pattern) */
const FLOATS = {
    stars: ['⭐', '✨', '🌙', '☄️'],
    pitch: ['⚽', '🥅', '🏆', '👟'],
    blocks: ['🟩', '⛏️', '💎', '🧱'],
    speed: ['🏎️', '🏁', '💨', '🔥'],
};
const FLOAT_POS = [
    { top: '8%', insetInlineEnd: '5%', fontSize: 26, animationDelay: '0s' },
    { bottom: '12%', insetInlineEnd: '18%', fontSize: 18, animationDelay: '-2s' },
    { top: '18%', insetInlineStart: '38%', fontSize: 16, animationDelay: '-4s' },
    { bottom: '8%', insetInlineStart: '55%', fontSize: 20, animationDelay: '-5.5s' },
];

export default function Dashboard() {
    const { auth, theme, me = {}, groups = [], sample, notices = [], notifications = [], unreadNotices = 0, levelXp = 150, levelNames = [] } = usePage().props;
    const w = (k, d = '') => theme?.narrative?.[k] ?? d;
    const skin = theme?.skin ?? {};
    const [picked, setPicked] = useState(null);

    const LEVEL_XP = Number(levelXp) > 0 ? Number(levelXp) : 150;
    const xp = Number(me.xp) || 0;
    const level = levelOf(xp, LEVEL_XP);
    const prog = levelProgress(xp, LEVEL_XP);
    const ringProg = Math.max(0, Math.min(1, prog || 0)); // کلمپ‌شده و بدون NaN
    const toNext = LEVEL_XP - (xp % LEVEL_XP);
    const levelName = levelNames[level - 1] || `${w('level', 'مرحله')} ${fa(level)}`;
    const R = 52, C = 2 * Math.PI * R;

    const maxTotal = Math.max(1, ...groups.map((g) => g.total));
    const top3 = groups.slice(0, 3);
    // چیدمان سکو: دوم | اول | سوم
    const podiumOrder = [top3[1], top3[0], top3[2]].filter(Boolean);
    const podiumClass = { 0: 'p2', 1: 'p1', 2: 'p3' };
    const floats = FLOATS[skin.pattern] ?? FLOATS.stars;
    const myTeam = groups.find((g) => g.mine);

    // کاشی‌های اکشن — رنگ اختصاصی هر کاشی
    const smartLab = usePage().props.smartLab;
    const tiles = [
        { href: '/missions', em: '🎯', label: 'مأموریت روزانه', t1: skin.p1, t2: skin.p2 },
        { href: '/game-world', em: '🎮', label: 'دنیای بازی‌ها', t1: '#e8505b', t2: '#b0333f' },
        ...(smartLab ? [{ href: '/student/smart-exams', em: '🧠', label: 'آزمون هوشمند', t1: '#7c5cf0', t2: '#4c2fb0' }] : []),
        { href: '/my-grades', em: '📔', label: 'نمرات کلاسی', t1: '#18a97c', t2: '#0d6b4e' },
    ];

    // کارت‌های کلاس من (موارد انضباطی، فعالیت‌ها، محتوا، تکالیف، گزارش‌ها)
    const cards = [
        { href: '/my-discipline', em: '⭐', label: 'موارد انضباطی', sub: 'تشویق‌ها و تذکرها', t1: '#f5b53f', t2: '#d98f0f' },
        { href: '/my-activities', em: '🎁', label: 'فعالیت‌ها و امتیازها', sub: 'تاریخچه‌ی امتیازها', t1: '#2bb673', t2: '#1a8a52' },
        { href: '/class-content', em: '📚', label: 'محتوای کلاس', sub: 'جزوه، پادکست، گالری', t1: '#3d7bf0', t2: '#2555c0' },
        { href: '/homework', em: '📝', label: 'تکالیف', sub: 'کارهای در پیش', t1: '#a24cf0', t2: '#6f2fb0' },
        { href: '/my-reports', em: '📈', label: 'گزارش‌ها و نمودارها', sub: 'تحلیل کامل عملکرد', t1: '#0ea5b7', t2: '#0a7d8a' },
    ];

    return (
        <ThemedDash title="خانه" active="home">
            {/* هدر تیمی (هویت تیم از طرح قدیم) */}
            <TeamHeader />

            {/* ===== کارت قهرمان ===== */}
            <div className="k3-card" style={{ marginTop: 14, overflow: 'hidden' }}>
                <div className="k3-floats">
                    {floats.map((f, i) => <i key={i} style={FLOAT_POS[i]}>{f}</i>)}
                </div>
                <div className="k3-hero" style={{ position: 'relative' }}>
                    {/* آواتار با حلقه‌ی پیشرفت مرحله — از بالا شروع، رنگی و واضح */}
                    <div className="k3-avatar">
                        <svg viewBox="0 0 120 120" width="112" height="112" style={{ transform: 'rotate(-90deg)' }}>
                            <defs>
                                <linearGradient id="ringGrad" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" stopColor="var(--acc)" />
                                    <stop offset="100%" stopColor="var(--p1)" />
                                </linearGradient>
                            </defs>
                            <circle cx="60" cy="60" r={R} fill="none" stroke="rgba(255,255,255,.18)" strokeWidth="9" />
                            <circle cx="60" cy="60" r={R} fill="none" stroke="url(#ringGrad)" strokeWidth="9" strokeLinecap="round"
                                strokeDasharray={C} strokeDashoffset={C * (1 - ringProg)}
                                style={{ transition: 'stroke-dashoffset 1s cubic-bezier(.2,.8,.3,1)', filter: 'drop-shadow(0 0 5px var(--acc))' }} />
                        </svg>
                        <div className="face">{skin.character ?? skin.mascot ?? theme?.emoji}</div>
                        <div className="lvl">{levelName}</div>
                        <div className="ringpct">{fa(Math.round(ringProg * 100))}٪</div>
                    </div>

                    <div style={{ flex: 1, minWidth: 180 }}>
                        <div style={{ fontSize: 13.5, opacity: .85 }}>سلام {auth?.user?.name} 👋</div>
                        <div style={{ fontSize: 24, fontWeight: 900, lineHeight: 1.5 }}>{theme?.emoji} {me.group ?? theme?.name}</div>
                        <div style={{ fontSize: 12.5, opacity: .8, marginTop: 2 }}>
                            {me.classroom ? `کلاس ${me.classroom}` : ''}{me.teacher ? ` · معلم: ${me.teacher}` : ''}
                        </div>
                        <div style={{ fontSize: 12, marginTop: 8, background: 'rgba(0,0,0,.25)', display: 'inline-block', borderRadius: 20, padding: '4px 12px' }}>
                            ⚡ {fa(toNext)} {w('xp_unit', 'امتیاز')} تا {w('level', 'مرحله')} {fa(level + 1)}
                        </div>
                    </div>

                    <div style={{ textAlign: 'center', flex: 'none' }}>
                        <div style={{ fontSize: 40, fontWeight: 900, color: 'var(--acc)', textShadow: '0 4px 14px rgba(0,0,0,.4)', lineHeight: 1.2 }}>{fa(xp)}</div>
                        <div style={{ fontSize: 12, opacity: .85 }}>{w('xp_unit', 'امتیاز')} من</div>
                    </div>
                </div>

                {/* چیپ‌های آمار */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10, marginTop: 16, position: 'relative' }}>
                    <div className="k3-chip"><b>{me.rank_class ? `#${fa(me.rank_class)}` : '—'}</b><span>رتبه در کلاس ({fa(me.class_size ?? 0)} نفر)</span></div>
                    <div className="k3-chip"><b>{me.rank_group ? `#${fa(me.rank_group)}` : '—'}</b><span>رتبه در تیم</span></div>
                    <div className="k3-chip"><b>{fa(me.badges ?? 0)}</b><span>نشان‌های من</span></div>
                </div>
            </div>

            {/* ===== کاشی‌های اکشن ===== */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 12, marginTop: 16 }}>
                {tiles.map((t) => (
                    <Link key={t.href} href={t.href} className="k3-tile" style={{ '--tile': t.t1, '--tile2': t.t2 }}>
                        <span className="shine" />
                        <span className="em">{t.em}</span>
                        {t.label}
                    </Link>
                ))}
            </div>

            {/* ===== کارت‌های کلاس من ===== */}
            <div style={{ marginTop: 20 }}>
                <SectionTitle>🧩 کارت‌های کلاس من</SectionTitle>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 12 }}>
                    {cards.map((c) => (
                        <Link key={c.href} href={c.href} className="k3-homecard" style={{ '--c1': c.t1, '--c2': c.t2 }}>
                            <span className="hc-em">{c.em}</span>
                            <span className="hc-lbl">{c.label}</span>
                            <span className="hc-sub">{c.sub}</span>
                        </Link>
                    ))}
                </div>
            </div>

            {/* ===== اعلان‌ها (قالب‌های رنگیِ متنوع + لیبل چشمک‌زنِ جدید) ===== */}
            {notifications.length > 0 && (
                <div className={`k3-card ${unreadNotices > 0 ? 'notice-blink' : ''}`} style={{ marginTop: 16 }}>
                    <div style={{ display: 'flex', alignItems: 'center', marginBottom: 12 }}>
                        <div style={{ fontWeight: 900, fontSize: 16 }}>🔔 اعلان‌ها و پیام‌ها
                            {unreadNotices > 0 && <span className="new-badge" style={{ marginInlineStart: 8 }}>{fa(unreadNotices)} جدید ✨</span>}
                        </div>
                        <Link href="/notices" style={{ marginInlineStart: 'auto', color: 'var(--acc)', fontWeight: 800, fontSize: 13 }}>همه ←</Link>
                    </div>
                    <div style={{ display: 'grid', gap: 10 }}>
                        {notifications.slice(0, 4).map((n, i) => (
                            <Link key={n.id} href={n.href} className={`notice-tpl nt-c${i % 6}`} style={{ display: 'block' }}>
                                <div className="nt-title">
                                    <span style={{ marginInlineEnd: 6 }}>{n.icon}</span>{n.title}
                                    {!n.read && <span className="new-badge" style={{ marginInlineStart: 8, background: 'rgba(255,255,255,.28)' }}>جدید</span>}
                                </div>
                                {n.body && <div className="nt-body" style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{n.body}</div>}
                                <div className="nt-meta">🕐 {n.date}</div>
                            </Link>
                        ))}
                    </div>
                </div>
            )}

            {/* ===== دو ستون در دسکتاپ ===== */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(330px,1fr))', gap: 16, marginTop: 20, alignItems: 'start' }}>
                {/* رقابت تیم‌ها + سکو */}
                <div className="k3-card">
                    <SectionTitle>🏆 {w('leaderboard', 'رقابت تیم‌ها')}</SectionTitle>
                    {top3.length > 0 && (
                        <div className="podium">
                            {podiumOrder.map((g, i) => {
                                const rank = groups.indexOf(g) + 1;
                                return (
                                    <div key={g.name} className={`pcol ${podiumClass[i]}`}>
                                        <span className="pchar" style={{ animationDelay: `${-i}s` }}>{g.emoji}</span>
                                        <span className="pname">{g.name}</span>
                                        <span className="pxp">{fa(g.total)} {w('xp_unit', '')}</span>
                                        <div className="pblock" style={{ '--pc': g.color }}>
                                            <span className="prank">{rank === 1 ? '👑' : fa(rank)}</span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                    <div style={{ display: 'grid', gap: 8, marginTop: 14 }}>
                        {groups.map((g, i) => (
                            <div key={i} className={`k3-teambar ${g.mine ? 'mine' : ''}`}>
                                <div className="tico" style={{ background: g.color }}>{g.emoji}</div>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontWeight: 800, fontSize: 13.5, display: 'flex', gap: 6, alignItems: 'center' }}>
                                        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{g.name}</span>
                                        {g.mine && <span style={{ fontSize: 10, color: 'var(--acc)', flex: 'none' }}>★ تیم تو</span>}
                                    </div>
                                    <div className="tbar"><i style={{ width: `${(g.total / maxTotal) * 100}%`, background: g.color }} /></div>
                                </div>
                                <div style={{ textAlign: 'center', flex: 'none' }}>
                                    <div style={{ fontWeight: 800, color: 'var(--acc)', fontSize: 14 }}>{fa(g.total)}</div>
                                    <div style={{ fontSize: 10, opacity: .65 }}>{fa(g.count)} نفر</div>
                                </div>
                            </div>
                        ))}
                        {groups.length === 0 && <span style={{ opacity: .7, fontSize: 13 }}>هنوز رقابتی شکل نگرفته.</span>}
                    </div>

                    {/* نفرات برتر تیم من */}
                    {myTeam && (
                        <>
                            <SectionTitle style={{ marginTop: 18 }}>⭐ ستاره‌های تیم تو</SectionTitle>
                            <div style={{ display: 'grid', gap: 6 }}>
                                {myTeam.top.map((m, i) => (
                                    <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '7px 10px', borderRadius: 13, background: m.me ? 'rgba(255,255,255,.09)' : 'transparent', border: m.me ? '1px solid var(--acc)' : '1px solid transparent' }}>
                                        <span className={`k3-medal ${i < 3 ? `m${i + 1}` : ''}`}>{fa(i + 1)}</span>
                                        <span style={{ flex: 1, fontWeight: m.me ? 900 : 600, fontSize: 14 }}>{m.me ? `تو (${m.name})` : m.name}</span>
                                        <b style={{ color: 'var(--acc)' }}>{fa(m.xp)}</b>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </div>

                {/* مأموریت امروز */}
                <div className="k3-card">
                    <SectionTitle>🎯 {w('mission_title', 'مأموریت امروز')}</SectionTitle>
                    {sample ? (
                        <>
                            <div style={{ display: 'inline-flex', alignItems: 'center', gap: 7, fontSize: 12, fontWeight: 800, padding: '5px 13px', borderRadius: 30, background: 'rgba(0,0,0,.28)', border: '1px solid rgba(255,255,255,.16)', marginBottom: 12 }}>
                                {skin.mascot} {sample.skill}
                            </div>
                            <div style={{ fontWeight: 800, fontSize: 16.5, lineHeight: 2.1 }}>{sample.prompt}</div>
                            <div style={{ display: 'grid', gap: 9, marginTop: 14, gridTemplateColumns: '1fr 1fr' }}>
                                {sample.choices.map((c, i) => {
                                    const st = picked == null ? '' : c.correct ? 'ok' : (picked === i ? 'no' : '');
                                    return (
                                        <button key={i} onClick={() => picked == null && setPicked(i)}
                                            style={{
                                                padding: 14, borderRadius: 15, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit', fontSize: 16, color: '#fff', transition: '.15s',
                                                background: st === 'ok' ? 'linear-gradient(180deg,#2bb673,#1d8a55)' : st === 'no' ? 'linear-gradient(180deg,#e8505b,#b03340)' : 'rgba(255,255,255,.08)',
                                                border: '1px solid rgba(255,255,255,.16)',
                                                boxShadow: st ? '0 4px 0 rgba(0,0,0,.35), inset 0 2px 0 rgba(255,255,255,.25)' : 'inset 0 2px 4px rgba(0,0,0,.25)',
                                            }}>{fa(c.value)}{st === 'ok' ? ' ✅' : st === 'no' ? ' ❌' : ''}</button>
                                    );
                                })}
                            </div>
                            {picked != null && (
                                <div style={{ marginTop: 12, fontWeight: 800, fontSize: 14, color: sample.choices[picked]?.correct ? '#7be05a' : '#ffb3b3' }}>
                                    {sample.choices[picked]?.correct ? w('reward_title', 'آفرین! 🎉') : 'اشکالی نداره، تو مأموریت جبران کن! 💪'}
                                </div>
                            )}
                            <Link href={route('missions')} className="k3-btn" style={{ width: '100%', marginTop: 16, fontSize: 16 }}>
                                🚀 شروع {w('mission_title', 'مأموریت')}
                            </Link>
                        </>
                    ) : (
                        <div style={{ opacity: .75, fontSize: 14 }}>
                            هنوز مأموریتی موجود نیست — از کاشی «🎮 مأموریت و بازی» سر بزن!
                        </div>
                    )}

                    {/* دسترسی سریع دوم */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginTop: 16 }}>
                        <Link href="/leaderboard" className="k3-btn ghost" style={{ fontSize: 13.5 }}>🏆 {w('leaderboard', 'جدول رقابت')}</Link>
                        <Link href="/progress" className="k3-btn ghost" style={{ fontSize: 13.5 }}>📈 کارنامه‌ی من</Link>
                    </div>
                </div>
            </div>
        </ThemedDash>
    );
}

function SectionTitle({ children, style = {} }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '2px 2px 12px', fontWeight: 900, fontSize: 16, color: '#fff', ...style }}>
            <span style={{ width: 5, height: 18, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))', boxShadow: '0 0 8px var(--acc)' }} />
            {children}
        </div>
    );
}
