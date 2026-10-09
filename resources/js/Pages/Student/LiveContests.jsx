import { usePage, Link } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** دانش‌آموز: فهرستِ «🏆 مسابقه‌های زنده». */
export default function LiveContests() {
    const { contests = [] } = usePage().props;
    const now = contests.filter((c) => c.phase === 'question' || c.phase === 'reveal');
    const soon = contests.filter((c) => c.phase === 'lobby');
    const past = contests.filter((c) => c.phase === 'end');

    const Card = ({ c }) => (
        <Link href={route('live.play', c.id)} className={`k3-card lt-card lv-scard ${c.phase}`}>
            <div className="lt-card-ic">{c.phase === 'end' ? (c.rank === 1 ? '🥇' : c.rank === 2 ? '🥈' : c.rank === 3 ? '🥉' : '🏁') : '🏆'}</div>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 900, fontSize: 16 }}>{c.title}</div>
                <div style={{ fontSize: 12.5, opacity: .85 }}>{fa(c.questions)} سؤال · {c.when ? `🗓️ ${c.when}` : c.date}</div>
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
        </ThemedDash>
    );
}
