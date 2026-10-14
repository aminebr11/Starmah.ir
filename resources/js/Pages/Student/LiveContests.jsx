import { useEffect, useMemo, useState } from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';
import '@/../css/live-contest.css';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const clock = (s) => {
    const d = Math.floor(s / 86400); const h = Math.floor((s % 86400) / 3600); const m = Math.floor((s % 3600) / 60); const x = s % 60;
    return d ? `${fa(d)} روز و ${fa(h)} ساعت` : fa(h ? `${h}:${String(m).padStart(2, '0')}:${String(x).padStart(2, '0')}` : `${m}:${String(x).padStart(2, '0')}`);
};

/** دانش‌آموز: فهرستِ «🏆 مسابقه‌های زنده». */
export default function LiveContests() {
    const { contests = [], history = [] } = usePage().props;
    const t0 = useMemo(() => Date.now(), [contests]); // eslint-disable-line react-hooks/exhaustive-deps
    const [, tick] = useState(0);
    // شمارشِ معکوس زنده + تازه‌سازیِ فهرست (مسابقه سرِ ساعت «الان!» می‌شود)
    useEffect(() => {
        const a = setInterval(() => tick((x) => x + 1), 1000);
        const b = setInterval(() => router.reload({ only: ['contests'], preserveScroll: true }), 15000);
        return () => { clearInterval(a); clearInterval(b); };
    }, []);
    const left = (c) => (c.starts_in == null ? null : Math.max(0, c.starts_in - Math.floor((Date.now() - t0) / 1000)));
    const now = contests.filter((c) => c.phase === 'question' || c.phase === 'reveal');
    const soon = contests.filter((c) => c.phase === 'lobby');
    const past = contests.filter((c) => c.phase === 'end');

    const Card = ({ c }) => (
        <Link href={route('live.play', c.id)} className={`k3-card lt-card lv-scard ${c.phase}`}>
            <div className="lt-card-ic">{c.phase === 'end' ? (c.rank === 1 ? '🥇' : c.rank === 2 ? '🥈' : c.rank === 3 ? '🥉' : '🏁') : '🏆'}</div>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 900, fontSize: 16 }}>{c.title}</div>
                <div style={{ fontSize: 12.5, opacity: .85 }}>{fa(c.questions)} سؤال · {c.when ? `🗓️ ${c.when}` : c.date}</div>
                {c.ends && c.phase !== 'end' && <div className="lv-left" style={{ color: '#ffb3b3' }}>🏁 پایان: {c.ends}</div>}
                {c.phase === 'lobby' && left(c) != null && <div className="lv-left">⏳ {left(c) > 0 ? <>شروع تا <b>{clock(left(c))}</b></> : 'الان شروع می‌شود؛ وارد شو!'}</div>}
                {c.phase === 'end' && c.score != null && <div style={{ fontWeight: 800, marginTop: 3 }}>رتبه‌ی {fa(c.rank)} · {fa(c.score)} امتیاز · {fa(c.correct)} درست</div>}
            </div>
            <span className="lt-status" style={{ background: c.phase === 'end' ? 'rgba(255,255,255,.18)' : c.phase === 'lobby' ? '#ffd23f' : '#e8505b', color: c.phase === 'lobby' ? '#2b1d00' : '#fff' }}>
                {c.phase === 'end' ? 'نتیجه' : c.phase === 'lobby' ? 'ورود ←' : '🔴 الان!'}
            </span>
        </Link>
    );

    return (
        <ThemedDash title="مسابقه‌ی زنده" active="live">
            <div className="k3-card lt-hero lv-shero">
                <div className="lt-hero-ic">🏆</div>
                <div>
                    <div style={{ fontWeight: 900, fontSize: 20 }}>مسابقه‌ی زنده</div>
                    <div style={{ opacity: .92, fontSize: 13.5, lineHeight: 1.9 }}>سؤال روی تخته می‌آید؛ تو همین‌جا رنگ و شکلِ جوابِ درست را بزن. سریع‌تر = امتیازِ بیشتر ⚡ چند جوابِ درستِ پشتِ سرِ هم = 🔥 جایزه!</div>
                </div>
            </div>
            {contests.length === 0 && <div className="k3-card" style={{ marginTop: 14, textAlign: 'center' }}>فعلاً مسابقه‌ای نیست. وقتی معلم مسابقه بسازد، اینجا و در اعلان‌ها خبردار می‌شوی.</div>}
            {now.length > 0 && <><h3 className="lv-sh">🔴 همین الان</h3><div className="lt-list">{now.map((c) => <Card key={c.id} c={c} />)}</div></>}
            {soon.length > 0 && <><h3 className="lv-sh">⏳ به‌زودی</h3><div className="lt-list">{soon.map((c) => <Card key={c.id} c={c} />)}</div></>}
            {past.length > 0 && <><h3 className="lv-sh">🏁 مسابقه‌های قبلی</h3><div className="lt-list">{past.map((c) => <Card key={c.id} c={c} />)}</div></>}
            {history.length > 0 && (
                <>
                    <h3 className="lv-sh">📜 سوابقِ من (هر مسابقه و هر دور)</h3>
                    <div className="lv-hist">
                        {history.map((h) => (
                            <div key={h.id} className="k3-card lv-hrow">
                                <div className="lv-hmedal">{h.rank === 1 ? '🥇' : h.rank === 2 ? '🥈' : h.rank === 3 ? '🥉' : '🏅'}</div>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <b>{h.title}{h.round > 1 ? ` · دورِ ${fa(h.round)}` : ''}</b>
                                    <small>{h.date}</small>
                                    <div className="lv-hstats">
                                        <span>رتبه‌ی {fa(h.rank)} از {fa(h.players)}</span>
                                        <span>⭐ {fa(h.score)}</span>
                                        <span>✅ {fa(h.correct)}/{fa(h.total)}</span>
                                        <span>🎯 {fa(h.accuracy)}٪</span>
                                        {h.best_streak > 1 && <span>🔥 {fa(h.best_streak)}</span>}
                                        {h.xp > 0 && <span>+{fa(h.xp)} امتیاز</span>}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </ThemedDash>
    );
}
