import { useMemo, useState } from 'react';
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

/**
 * کادرِ نوشتن و ارسالِ پیامک — مشترکِ مدیرِ مدرسه و معلم.
 *
 * دو چیز نسبت به نسخه‌ی قبل عوض شده:
 *  ۱) گیرنده‌ها را می‌شود نام‌به‌نام انتخاب کرد (با جست‌وجو)، نه فقط
 *     «کلاس»؛ کنارِ هر نام دیده می‌شود که شماره‌ی خودش و ولی‌اش هست یا نه.
 *  ۲) شمارشِ زنده‌ی گیرنده‌ها و قطعه‌ها پیش از زدنِ دکمه؛ پیش از این
 *     کاربر می‌فرستاد و فقط پیامِ «گیرنده‌ای پیدا نشد» می‌گرفت.
 */
export default function SmsComposer({ sendRoute, classrooms = [], students = [], admin = false, can = { ok: true }, quota = {} }) {
    const f = useForm({ audience: 'parents', classroom_id: '', message: '', phones: '', student_ids: [] });
    const [sent, setSent] = useState(false);
    const [q, setQ] = useState('');

    const list = AUDIENCES.filter((a) => admin || !a.adminOnly);
    const seg = segments(f.data.message);
    const manual = f.data.audience === 'manual';
    const teachers = f.data.audience === 'teachers';
    const toParent = f.data.audience === 'parents' || f.data.audience === 'both';
    const toStudent = f.data.audience === 'students' || f.data.audience === 'both';

    // دانش‌آموزانِ قابلِ انتخاب: محدود به کلاسِ انتخابی و متنِ جست‌وجو
    const pool = useMemo(() => students.filter((s) => {
        if (f.data.classroom_id && !(s.class_ids ?? []).includes(Number(f.data.classroom_id))) return false;
        if (!q.trim()) return true;
        return (s.name + ' ' + (s.classroom ?? '') + ' ' + (s.phone ?? '')).includes(q.trim());
    }), [students, f.data.classroom_id, q]);

    // اگر کسی دستی انتخاب نشده باشد، همه‌ی دانش‌آموزانِ دامنه گیرنده‌اند
    const chosen = f.data.student_ids.length
        ? students.filter((s) => f.data.student_ids.includes(s.id))
        : pool;

    const count = useMemo(() => {
        if (manual) {
            return (f.data.phones || '').split(/[\s,;\n]+/).filter(Boolean).length;
        }
        if (teachers) return null;      // فهرستِ معلم‌ها سمتِ سرور ساخته می‌شود
        const set = new Set();
        chosen.forEach((s) => {
            if (toStudent && s.phone) set.add(s.phone);
            if (toParent) (s.parents ?? []).forEach((p) => set.add(p));
        });
        return set.size;
    }, [chosen, toParent, toStudent, manual, teachers, f.data.phones]);

    const missing = useMemo(() => (manual || teachers ? 0
        : chosen.filter((s) => !((toStudent && s.phone) || (toParent && (s.parents ?? []).length))).length),
    [chosen, toParent, toStudent, manual, teachers]);

    const toggle = (id) => f.setData('student_ids',
        f.data.student_ids.includes(id) ? f.data.student_ids.filter((x) => x !== id) : [...f.data.student_ids, id]);

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

            <div className="field"><label>پیام به چه کسی برود؟</label>
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
                <>
                    <div className="field"><label>کلاس</label>
                        <select className="input" value={f.data.classroom_id}
                            onChange={(e) => { f.setData('classroom_id', e.target.value); f.setData('student_ids', []); }}>
                            <option value="">همه‌ی کلاس‌های من</option>
                            {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name} ({fa(c.students)} دانش‌آموز)</option>)}
                        </select>
                    </div>

                    <div className="field">
                        <label>
                            انتخابِ نام‌به‌نام
                            <span style={{ color: 'var(--muted)', fontWeight: 500, fontSize: 12 }}>
                                {' '}— اگر هیچ‌کس را تیک نزنی، برای همه‌ی فهرستِ زیر فرستاده می‌شود
                            </span>
                        </label>

                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 8 }}>
                            <input className="input" style={{ flex: '1 1 170px', minWidth: 0 }} value={q}
                                onChange={(e) => setQ(e.target.value)} placeholder="جست‌وجوی نام…" />
                            <button type="button" className="btn btn-ghost btn-sm"
                                onClick={() => f.setData('student_ids', pool.map((s) => s.id))}>✅ همه</button>
                            <button type="button" className="btn btn-ghost btn-sm"
                                onClick={() => f.setData('student_ids', [])}>✖️ هیچ‌کدام</button>
                            <button type="button" className="btn btn-ghost btn-sm" title="فقط کسانی که شماره دارند"
                                onClick={() => f.setData('student_ids', pool
                                    .filter((s) => (toStudent && s.phone) || (toParent && (s.parents ?? []).length))
                                    .map((s) => s.id))}>📱 فقط شماره‌دارها</button>
                        </div>

                        <div style={{ maxHeight: 260, overflowY: 'auto', border: '1px solid var(--line)', borderRadius: 12, background: '#fff' }}>
                            {pool.length === 0 && (
                                <div style={{ padding: 14, color: 'var(--muted)', fontSize: 13 }}>دانش‌آموزی در این دامنه نیست.</div>
                            )}
                            {pool.map((s) => {
                                const on = f.data.student_ids.includes(s.id);
                                const has = (toStudent && s.phone) || (toParent && (s.parents ?? []).length);
                                return (
                                    <label key={s.id} style={{
                                        display: 'flex', alignItems: 'center', gap: 10, padding: '9px 12px', cursor: 'pointer',
                                        borderBottom: '1px solid #f1f4f9', background: on ? '#f3f7ff' : 'transparent',
                                    }}>
                                        <input type="checkbox" checked={on} onChange={() => toggle(s.id)} style={{ width: 17, height: 17, flex: 'none' }} />
                                        <span style={{ fontWeight: 700, fontSize: 13.5, flex: 1, minWidth: 0 }}>{s.name}</span>
                                        {s.classroom && <span className="tag tag-info" style={{ fontSize: 11 }}>{s.classroom}</span>}
                                        {s.phone
                                            ? <span className="tag tag-ok" style={{ fontSize: 11 }} title={s.phone}>🎓 شماره دارد</span>
                                            : <span className="tag" style={{ fontSize: 11, background: '#f1f4f9', color: '#8896ad' }}>🎓 بدونِ شماره</span>}
                                        {(s.parents ?? []).length
                                            ? <span className="tag tag-ok" style={{ fontSize: 11 }} title={(s.parents ?? []).join('، ')}>👪 {fa((s.parents ?? []).length)} شماره</span>
                                            : <span className="tag" style={{ fontSize: 11, background: '#fdecee', color: '#b0333f' }}>👪 بدونِ ولی</span>}
                                        {!has && <span title="با این انتخاب، پیامکی برای او نمی‌رود">⚠️</span>}
                                    </label>
                                );
                            })}
                        </div>
                    </div>
                </>
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

            {/* پیش‌نمایشِ گیرنده‌ها — پیش از زدنِ دکمه معلوم است چند پیامک می‌رود */}
            <div style={{
                background: count === 0 ? '#fdecee' : '#f3f8ff', border: `1px solid ${count === 0 ? '#f5b5ba' : '#d7e3fb'}`,
                borderRadius: 12, padding: '10px 13px', fontSize: 12.5, lineHeight: 1.9, marginBottom: 12,
            }}>
                {teachers
                    ? '👩‍🏫 به همه‌ی معلم‌هایی که شماره‌ی ثبت‌شده دارند فرستاده می‌شود.'
                    : count === 0
                        ? <b style={{ color: '#b0333f' }}>هیچ گیرنده‌ای با شماره‌ی معتبر انتخاب نشده — با این تنظیم پیامکی ارسال نمی‌شود.</b>
                        : <>
                            📨 <b>{fa(count)} شماره</b> گیرنده‌ی این پیام است
                            {seg > 1 && <> · مجموعاً <b>{fa(count * seg)} قطعه</b></>}
                            {missing > 0 && <span style={{ color: '#b9831a' }}> · {fa(missing)} دانش‌آموز شماره‌ی لازم را ندارند</span>}
                        </>}
            </div>

            <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
                <button type="submit" disabled={f.processing || !f.data.message.trim() || count === 0} className="btn">
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
