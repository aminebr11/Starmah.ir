import ThemedDash from '@/Layouts/ThemedDash';
import Mascot from '@/Components/Mascot';
import { fa, RecordingsList } from '@/online/parts';

/** «🎥 کلاس‌های آنلاینِ من» — کلاسِ زنده، برنامه‌ی بعدی و فیلم‌های ضبط‌شده (دانش‌آموز). */
export default function OnlineClasses({ on, rooms, recordings = [] }) {
    const live = rooms.filter((r) => r.status === 'live');
    const next = rooms.filter((r) => r.status === 'scheduled');
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
            <RecordingsList recordings={recordings} title="🎬 فیلمِ کلاس‌های گذشته" />
            {on && rooms.length === 0 && recordings.length === 0 && <div className="panel oc-empty-p"><Mascot mood="sleep" size={70} /><p>فعلاً کلاسِ آنلاینی نداری.</p></div>}
        </ThemedDash>
    );
}
