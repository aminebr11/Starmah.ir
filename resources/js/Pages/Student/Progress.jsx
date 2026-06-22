import { usePage } from '@inertiajs/react';
import Themed, { studentNav } from '@/Layouts/Themed';
import { SectionTitle } from '@/Pages/Student/Dashboard';
import { fa, ui } from '@/theme';

/** کارنامه: تسلط مهارت، نمرات اخیر، انضباط، نشان‌ها. */
export default function Progress() {
    const { stats, mastery, recent, discipline, badges } = usePage().props;

    return (
        <Themed title="کارنامه" nav={studentNav('progress')} active="progress">
            <div style={{ textAlign: 'center' }}>
                <div style={ui.h}>کارنامه‌ی من 📈</div>
                <p style={ui.muted}>دفتر نمره، مهارت‌ها و انضباط — یک‌جا</p>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10, marginTop: 14 }}>
                <Stat b={`${fa(stats.avg)}٪`} s="میانگین تسلط" />
                <Stat b={fa(stats.badges)} s="نشان" />
                <Stat b={`⭐${fa(stats.stars)}`} s="ستاره‌ی انضباط" />
            </div>

            <SectionTitle>تسلط بر مهارت‌ها</SectionTitle>
            <div style={{ ...ui.card, display: 'grid', gap: 14 }}>
                {mastery.length ? mastery.map((m, i) => (
                    <div key={i}>
                        <div style={ui.row('space-between')}>
                            <b style={{ fontSize: 13 }}>{m.skill}</b>
                            <span style={ui.muted}>{fa(m.mastery)}٪ • {label(m.mastery)}</span>
                        </div>
                        <div style={{ ...ui.bar, marginTop: 6 }}><div style={ui.barFill(m.mastery)} /></div>
                    </div>
                )) : <div style={ui.muted}>هنوز تمرینی ثبت نشده. یک تمرین انجام بده!</div>}
            </div>

            <SectionTitle>نمرات اخیر</SectionTitle>
            <div style={{ ...ui.card, display: 'grid', gap: 10 }}>
                {recent.length ? recent.map((r, i) => (
                    <div key={i} style={ui.row('space-between')}>
                        <div style={ui.row()}>
                            <span style={{ ...ui.pill, ...ui.pillAcc }}>{fa(r.score)}/{fa(r.max)}</span>
                            <b style={{ fontSize: 13 }}>{r.skill}</b>
                        </div>
                        <span style={ui.muted}>{fa(r.date)}</span>
                    </div>
                )) : <div style={ui.muted}>—</div>}
            </div>

            <SectionTitle>نشان‌ها 🏅</SectionTitle>
            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                {badges.length ? badges.map((b, i) => (
                    <span key={i} style={{ ...ui.pill, padding: '10px 12px' }}>{b.emoji} {b.name}</span>
                )) : <span style={ui.muted}>هنوز نشانی نگرفتی</span>}
            </div>

            {discipline.length > 0 && (
                <>
                    <SectionTitle>انضباط</SectionTitle>
                    <div style={{ ...ui.card, display: 'grid', gap: 8 }}>
                        {discipline.map((d, i) => (
                            <div key={i} style={ui.row('space-between')}>
                                <span>{d.type === 'star' ? '🌟 تشویق' : d.type === 'warning' ? '⚠️ تذکر' : '📝 یادداشت'} {d.note}</span>
                                <span style={ui.muted}>{fa(d.date)}</span>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </Themed>
    );
}

const label = (m) => (m >= 85 ? 'عالی' : m >= 60 ? 'خوب' : 'نیاز به تمرین');
function Stat({ b, s }) {
    return (
        <div style={{ ...ui.card, padding: '12px 8px', textAlign: 'center', borderRadius: 18 }}>
            <b style={{ display: 'block', fontSize: 20, color: 'var(--acc)' }}>{b}</b>
            <span style={{ fontSize: 11, color: 'rgba(255,255,255,.6)' }}>{s}</span>
        </div>
    );
}
