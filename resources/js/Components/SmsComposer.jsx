import { useState } from 'react';
import { useForm } from '@inertiajs/react';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** پیامکِ فارسی ۷۰ نویسه در هر قطعه است، نه ۱۶۰ — همان فرمولِ سرور. */
const segments = (t) => {
    const ascii = /^[\x00-\x7F]*$/.test(t);
    return Math.max(1, Math.ceil((t || '').length / (ascii ? 160 : 70)));
};

const AUDIENCES = [
    { v: 'parents', t: '👪 اولیا', d: 'شماره‌ی ولیِ دانش‌آموزان' },
    { v: 'students', t: '🎓 دانش‌آموزان', d: 'شماره‌ی خودِ دانش‌آموز' },
    { v: 'both', t: '👪+🎓 هر دو', d: 'ولی و دانش‌آموز' },
    { v: 'teachers', t: '👩‍🏫 معلم‌ها', d: 'همه‌ی معلم‌های مدرسه', adminOnly: true },
    { v: 'manual', t: '✍️ شماره‌های دستی', d: 'فهرستی که خودت می‌نویسی' },
];

/** کادرِ نوشتن و ارسالِ پیامک — مشترکِ مدیرِ مدرسه و معلم. */
export default function SmsComposer({ sendRoute, classrooms = [], admin = false, can = { ok: true }, quota = {} }) {
    const f = useForm({ audience: 'parents', classroom_id: '', message: '', phones: '', student_ids: [] });
    const [sent, setSent] = useState(false);

    const list = AUDIENCES.filter((a) => admin || !a.adminOnly);
    const seg = segments(f.data.message);
    const manual = f.data.audience === 'manual';
    const teachers = f.data.audience === 'teachers';

    const submit = (e) => {
        e.preventDefault();
        f.post(sendRoute, {
            preserveScroll: true,
            onSuccess: () => { f.setData('message', ''); setSent(true); setTimeout(() => setSent(false), 4000); },
        });
    };

    if (!can.ok) {
        return (
            <div className="panel" style={{ borderColor: '#f5b5ba', background: '#fdecee' }}>
                <h3 style={{ marginTop: 0 }}>📩 ارسالِ پیامک</h3>
                <p style={{ color: '#b0333f', margin: 0, fontWeight: 700 }}>{can.reason}</p>
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="panel">
            <h3 style={{ marginTop: 0 }}>📩 ارسالِ پیامک</h3>

            <div className="field"><label>گیرندگان</label>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {list.map((a) => (
                        <button type="button" key={a.v} onClick={() => f.setData('audience', a.v)} title={a.d}
                            className={`tag ${f.data.audience === a.v ? 'tag-warn' : 'tag-info'}`}
                            style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 14px', fontSize: 13 }}>
                            {a.t}
                        </button>
                    ))}
                </div>
                <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 6 }}>
                    {list.find((a) => a.v === f.data.audience)?.d}
                </div>
            </div>

            {!manual && !teachers && (
                <div className="field"><label>کلاس</label>
                    <select className="input" value={f.data.classroom_id} onChange={(e) => f.setData('classroom_id', e.target.value)}>
                        <option value="">همه‌ی کلاس‌های من</option>
                        {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name} ({fa(c.students)} دانش‌آموز)</option>)}
                    </select>
                </div>
            )}

            {manual && (
                <div className="field"><label>شماره‌ها (با فاصله، کاما یا خطِ تازه جدا کن)</label>
                    <textarea className="input" rows="3" dir="ltr" value={f.data.phones}
                        onChange={(e) => f.setData('phones', e.target.value)} placeholder="09121234567, 09351234567" />
                </div>
            )}

            <div className="field"><label>متنِ پیام</label>
                <textarea className="input" rows="4" value={f.data.message} maxLength={600}
                    onChange={(e) => f.setData('message', e.target.value)} placeholder="سلام، جلسه‌ی اولیا و مربیان فردا ساعت ۱۰ برگزار می‌شود." />
                <div style={{ display: 'flex', gap: 10, fontSize: 12, color: 'var(--muted)', marginTop: 5, flexWrap: 'wrap' }}>
                    <span>{fa(f.data.message.length)} نویسه</span>
                    <span>·</span>
                    <span>{fa(seg)} قطعه پیامک برای هر گیرنده</span>
                    {seg > 1 && <span style={{ color: '#b9831a' }}>⚠️ هزینه {fa(seg)} برابر می‌شود</span>}
                </div>
                {f.errors.message && <div style={{ color: '#e8505b', fontSize: 12.5, marginTop: 6 }}>{f.errors.message}</div>}
            </div>

            <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
                <button type="submit" disabled={f.processing || !f.data.message.trim()} className="btn">
                    {f.processing ? 'در حال ارسال…' : '📤 ارسالِ پیامک'}
                </button>
                {sent && <span style={{ color: '#1a8a52', fontWeight: 700, fontSize: 13 }}>✅ فرستاده شد</span>}
                <span style={{ marginInlineStart: 'auto', fontSize: 12, color: 'var(--muted)' }}>
                    {quota.my_left != null
                        ? `سهمیه‌ی شما: ${fa(quota.my_left)} قطعه باقی مانده`
                        : quota.school_left != null
                            ? `سهمیه‌ی مدرسه: ${fa(quota.school_left)} قطعه باقی مانده`
                            : 'بدونِ سقفِ سهمیه'}
                </span>
            </div>
            <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 8 }}>
                ⚠️ پیامک واقعاً ارسال می‌شود و از اعتبارِ مدرسه کم می‌کند.
            </div>
        </form>
    );
}

export { segments };
