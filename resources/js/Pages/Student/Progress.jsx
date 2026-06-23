import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 18, color: '#fff' };

/** کارنامه‌ی من — تسلط، نمرات، امتیازهای گرفته/ازدست‌رفته، انضباط، نشان‌ها. */
export default function Progress() {
    const { stats = {}, mastery = [], recent = [], discipline = [], badges = [] } = usePage().props;

    return (
        <ThemedDash title="کارنامه‌ی من" active="progress">
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 12 }} className="prog-stats">
                <Stat b={fa(stats.xp ?? 0)} s="امتیاز کل" />
                <Stat b={`${fa(stats.avg ?? 0)}٪`} s="میانگین تسلط" />
                <Stat b={`⭐${fa(stats.stars ?? 0)}`} s="ستاره انضباط" />
                <Stat b={fa(stats.badges ?? 0)} s="نشان" />
            </div>

            <Title>📊 تسلط بر مهارت‌ها</Title>
            <div style={{ ...card, display: 'grid', gap: 14 }}>
                {mastery.length ? mastery.map((m, i) => (
                    <div key={i}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
                            <b style={{ fontSize: 13 }}>{m.skill}</b>
                            <span style={{ opacity: .7, fontSize: 12 }}>{fa(m.mastery)}٪ · {label(m.mastery)}</span>
                        </div>
                        <div style={{ height: 9, borderRadius: 8, background: 'rgba(255,255,255,.12)', overflow: 'hidden' }}>
                            <div style={{ width: `${m.mastery}%`, height: '100%', background: 'linear-gradient(90deg,var(--p1),var(--acc))' }} />
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
function Stat({ b, s }) {
    return <div style={{ ...card, padding: '12px 8px', textAlign: 'center' }}><b style={{ display: 'block', fontSize: 22, color: 'var(--acc)' }}>{b}</b><span style={{ fontSize: 11, opacity: .7 }}>{s}</span></div>;
}
function Title({ children }) {
    return <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '22px 4px 12px', fontWeight: 800, fontSize: 16, color: '#fff' }}>
        <span style={{ width: 5, height: 18, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))' }} />{children}</div>;
}
