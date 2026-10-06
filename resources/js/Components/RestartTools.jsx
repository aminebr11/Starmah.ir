import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * «راه‌اندازیِ مجدد»ِ آزمون یا بازی برای یک، چند یا همه‌ی دانش‌آموزان.
 * نتیجه و امتیازِ قبلی پاک می‌شود، مورد (اگر بسته بود) باز می‌شود و دانش‌آموزان
 * اعلانِ «دوباره فعال شد» می‌گیرند.
 */
export function useRestart(routeName, id, kind) {
    const [busy, setBusy] = useState(false);
    const [deadline, setDeadline] = useState('');
    const noun = kind === 'game' ? 'بازی' : 'آزمون';
    const restart = (ids, label, onDone) => {
        if (busy) return;
        const ok = confirm(
            `🔁 راه‌اندازیِ مجددِ ${noun} — ${label}\n\n`
            + `• نتیجه‌ها و پاسخ‌های قبلی پاک می‌شوند.\n`
            + `• امتیازی که از این ${noun} گرفته‌اند هم از امتیازِ کلشان کم می‌شود.\n`
            + `• ${noun} دوباره برایشان باز می‌شود و اعلانِ «دوباره فعال شد» می‌گیرند.\n\n`
            + 'این کار قابلِ بازگشت نیست. ادامه می‌دهید؟');
        if (!ok) return;
        setBusy(true);
        const payload = { ...(ids ? { student_ids: ids } : {}), ...(deadline ? { [kind === 'game' ? 'close_at' : 'closes_at']: deadline } : {}) };
        router.post(route(routeName, id), payload, {
            preserveScroll: true,
            onFinish: () => { setBusy(false); onDone && onDone(); },
        });
    };
    return { busy, deadline, setDeadline, restart, noun };
}

/** نوارِ توضیح + مهلتِ تازه + دکمه‌های گروهی. */
export function RestartBar({ r, closed, selected = [], onSelectedDone, smart = false }) {
    // نتیجه‌ی کار (پیامِ سرور) همین‌جا دیده می‌شود؛ این صفحه‌ها بنرِ عمومیِ پیام ندارند
    const { flash } = usePage().props;
    const [msg, setMsg] = useState(null);
    useEffect(() => {
        const m = flash?.flash;
        if (m) setMsg(typeof m === 'string' ? m : m.message);
    }, [flash]);
    const btn = smart ? 'smart-btn sm' : 'btn btn-sm';
    const ghost = smart ? 'smart-btn ghost sm' : 'btn btn-ghost btn-sm';
    return (
        <div className="rs-bar no-print">
            {msg && <div className="rs-msg" role="status">{msg}<button type="button" onClick={() => setMsg(null)} aria-label="بستن">✕</button></div>}
            <div className="rs-note">
                🔁 <b>راه‌اندازیِ مجدد</b> نتیجه و <b>امتیازِ قبلیِ</b> این {r.noun} را برای دانش‌آموزانِ انتخاب‌شده پاک می‌کند تا
                با امتیازِ تازه دوباره شرکت کنند؛ به آن‌ها <b>اعلان</b> هم می‌رود.
                {closed && <span className="rs-warn"> این {r.noun} الان بسته است یا مهلتش گذشته؛ با راه‌اندازیِ مجدد باز می‌شود (بدونِ مهلتِ تازه، ۷ روز مهلت می‌گیرد).</span>}
            </div>
            <div className="rs-row">
                <div className="rs-dl">
                    <label>⏰ مهلتِ تازه (اختیاری)</label>
                    <JalaliDatePicker withTime value={r.deadline} onChange={r.setDeadline} placeholder={closed ? '۷ روزِ دیگر' : 'بدونِ تغییر'} />
                    {r.deadline && <button type="button" className={ghost} onClick={() => r.setDeadline('')}>✕</button>}
                </div>
                <button type="button" className={btn} disabled={r.busy || !selected.length}
                    onClick={() => r.restart(selected, `${fa(selected.length)} دانش‌آموزِ انتخاب‌شده`, onSelectedDone)}>
                    🔁 برای انتخاب‌شده‌ها ({fa(selected.length)})
                </button>
                <button type="button" className={ghost} style={{ color: '#c0392b' }} disabled={r.busy}
                    onClick={() => r.restart(null, 'همه‌ی دانش‌آموزان', onSelectedDone)}>
                    🔁 برای همه
                </button>
            </div>
        </div>
    );
}
