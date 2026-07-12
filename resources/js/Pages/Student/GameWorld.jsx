import { Link, usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const DIFF = { easy: '🟢 آسان', medium: '🟡 متوسط', hard: '🔴 سخت' };
const STATUS = {
    new: { t: 'جدید', c: '#2bb673', btn: '🚀 شروع بازی' },
    in_progress: { t: 'در حال انجام', c: '#e8862e', btn: '▶️ ادامه بازی' },
    done: { t: 'تکمیل ✓', c: '#3d7bf0', btn: '🔁 بازی دوباره' },
    locked: { t: '🔒 قفل', c: '#8896ad', btn: '' },
};
const PAL = [['#e8505b', '#b0333f'], ['#3d7bf0', '#2555c0'], ['#2bb673', '#1a8a52'], ['#a24cf0', '#6f2fb0'], ['#f0952e', '#c06712'], ['#0ea5b7', '#0a7d8a']];

export default function GameWorld() {
    const { me = {}, cards = [], stats = {} } = usePage().props;
    const active = cards.filter((c) => c.status === 'new' || c.status === 'in_progress');
    const done = cards.filter((c) => c.status === 'done');
    const locked = cards.filter((c) => c.status === 'locked');

    return (
        <ThemedDash title="دنیای بازی‌های آموزشی" active="gameworld">
            {/* هدر قهرمان با تزئین شناور */}
            <div className="k3-card" style={{ background: 'linear-gradient(135deg,var(--p1),var(--p2))', overflow: 'hidden', position: 'relative' }}>
                <div className="k3-floats">
                    <i style={{ top: '10%', insetInlineEnd: '4%', fontSize: 26 }}>🎮</i>
                    <i style={{ bottom: '14%', insetInlineEnd: '16%', fontSize: 18, animationDelay: '-2s' }}>🕹️</i>
                    <i style={{ top: '20%', insetInlineStart: '34%', fontSize: 16, animationDelay: '-4s' }}>⭐</i>
                    <i style={{ bottom: '8%', insetInlineStart: '52%', fontSize: 20, animationDelay: '-5.5s' }}>🏆</i>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap', position: 'relative' }}>
                    <span style={{ fontSize: 48 }} className="gbob">🎮</span>
                    <div style={{ flex: 1, minWidth: 160 }}>
                        <div style={{ fontWeight: 900, fontSize: 21 }}>سلام {me.name} 👋</div>
                        <div style={{ opacity: .92, fontSize: 13, marginTop: 2 }}>بازی کن، یاد بگیر، امتیاز بگیر! ⚡ هر بازی فقط بارِ اول XP می‌دهد.</div>
                    </div>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <Chip icon="⚡" v={fa(me.xp)} l="امتیاز" />
                        <Chip icon="🏅" v={fa(me.badges)} l="نشان" />
                        <Chip icon="🎚️" v={fa(me.level)} l="مرحله" />
                        {me.rank_group && <Chip icon="🏆" v={`#${fa(me.rank_group)}`} l={`رتبه در گروه (${fa(me.group_size)})`} />}
                    </div>
                </div>
            </div>

            {/* آمار سریع */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10, marginTop: 14 }}>
                <Stat icon="🎯" v={fa(stats.active)} l="بازی فعال" c="#2bb673" />
                <Stat icon="✅" v={fa(stats.done)} l="انجام‌شده" c="#3d7bf0" />
                <Stat icon="🔒" v={fa(stats.locked)} l="به‌زودی" c="#8896ad" />
            </div>

            {cards.length === 0 && (
                <div className="k3-card" style={{ marginTop: 16, textAlign: 'center', padding: 30 }}>
                    <div style={{ fontSize: 50 }} className="gbob">🕹️</div>
                    <div style={{ fontWeight: 900, fontSize: 17, marginTop: 8 }}>هنوز بازی‌ای برایت منتشر نشده</div>
                    <div style={{ opacity: .8, fontSize: 13, marginTop: 4 }}>به‌محض اینکه معلم بازی جدیدی بسازد، همین‌جا با اعلان 🔔 خبرت می‌کنیم!</div>
                </div>
            )}

            {/* همه‌ی بازی‌ها کنار هم — فعال، انجام‌شده، قفل */}
            <Section title="🕹️ بازی‌های تو" list={[...active, ...done]}
                sub={done.length ? 'بازی‌های تکمیل‌شده را می‌توانی برای تمرین دوباره بازی کنی — XP فقط بارِ اول محاسبه شده.' : null} />
            <Section title="🔒 به‌زودی باز می‌شوند" list={locked} locked />
        </ThemedDash>
    );
}

function Section({ title, list, locked, sub }) {
    if (!list.length) return null;
    return (
        <>
            <div style={{ margin: '20px 4px 10px' }}>
                <div style={{ fontWeight: 900, fontSize: 16, display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span style={{ width: 6, height: 20, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))', boxShadow: '0 0 8px var(--acc)' }} />
                    {title} <span style={{ opacity: .6, fontSize: 13 }}>({fa(list.length)})</span>
                </div>
                {sub && <div style={{ fontSize: 12, opacity: .7, marginTop: 3, marginInlineStart: 14 }}>{sub}</div>}
            </div>
            <div className="gw-grid">
                {list.map((g, i) => <Card key={g.id} g={g} i={i} locked={locked} />)}
            </div>
        </>
    );
}

function Card({ g, i, locked }) {
    const s = STATUS[g.status] || STATUS.new;
    const pal = PAL[i % PAL.length];
    const inner = (
        <>
            <div className="gw-cover" style={{ background: g.cover ? `center/cover url(${g.cover})` : `linear-gradient(135deg,${pal[0]},${pal[1]})` }}>
                <span className="shine" />
                {!g.cover && <span className={g.status === 'new' ? 'gbob' : ''}>{g.icon}</span>}
                <span className="gw-ribbon" style={{ background: s.c }}>{s.t}</span>
            </div>
            <div className="gw-body">
                <div style={{ fontWeight: 900, fontSize: 14.5, lineHeight: 1.7 }}>{g.title}</div>
                <div style={{ fontSize: 11, opacity: .75 }}>{g.template}{g.subject ? ` · ${g.subject}` : ''}</div>
                <div className="gw-chips">
                    <span>❓ {fa(g.questions)}</span>
                    <span>⚡ {fa(g.maxPoints)}</span>
                    <span>{DIFF[g.difficulty]}</span>
                    {g.theme && <span>{g.theme_emoji} {g.theme}</span>}
                </div>
                {g.status === 'done' && <div style={{ fontSize: 10.5, color: '#93c5fd', fontWeight: 700 }}>⚡ XP گرفته شد ({fa(g.score)}) — دور بعد بدون XP</div>}
                {g.teacher && <div style={{ fontSize: 10.5, opacity: .6 }}>👩‍🏫 {g.teacher}</div>}
                {locked
                    ? <div style={{ marginTop: 'auto', textAlign: 'center', fontWeight: 800, fontSize: 12, opacity: .85, background: 'rgba(0,0,0,.25)', borderRadius: 12, padding: '8px 6px' }}>🔒 {g.lockReason || 'به‌زودی'}</div>
                    : <div className="k3-btn" style={{ marginTop: 'auto', fontSize: 13, padding: '10px 8px' }}>{s.btn}</div>}
            </div>
        </>
    );
    if (locked) return <div className="gw-card" style={{ opacity: .72 }}>{inner}</div>;
    return <Link href={route('gameworld.play', g.id)} className={`gw-card ${g.status === 'new' ? 'is-new' : ''}`}>{inner}</Link>;
}

function Chip({ icon, v, l }) {
    return <div style={{ background: 'rgba(0,0,0,.22)', borderRadius: 14, padding: '8px 12px', textAlign: 'center', minWidth: 64 }}>
        <div style={{ fontSize: 16 }}>{icon}</div><b style={{ fontSize: 16 }}>{v}</b><div style={{ fontSize: 10, opacity: .85 }}>{l}</div></div>;
}
function Stat({ icon, v, l, c }) {
    return <div style={{ borderRadius: 16, padding: 14, textAlign: 'center', color: '#fff', background: `linear-gradient(135deg,${c},${c}bb)` }}>
        <div style={{ fontSize: 22 }}>{icon}</div><b style={{ fontSize: 22 }}>{v}</b><div style={{ fontSize: 11.5, opacity: .9 }}>{l}</div></div>;
}
