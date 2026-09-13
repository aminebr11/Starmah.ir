import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * دسترسیِ پیامکِ هر دانش‌آموز — تنظیمِ معلمِ همان کلاس (و مدیرِ مدرسه).
 *
 * چرا اینجا و نه فقط در پنلِ مدیر: معلم است که می‌داند خانواده‌ی کدام
 * دانش‌آموز پیامک می‌خواهد و کدام شماره فعال است. مدیر «سقف» را تعیین
 * می‌کند (کدام رویدادها اصلاً پیامک شوند) و معلم درونِ همان سقف برای هر
 * دانش‌آموز تصمیم می‌گیرد.
 */
export default function StudentSmsAccess({ students = [], events = {}, schoolEvents = {}, saveRoute }) {
    const [q, setQ] = useState('');
    const [openId, setOpenId] = useState(null);
    const [draft, setDraft] = useState(null);
    const [picked, setPicked] = useState([]);
    const [busy, setBusy] = useState(false);

    // رویدادهایی که مدرسه اصلاً روشن کرده — بقیه حتی با تیکِ معلم نمی‌روند
    const allowed = useMemo(() => Object.keys(events)
        .filter((k) => schoolEvents?.[k]?.parent || schoolEvents?.[k]?.student), [events, schoolEvents]);

    const list = useMemo(() => students.filter((s) => !q.trim()
        || (s.name + ' ' + (s.classroom ?? '')).includes(q.trim())), [students, q]);

    const edit = (s) => {
        setOpenId(openId === s.id ? null : s.id);
        setDraft({
            enabled: s.sms.enabled, to_parent: s.sms.to_parent, to_student: s.sms.to_student,
            events: s.sms.events, phone: s.sms.phone ?? '', note: s.sms.note ?? '',
        });
    };

    const save = (s, ids = null) => {
        if (!draft) return;
        setBusy(true);
        const payload = {
            enabled: draft.enabled, to_parent: draft.to_parent, to_student: draft.to_student,
            phone: draft.phone, note: draft.note,
        };
        // نبودِ کلیدِ events یعنی «هرچه مدرسه گفت»؛ آرایه یعنی فهرستِ دقیق
        if (draft.events !== null) payload.events = draft.events;
        if (ids) payload.ids = ids;
        router.post(saveRoute(s.id), payload, {
            preserveScroll: true,
            onFinish: () => { setBusy(false); setOpenId(null); setPicked([]); },
        });
    };

    const toggleEvent = (k) => setDraft((d) => {
        const cur = d.events ?? allowed;
        return { ...d, events: cur.includes(k) ? cur.filter((x) => x !== k) : [...cur, k] };
    });

    const Pill = ({ on, children, ...rest }) => (
        <button type="button" {...rest}
            className={`tag ${on ? 'tag-ok' : ''}`}
            style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', fontSize: 12, padding: '7px 12px',
                background: on ? undefined : '#eef1f7', color: on ? undefined : '#8896ad' }}>
            {children}
        </button>
    );

    return (
        <div className="panel">
            <h3 style={{ marginTop: 0 }}>🎚️ دسترسیِ پیامکِ دانش‌آموزان</h3>
            <p style={{ color: 'var(--muted)', fontSize: 12.5, lineHeight: 2, marginTop: 0 }}>
                این تنظیم فقط روی <b>پیامک‌های خودکارِ سامانه</b> (نمره، غیبت، تکلیف…) اثر دارد؛
                پیامکِ دستی‌ای که خودتان می‌نویسید همیشه فرستاده می‌شود.
                {allowed.length === 0
                    ? <span style={{ color: '#b0333f' }}> ⚠️ مدیرِ مدرسه هنوز هیچ رویدادی را برای پیامک روشن نکرده، پس فعلاً پیامکِ خودکاری ارسال نمی‌شود.</span>
                    : <> رویدادهای روشنِ مدرسه: <b>{allowed.map((k) => events[k].label).join('، ')}</b>.</>}
            </p>

            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 10 }}>
                <input className="input" style={{ flex: '1 1 180px', minWidth: 0 }} value={q}
                    onChange={(e) => setQ(e.target.value)} placeholder="جست‌وجوی دانش‌آموز…" />
                <span style={{ alignSelf: 'center', color: 'var(--muted)', fontSize: 12 }}>
                    {fa(list.length)} دانش‌آموز
                </span>
            </div>

            <div style={{ border: '1px solid var(--line)', borderRadius: 13, overflow: 'hidden', background: '#fff' }}>
                {list.length === 0 && <div style={{ padding: 14, color: 'var(--muted)', fontSize: 13 }}>دانش‌آموزی پیدا نشد.</div>}
                {list.map((s) => (
                    <div key={s.id} style={{ borderBottom: '1px solid #f1f4f9' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '10px 12px', flexWrap: 'wrap' }}>
                            <input type="checkbox" checked={picked.includes(s.id)} title="انتخاب برای تنظیمِ گروهی"
                                onChange={() => setPicked((p) => p.includes(s.id) ? p.filter((x) => x !== s.id) : [...p, s.id])}
                                style={{ width: 16, height: 16, flex: 'none' }} />
                            <span style={{ fontWeight: 700, fontSize: 13.5, flex: '1 1 120px', minWidth: 0 }}>{s.name}</span>
                            {s.classroom && <span className="tag tag-info" style={{ fontSize: 11 }}>{s.classroom}</span>}

                            {s.sms.enabled
                                ? <>
                                    {s.sms.to_parent && <span className="tag tag-ok" style={{ fontSize: 11 }}>👪 ولی</span>}
                                    {s.sms.to_student && <span className="tag tag-ok" style={{ fontSize: 11 }}>🎓 خودش</span>}
                                    {!s.sms.to_parent && !s.sms.to_student
                                        && <span className="tag tag-warn" style={{ fontSize: 11 }}>بدونِ گیرنده</span>}
                                </>
                                : <span className="tag" style={{ fontSize: 11, background: '#fdecee', color: '#b0333f' }}>🔕 خاموش</span>}

                            {(s.parents ?? []).length === 0 && s.sms.to_parent
                                && <span className="tag" style={{ fontSize: 11, background: '#fff3d6', color: '#b9831a' }}>بدونِ شماره‌ی ولی</span>}
                            {Array.isArray(s.sms.events)
                                && <span className="tag tag-info" style={{ fontSize: 11 }}>{fa(s.sms.events.length)} رویدادِ خاص</span>}

                            <button type="button" className="btn btn-ghost btn-sm" onClick={() => edit(s)}>
                                {openId === s.id ? 'بستن' : '⚙️ تنظیم'}
                            </button>
                        </div>

                        {openId === s.id && draft && (
                            <div style={{ padding: '4px 12px 14px', background: '#f8fafd' }}>
                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 10 }}>
                                    <Pill on={draft.enabled} onClick={() => setDraft({ ...draft, enabled: !draft.enabled })}>
                                        {draft.enabled ? '🔔 پیامکِ خودکار روشن' : '🔕 پیامکِ خودکار خاموش'}
                                    </Pill>
                                    <Pill on={draft.to_parent} onClick={() => setDraft({ ...draft, to_parent: !draft.to_parent })}>👪 به ولی</Pill>
                                    <Pill on={draft.to_student} onClick={() => setDraft({ ...draft, to_student: !draft.to_student })}>🎓 به خودِ دانش‌آموز</Pill>
                                </div>

                                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginBottom: 10 }}>
                                    <div style={{ flex: '1 1 180px', minWidth: 0 }}>
                                        <label style={{ fontSize: 12, fontWeight: 700, display: 'block', marginBottom: 4 }}>شماره‌ی پیامکِ خانواده (اختیاری)</label>
                                        <input className="input" dir="ltr" value={draft.phone} maxLength={20}
                                            onChange={(e) => setDraft({ ...draft, phone: e.target.value })}
                                            placeholder={(s.parents ?? [])[0] ?? '09xxxxxxxxx'} />
                                        <div style={{ color: 'var(--muted)', fontSize: 11.5, marginTop: 4 }}>
                                            اگر پر شود، پیامکِ ولی فقط به همین شماره می‌رود (جایگزینِ شماره‌های دیگر).
                                        </div>
                                    </div>
                                    <div style={{ flex: '1 1 180px', minWidth: 0 }}>
                                        <label style={{ fontSize: 12, fontWeight: 700, display: 'block', marginBottom: 4 }}>یادداشتِ داخلی (اختیاری)</label>
                                        <input className="input" value={draft.note} maxLength={200}
                                            onChange={(e) => setDraft({ ...draft, note: e.target.value })}
                                            placeholder="مثلاً: فقط مادر پیامک می‌خواهد" />
                                    </div>
                                </div>

                                <div style={{ marginBottom: 10 }}>
                                    <label style={{ fontSize: 12, fontWeight: 700, display: 'block', marginBottom: 6 }}>
                                        کدام رویدادها برای این دانش‌آموز پیامک شود؟
                                    </label>
                                    <div style={{ display: 'flex', gap: 7, flexWrap: 'wrap' }}>
                                        <Pill on={draft.events === null} onClick={() => setDraft({ ...draft, events: null })}>
                                            ✅ هرچه مدرسه روشن کرده
                                        </Pill>
                                        {draft.events !== null && allowed.map((k) => (
                                            <Pill key={k} on={(draft.events ?? []).includes(k)} onClick={() => toggleEvent(k)}>
                                                {events[k].label}
                                            </Pill>
                                        ))}
                                        {draft.events === null && allowed.length > 0 && (
                                            <Pill on={false} onClick={() => setDraft({ ...draft, events: allowed })}>
                                                ✏️ انتخابِ دستی
                                            </Pill>
                                        )}
                                    </div>
                                </div>

                                <div style={{ display: 'flex', gap: 9, flexWrap: 'wrap' }}>
                                    <button type="button" className="btn btn-sm" disabled={busy} onClick={() => save(s)}>
                                        {busy ? 'در حال ذخیره…' : '💾 ذخیره'}
                                    </button>
                                    {picked.length > 1 && (
                                        <button type="button" className="btn btn-ghost btn-sm" disabled={busy}
                                            onClick={() => save(s, picked)}>
                                            📋 همین تنظیم برای {fa(picked.length)} دانش‌آموزِ انتخاب‌شده
                                        </button>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {picked.length > 1 && openId === null && (
                <div style={{ marginTop: 10, color: 'var(--muted)', fontSize: 12.5 }}>
                    {fa(picked.length)} دانش‌آموز انتخاب شده — روی «⚙️ تنظیم» یکی از آن‌ها بزنید تا همان تنظیم را روی همه بگذارید.
                </div>
            )}
        </div>
    );
}
