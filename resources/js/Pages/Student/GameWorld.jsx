import { Link, usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const DIFF = { easy: 'آسان', medium: 'متوسط', hard: 'سخت' };
const STATUS = {
    new: { t: 'جدید', c: '#2bb673', btn: 'شروع بازی' },
    in_progress: { t: 'در حال انجام', c: '#e8862e', btn: 'ادامه بازی' },
    done: { t: 'تکمیل‌شده', c: '#3d7bf0', btn: 'بازی دوباره' },
    locked: { t: 'قفل', c: '#8896ad', btn: 'قفل' },
};
const PAL = [['#e8505b', '#b0333f'], ['#3d7bf0', '#2555c0'], ['#2bb673', '#1a8a52'], ['#a24cf0', '#6f2fb0'], ['#f0952e', '#c06712'], ['#0ea5b7', '#0a7d8a']];

export default function GameWorld() {
    const { me = {}, cards = [], stats = {} } = usePage().props;
    const active = cards.filter((c) => c.status === 'new' || c.status === 'in_progress');
    const done = cards.filter((c) => c.status === 'done');
    const locked = cards.filter((c) => c.status === 'locked');

    return (
        <ThemedDash title="دنیای بازی‌های آموزشی" active="gameworld">
            {/* هدر پروفایل */}
            <div className="k3-card" style={{ background: 'linear-gradient(135deg,var(--p1),var(--p2))' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 44 }}>🎮</span>
                    <div style={{ flex: 1, minWidth: 160 }}>
                        <div style={{ fontWeight: 900, fontSize: 20 }}>سلام {me.name} 👋</div>
                        <div style={{ opacity: .9, fontSize: 13, marginTop: 2 }}>به دنیای بازی‌های آموزشی خوش اومدی!</div>
                    </div>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <Chip icon="⚡" v={fa(me.xp)} l="امتیاز" />
                        <Chip icon="🏅" v={fa(me.badges)} l="نشان" />
                        <Chip icon="🎚️" v={fa(me.level)} l="مرحله" />
                        {me.rank_group && <Chip icon="🏆" v={`#${fa(me.rank_group)}`} l="رتبه در گروه" />}
                    </div>
                </div>
            </div>

            {/* آمار سریع */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10, marginTop: 14 }}>
                <Stat icon="🎯" v={fa(stats.active)} l="بازی فعال" c="#2bb673" />
                <Stat icon="✅" v={fa(stats.done)} l="انجام‌شده" c="#3d7bf0" />
                <Stat icon="🔒" v={fa(stats.locked)} l="قفل‌شده" c="#8896ad" />
            </div>

            {cards.length === 0 && <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .85 }}>هنوز بازی‌ای برایت منتشر نشده — به‌زودی! 🕹️</div>}

            <Section title="🎯 بازی‌های فعال" list={active} />
            <Section title="💡 بازی‌های پیشنهادی" list={done.length ? [] : []} hide />
            <Section title="✅ بازی‌های انجام‌شده" list={done} />
            <Section title="🔒 بازی‌های قفل‌شده" list={locked} locked />
        </ThemedDash>
    );
}

function Section({ title, list, locked, hide }) {
    if (hide || !list.length) return null;
    return (
        <>
            <div style={{ fontWeight: 900, fontSize: 16, margin: '20px 4px 10px', display: 'flex', alignItems: 'center', gap: 8 }}>
                <span style={{ width: 6, height: 20, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))', boxShadow: '0 0 8px var(--acc)' }} />{title}
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(250px,1fr))', gap: 14 }}>
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
            <div style={{ height: 96, borderRadius: 14, marginBottom: 10, display: 'grid', placeItems: 'center', fontSize: 44, position: 'relative', overflow: 'hidden',
                background: g.cover ? `center/cover url(${g.cover})` : `linear-gradient(135deg,${pal[0]},${pal[1]})` }}>
                {!g.cover && g.icon}
                <span style={{ position: 'absolute', top: 8, insetInlineEnd: 8, background: s.c, color: '#fff', borderRadius: 20, padding: '2px 10px', fontSize: 11, fontWeight: 800 }}>{s.t}</span>
            </div>
            <div style={{ fontWeight: 900, fontSize: 15.5 }}>{g.title}</div>
            <div style={{ fontSize: 12, opacity: .78, marginTop: 3, lineHeight: 1.8 }}>
                {g.template} · {g.subject || ''}{g.grade ? ` · پایه ${g.grade}` : ''}
            </div>
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 8, fontSize: 11 }}>
                {g.theme && <span style={{ background: 'rgba(255,255,255,.12)', borderRadius: 20, padding: '3px 9px' }}>{g.theme_emoji} {g.theme}</span>}
                <span style={{ background: 'rgba(255,255,255,.12)', borderRadius: 20, padding: '3px 9px' }}>❓ {fa(g.questions)} سؤال</span>
                <span style={{ background: 'rgba(255,255,255,.12)', borderRadius: 20, padding: '3px 9px' }}>⚡ {fa(g.maxPoints)}</span>
                <span style={{ background: 'rgba(255,255,255,.12)', borderRadius: 20, padding: '3px 9px' }}>{DIFF[g.difficulty]}</span>
            </div>
            {g.teacher && <div style={{ fontSize: 11, opacity: .65, marginTop: 6 }}>👩‍🏫 {g.teacher}</div>}
            {locked
                ? <div style={{ marginTop: 10, textAlign: 'center', fontWeight: 800, fontSize: 13, opacity: .8 }}>🔒 {g.lockReason || 'قفل'}</div>
                : <div className="k3-btn" style={{ marginTop: 10, fontSize: 14 }}>{g.status === 'done' ? '🔁' : '🚀'} {s.btn}</div>}
        </>
    );
    if (locked) return <div className="k3-card" style={{ opacity: .7 }}>{inner}</div>;
    return <Link href={route('gameworld.play', g.id)} className="k3-card" style={{ display: 'block', color: 'inherit' }}>{inner}</Link>;
}

function Chip({ icon, v, l }) {
    return <div style={{ background: 'rgba(0,0,0,.22)', borderRadius: 14, padding: '8px 12px', textAlign: 'center', minWidth: 64 }}>
        <div style={{ fontSize: 16 }}>{icon}</div><b style={{ fontSize: 16 }}>{v}</b><div style={{ fontSize: 10, opacity: .85 }}>{l}</div></div>;
}
function Stat({ icon, v, l, c }) {
    return <div style={{ borderRadius: 16, padding: 14, textAlign: 'center', color: '#fff', background: `linear-gradient(135deg,${c},${c}bb)` }}>
        <div style={{ fontSize: 22 }}>{icon}</div><b style={{ fontSize: 22 }}>{v}</b><div style={{ fontSize: 11.5, opacity: .9 }}>{l}</div></div>;
}
