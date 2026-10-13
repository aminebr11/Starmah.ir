import ThemedDash from '@/Layouts/ThemedDash';
import Mascot from '@/Components/Mascot';
import { fa } from '@/online/parts';

/** «🎥 کلاس‌های آنلاینِ من» — کلاسِ زنده، برنامه‌ی بعدی و فیلم‌های ضبط‌شده (دانش‌آموز). */
export default function OnlineClasses({ on, rooms }) {
    const live = rooms.filter((r) => r.status === 'live');
    const next = rooms.filter((r) => r.status === 'scheduled');
    const past = rooms.filter((r) => r.status === 'ended');
    return (
        <ThemedDash title="کلاسِ آنلاین" active="online">
            <div className="oc-kid-hero">
                <Mascot mood={live.length ? 'wave' : 'idle'} size={92} />
                <div>
                    <h2>{live.length ? 'کلاس شروع شده! بدو بیا 🎉' : 'کلاس‌های آنلاینِ من'}</h2>
                    <p>{live.length ? 'معلم منتظرِ توست. هدفون بگذار و وارد شو.' : 'وقتی کلاس شروع شود، این‌جا دکمه‌ی ورود می‌آید و اعلان هم می‌گیری.'}</p>
                </div>
            </div>
            {!on && <div className="panel oc-warn">کلاسِ آنلاین برای مدرسه‌ی تو هنوز فعال نشده است.</div>}
            {live.map((r) => (
                <a key={r.id} href={route('online.room', r.id)} className="oc-live-card">
                    <span className="oc-live-dot" />
                    <div><b>{r.title}</b><small>👩‍🏫 {r.teacher} · {fa(r.online)} نفر در کلاس</small></div>
                    <span className="oc-live-go">ورود 🚀</span>
                </a>
            ))}
            {next.length > 0 && (
                <div className="oc-sec"><h3>🗓️ کلاس‌های بعدی</h3>
                    <div className="oc-cards">{next.map((r) => (
                        <div key={r.id} className="oc-card"><div className="oc-card-h"><b>{r.title}</b></div>
                            <div className="oc-card-m"><span>👩‍🏫 {r.teacher}</span>{r.when && <span>🗓️ {r.when}</span>}</div>
                            <div className="oc-card-a"><a className="btn btn-sm btn-ghost" href={route('online.room', r.id)}>اتاقِ انتظار</a></div>
                        </div>
                    ))}</div>
                </div>
            )}
            {past.length > 0 && (
                <div className="oc-sec"><h3>🎬 فیلمِ کلاس‌های گذشته</h3>
                    <div className="oc-cards">{past.map((r) => (
                        <div key={r.id} className="oc-card"><div className="oc-card-h"><b>{r.title}</b></div>
                            <div className="oc-card-m"><span>👩‍🏫 {r.teacher}</span>{r.started && <span>🗓️ {r.started}</span>}
                                {r.mine && <span>⏱️ {fa(r.mine.minutes)} دقیقه حضور{r.mine.stars ? ` · ⭐ ${fa(r.mine.stars)}` : ''}{r.mine.xp ? ` · ⚡ ${fa(r.mine.xp)}` : ''}</span>}</div>
                            {r.recording && <div className="oc-card-a"><a className="btn btn-sm" href={route('online.recording', r.id)} target="_blank" rel="noreferrer">▶️ دیدنِ فیلم</a></div>}
                        </div>
                    ))}</div>
                </div>
            )}
            {on && rooms.length === 0 && <div className="panel oc-empty-p"><Mascot mood="sleep" size={70} /><p>فعلاً کلاسِ آنلاینی نداری.</p></div>}
        </ThemedDash>
    );
}
