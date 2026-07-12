import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 18, color: '#fff' };

/** کارنامه‌ی من — تسلط، نمرات، امتیازهای گرفته/ازدست‌رفته، انضباط، نشان‌ها. */
export default function Progress() {
    const { stats = {}, mastery = [], recent = [], points_log = [], summary = {}, grades = [], discipline = [], badges = [] } = usePage().props;
    const byType = summary.by_type ?? [];
    const maxType = Math.max(1, ...byType.map((x) => x.points));

    return (
        <ThemedDash title="کارنامه‌ی من" active="progress">
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 12 }} className="prog-stats">
                <Stat b={fa(stats.xp ?? 0)} s="امتیاز کل" c1="#f5b53f" c2="#d98f0f" />
                <Stat b={`${fa(stats.avg ?? 0)}٪`} s="میانگین تسلط" c1="#2bb673" c2="#1a8a52" />
                <Stat b={`⭐${fa(stats.stars ?? 0)}`} s="ستاره انضباط" c1="#0ea5b7" c2="#0a7d8a" />
                <Stat b={fa(stats.badges ?? 0)} s="نشان" c1="#a24cf0" c2="#6f2fb0" />
            </div>

            <Title>📔 نمرات دفتر کلاسی</Title>
            <div style={{ ...card, display: 'grid', gap: 10 }}>
                {grades.length ? grades.map((g, i) => (
                    <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <b style={{ fontSize: 13 }}>{g.title}</b>
                        {g.type === 'numeric'
                            ? <span style={{ color: 'var(--acc)', fontWeight: 800 }}>{fa(g.score)} <span style={{ opacity: .6, fontSize: 12 }}>از {fa(g.max)}</span></span>
                            : <span style={{ color: 'var(--acc)', fontWeight: 700 }}>{g.text}</span>}
                    </div>
                )) : <span style={{ opacity: .7 }}>هنوز نمره‌ای ثبت نشده.</span>}
            </div>

            <Title>🎯 امتیازهای من از کجا آمده؟</Title>
            <div style={{ ...card, display: 'grid', gap: 12 }}>
                {byType.length ? byType.map((x, i) => (
                    <div key={i}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 5 }}>
                            <b style={{ fontSize: 13 }}>{x.label}</b><span style={{ opacity: .7, fontSize: 12 }}>{fa(x.points)} امتیاز</span>
                        </div>
                        <div style={{ height: 11, borderRadius: 8, background: 'rgba(255,255,255,.12)', overflow: 'hidden' }}>
                            <div style={{ width: `${(x.points / maxType) * 100}%`, height: '100%', background: barFor(i) }} />
                        </div>
                    </div>
                )) : <span style={{ opacity: .7 }}>هنوز امتیازی نگرفتی.</span>}
                <div style={{ textAlign: 'center', opacity: .8, fontSize: 13, marginTop: 4 }}>📅 امتیاز این هفته: <b style={{ color: 'var(--acc)' }}>{fa(summary.week_points ?? 0)}</b></div>
            </div>

            <Title>📊 تسلط بر مهارت‌ها</Title>
            <div style={{ ...card, display: 'grid', gap: 14 }}>
                {mastery.length ? mastery.map((m, i) => (
                    <div key={i}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
                            <b style={{ fontSize: 13 }}>{m.skill}</b>
                            <span style={{ opacity: .7, fontSize: 12 }}>{fa(m.mastery)}٪ · {label(m.mastery)}</span>
                        </div>
                        <div style={{ height: 11, borderRadius: 8, background: 'rgba(255,255,255,.12)', overflow: 'hidden' }}>
                            <div style={{ width: `${m.mastery}%`, height: '100%', background: barFor(i) }} />
                        </div>
                    </div>
                )) : <span style={{ opacity: .7 }}>هنوز تمرینی ثبت نشده. یک مأموریت انجام بده!</span>}
            </div>

            <Title>🎯 امتیازهای اخیر</Title>
            <div style={{ ...card, display: 'grid', gap: 10 }}>
                {recent.length ? recent.map((r, i) => {
                    const good = r.accuracy >= 60;
                    return (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <span style={{ fontSize: 16 }}>{good ? '🟢' : '🔴'}</span>
                                <b style={{ fontSize: 13 }}>{r.skill}</b>
                            </span>
                            <span style={{ color: good ? '#7be05a' : '#ff8d8d', fontWeight: 800 }}>{fa(r.score)}/{fa(r.max)}</span>
                            <span style={{ opacity: .6, fontSize: 12 }}>{fa(r.date)}</span>
                        </div>
                    );
                }) : <span style={{ opacity: .7 }}>—</span>}
            </div>

            <Title>🧾 دفتر امتیاز من (کجا گرفتم / کجا از دست دادم)</Title>
            <div style={{ ...card, display: 'grid', gap: 8 }}>
                {points_log.length ? points_log.map((e, i) => (
                    <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: i < points_log.length - 1 ? '1px solid rgba(255,255,255,.08)' : 0, paddingBottom: 6 }}>
                        <span style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                            <span>{e.amount >= 0 ? '➕' : '➖'}</span>{e.reason}
                        </span>
                        <span style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                            <b style={{ color: e.amount >= 0 ? '#7be05a' : '#ff8d8d' }}>{e.amount >= 0 ? '+' : ''}{fa(e.amount)}</b>
                            <span style={{ opacity: .6, fontSize: 12 }}>{e.date}</span>
                        </span>
                    </div>
                )) : <span style={{ opacity: .7 }}>هنوز امتیازی ثبت نشده.</span>}
            </div>

            <Title>🏅 نشان‌ها</Title>
            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                {badges.length ? badges.map((b, i) => (
                    <span key={i} style={{ ...card, padding: '10px 14px', display: 'inline-block' }}>{b.emoji} {b.name}</span>
                )) : <span style={{ opacity: .7 }}>هنوز نشانی نگرفتی</span>}
            </div>

            {discipline.length > 0 && (
                <>
                    <Title>⭐ انضباط</Title>
                    <div style={{ ...card, display: 'grid', gap: 8 }}>
                        {discipline.map((d, i) => (
                            <div key={i} style={{ display: 'flex', justifyContent: 'space-between' }}>
                                <span>{d.type === 'star' ? '🌟 تشویق' : d.type === 'warning' ? '⚠️ تذکر' : '📝 یادداشت'} {d.note}</span>
                                <span style={{ opacity: .6, fontSize: 12 }}>{fa(d.date)}</span>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </ThemedDash>
    );
}

const label = (m) => (m >= 85 ? 'عالی' : m >= 60 ? 'خوب' : 'نیاز به تمرین');
const HUES = ['#3d7bf0', '#a24cf0', '#18a97c', '#f0952e', '#0ea5b7', '#e8505b'];
const barFor = (i) => `linear-gradient(90deg,${HUES[i % 6]},${HUES[(i + 2) % 6]})`;
function Stat({ b, s, c1, c2 }) {
    return <div className="prg-kpi" style={{ padding: '14px 8px', background: `linear-gradient(135deg,${c1},${c2})` }}><b className="v" style={{ display: 'block' }}>{b}</b><span className="l">{s}</span></div>;
}
function Title({ children }) {
    return <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '22px 4px 12px', fontWeight: 900, fontSize: 16, color: '#fff' }}>
        <span style={{ width: 6, height: 20, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))', boxShadow: '0 0 8px var(--acc)' }} />{children}</div>;
}
