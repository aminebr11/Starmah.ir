import { useState } from 'react';
import { usePage, useForm } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
import SmsComposer from '@/Components/SmsComposer';
import { LogTable } from '@/Pages/Admin/Sms';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** سامانه‌ی پیامکِ مدرسه — ارسال، اعلان‌های خودکار، دسترسیِ معلم‌ها، سابقه. */
export default function Sms() {
    const { can = {}, classrooms = [], quota = {}, log = [], teachers = [], events = {}, eventConfig = {}, sender, assistant = {}, flash, errors = {} } = usePage().props;
    const [tab, setTab] = useState('send');

    const banner = typeof flash?.flash === 'string' ? flash.flash : flash?.flash?.message;

    const TABS = [
        { v: 'send', t: '📤 ارسالِ پیامک' },
        { v: 'events', t: '🔔 اعلان‌های خودکار' },
        { v: 'teachers', t: `👩‍🏫 دسترسیِ معلم‌ها (${fa(teachers.length)})` },
        { v: 'assistant', t: '🤖 دستیارِ هوشمند' },
        { v: 'log', t: `📜 سابقه (${fa(log.length)})` },
    ];

    return (
        <DashLayout title="سامانه‌ی پیامک" roleLabel="مدیر مدرسه" menu={schoolMenu} active="sms">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}
            {errors.message && <div className="panel" style={{ borderColor: '#f5b5ba', background: '#fdecee' }}><b style={{ color: '#b0333f' }}>{errors.message}</b></div>}

            <QuotaCards quota={quota} can={can} />

            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', margin: '14px 0' }}>
                {TABS.map((t) => (
                    <button key={t.v} onClick={() => setTab(t.v)} className={`tag ${tab === t.v ? 'tag-warn' : 'tag-info'}`}
                        style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 16px', fontSize: 13 }}>{t.t}</button>
                ))}
            </div>

            {tab === 'send' && <SmsComposer sendRoute={route('school.sms.send')} classrooms={classrooms} admin can={can} quota={quota} />}
            {tab === 'events' && <EventMatrix events={events} config={eventConfig} sender={sender} />}
            {tab === 'teachers' && <Teachers teachers={teachers} />}
            {tab === 'assistant' && <AssistantAccess a={assistant} />}
            {tab === 'log' && <LogTable log={log} />}
        </DashLayout>
    );
}

export function QuotaCards({ quota, can }) {
    const pct = quota.school_quota ? Math.min(100, Math.round((quota.school_used / quota.school_quota) * 100)) : 0;
    return (
        <>
            {!can.ok && (
                <div className="panel" style={{ borderColor: '#f5b5ba', background: '#fdecee' }}>
                    <b style={{ color: '#b0333f' }}>⛔ {can.reason}</b>
                </div>
            )}
            <div className="dash-cards">
                <div className="dcard">
                    <div style={{ fontSize: 26 }}>🏫</div>
                    <div style={{ fontWeight: 800, marginTop: 6 }}>
                        {quota.school_quota != null ? `${fa(quota.school_used)} از ${fa(quota.school_quota)}` : `${fa(quota.school_used)} قطعه`}
                    </div>
                    <div style={{ color: 'var(--muted)', fontSize: 12 }}>مصرفِ مدرسه در {fa(quota.window ?? 30)} روز</div>
                    {quota.school_quota != null && (
                        <div style={{ height: 6, background: '#eef2f8', borderRadius: 6, overflow: 'hidden', marginTop: 8 }}>
                            <div style={{ height: '100%', width: `${pct}%`, background: pct > 85 ? '#e8505b' : 'var(--gold)' }} />
                        </div>
                    )}
                </div>
                <div className="dcard">
                    <div style={{ fontSize: 26 }}>🎯</div>
                    <div style={{ fontWeight: 800, marginTop: 6 }}>
                        {quota.school_left != null ? `${fa(quota.school_left)} قطعه` : 'بدونِ سقف'}
                    </div>
                    <div style={{ color: 'var(--muted)', fontSize: 12 }}>باقی‌مانده‌ی مدرسه</div>
                </div>
                <div className="dcard">
                    <div style={{ fontSize: 26 }}>👤</div>
                    <div style={{ fontWeight: 800, marginTop: 6 }}>
                        {quota.my_left != null ? `${fa(quota.my_left)} قطعه` : `${fa(quota.my_used)} ارسال‌شده`}
                    </div>
                    <div style={{ color: 'var(--muted)', fontSize: 12 }}>{quota.my_left != null ? 'سهمیه‌ی شخصیِ شما' : 'ارسالِ شخصیِ شما'}</div>
                </div>
            </div>
        </>
    );
}

/** دسترسیِ دستیارِ هوشمند برای معلم‌ها و دانش‌آموزان. */
function AssistantAccess({ a }) {
    const f = useForm({ students: a.students !== false, teachers: a.teachers !== false, ai: a.ai !== false });
    const save = (e) => { e.preventDefault(); f.post(route('school.assistant.access'), { preserveScroll: true }); };

    const Row = ({ k, icon, title, hint }) => (
        <label style={{ display: 'flex', gap: 10, alignItems: 'flex-start', padding: '12px 14px', border: '1px solid var(--line)',
            borderRadius: 14, cursor: 'pointer', background: f.data[k] ? '#f6fffa' : '#fff' }}>
            <input type="checkbox" checked={f.data[k]} onChange={(e) => f.setData(k, e.target.checked)} style={{ width: 20, height: 20, marginTop: 2 }} />
            <div>
                <div style={{ fontWeight: 800 }}>{icon} {title}</div>
                <div style={{ fontSize: 12.5, color: 'var(--muted)', lineHeight: 1.9, marginTop: 2 }}>{hint}</div>
            </div>
        </label>
    );

    return (
        <form onSubmit={save} className="panel">
            <h3 style={{ marginTop: 0 }}>🤖 دسترسیِ دستیارِ هوشمند</h3>
            <p style={{ color: 'var(--muted)', fontSize: 13, lineHeight: 1.95 }}>
                دستیار برای هر نقش شخصیتِ خودش را دارد: برای دانش‌آموز یک «معلمِ راهنما» که رتبه، درس‌های ضعیف و
                برنامه‌ی امروزش را می‌گوید؛ برای معلم یک «دستیارِ آموزشی» که وضعیتِ کلاس و پیشنهادِ مأموریت می‌دهد؛
                برای شما یک «تحلیلگرِ مدرسه».
            </p>

            <div style={{ display: 'grid', gap: 10, marginTop: 12 }}>
                <Row k="students" icon="🎓" title="دستیار برای دانش‌آموزان باز باشد"
                    hint="رتبه، گزارشِ تحلیلی، درس‌های ضعیف و پیشنهادِ گامِ بعدی — همه از داده‌ی واقعیِ خودِ دانش‌آموز." />
                <Row k="teachers" icon="👩‍🏫" title="دستیار برای معلم‌ها باز باشد"
                    hint="میانگینِ کلاس، ضعیف‌ترین درس‌ها و پیشنهادِ ساختِ مأموریت و محتوا." />
                <Row k="ai" icon="✨" title="استفاده از هوش مصنوعی مجاز باشد"
                    hint={a.ai_ready
                        ? 'خاموش کنید تا فقط پاسخ‌های آماده‌ی سامانه داده شود (بی‌هزینه). پاسخ‌ها همچنان از داده‌ی واقعی ساخته می‌شوند.'
                        : '⚠️ ادمینِ کل هنوز کلیدِ هوش مصنوعی را تنظیم نکرده است؛ فعلاً در هر حالت از پاسخ‌های آماده استفاده می‌شود.'} />
            </div>

            <button type="submit" disabled={f.processing} className="btn" style={{ marginTop: 14 }}>
                {f.processing ? 'در حال ذخیره…' : '💾 ذخیره‌ی دسترسی‌ها'}
            </button>
        </form>
    );
}

/** کدام اعلان‌ها پیامک شوند و برای چه کسی. */
function EventMatrix({ events, config, sender }) {
    const f = useForm({
        events: Object.fromEntries(Object.keys(events).map((k) => [k, {
            parent: !!config?.[k]?.parent, student: !!config?.[k]?.student,
        }])),
        sender: sender || '',
    });
    const toggle = (k, who) => f.setData('events', { ...f.data.events, [k]: { ...f.data.events[k], [who]: !f.data.events[k][who] } });
    const save = (e) => { e.preventDefault(); f.post(route('school.sms.events'), { preserveScroll: true }); };
    const on = Object.values(f.data.events).filter((e) => e.parent || e.student).length;

    return (
        <form onSubmit={save} className="panel">
            <h3 style={{ marginTop: 0 }}>🔔 کدام اعلان‌ها پیامک شوند؟</h3>
            <p style={{ color: 'var(--muted)', fontSize: 13, lineHeight: 1.9 }}>
                هر اعلانی که اینجا روشن کنی، علاوه بر زنگوله‌ی سایت، به‌صورتِ پیامک هم فرستاده می‌شود.
                هر پیامک از سهمیه‌ی مدرسه کم می‌کند، پس فقط موارد مهم را روشن کن.
                الان <b>{fa(on)}</b> مورد روشن است.
            </p>

            <div style={{ overflowX: 'auto', marginTop: 10 }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 520 }}>
                    <thead>
                        <tr style={{ background: '#f6f8fc' }}>
                            <th style={th}>اعلان</th>
                            <th style={{ ...th, textAlign: 'center', width: 110 }}>👪 به ولی</th>
                            <th style={{ ...th, textAlign: 'center', width: 130 }}>🎓 به دانش‌آموز</th>
                        </tr>
                    </thead>
                    <tbody>
                        {Object.entries(events).map(([k, meta]) => (
                            <tr key={k} style={{ borderTop: '1px solid var(--line)' }}>
                                <td style={td}>
                                    <div style={{ fontWeight: 700 }}>{meta.label}</div>
                                    <div style={{ fontSize: 11.5, color: 'var(--muted)' }}>{meta.hint}</div>
                                </td>
                                <td style={{ ...td, textAlign: 'center' }}>
                                    <input type="checkbox" checked={f.data.events[k].parent} onChange={() => toggle(k, 'parent')} style={{ width: 20, height: 20 }} />
                                </td>
                                <td style={{ ...td, textAlign: 'center' }}>
                                    <input type="checkbox" checked={f.data.events[k].student} onChange={() => toggle(k, 'student')} style={{ width: 20, height: 20 }} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="field" style={{ marginTop: 14, maxWidth: 260 }}>
                <label>خطِ ارسالِ اختصاصیِ مدرسه (اختیاری)</label>
                <input className="input" dir="ltr" value={f.data.sender} onChange={(e) => f.setData('sender', e.target.value)} placeholder="خالی = خطِ پیش‌فرضِ سامانه" />
            </div>

            <button type="submit" disabled={f.processing} className="btn" style={{ marginTop: 6 }}>
                {f.processing ? 'در حال ذخیره…' : '💾 ذخیره‌ی تنظیمات'}
            </button>
        </form>
    );
}

/** اجازه و سهمیه‌ی هر معلم. */
function Teachers({ teachers }) {
    return (
        <div className="panel">
            <h3 style={{ marginTop: 0 }}>👩‍🏫 دسترسیِ پیامکِ معلم‌ها</h3>
            <p style={{ color: 'var(--muted)', fontSize: 13 }}>
                معلمی که اجازه بگیرد می‌تواند فقط به دانش‌آموزانِ کلاس‌های خودش و اولیای آن‌ها پیامک بدهد.
                سهمیه‌ی خالی یعنی «فقط محدود به سهمیه‌ی مدرسه».
            </p>
            <div style={{ display: 'grid', gap: 10, marginTop: 10 }}>
                {teachers.map((t) => <TeacherRow key={t.id} t={t} />)}
                {teachers.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز معلمی ثبت نشده.</p>}
            </div>
        </div>
    );
}

function TeacherRow({ t }) {
    const f = useForm({ sms_allowed: t.allowed, sms_quota: t.quota ?? '' });
    const save = () => f.post(route('school.sms.teacher', t.id), { preserveScroll: true });

    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 12, background: f.data.sms_allowed ? '#f6fffa' : '#fff',
            display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
            <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontWeight: 800, cursor: 'pointer' }}>
                <input type="checkbox" checked={f.data.sms_allowed} onChange={(e) => f.setData('sms_allowed', e.target.checked)} />
                👩‍🏫 {t.name}
            </label>
            <span style={{ fontSize: 12, color: 'var(--muted)' }} dir="ltr">{fa(t.phone || '')}</span>
            <span style={{ fontSize: 12, color: 'var(--muted)' }}>مصرف: {fa(t.used)} قطعه</span>
            <input className="input" type="number" min="0" inputMode="numeric" value={f.data.sms_quota}
                onChange={(e) => f.setData('sms_quota', e.target.value)} placeholder="سهمیه (اختیاری)"
                style={{ maxWidth: 160, marginInlineStart: 'auto' }} />
            <button onClick={save} disabled={f.processing} className="btn btn-sm">{f.processing ? '…' : '💾'}</button>
        </div>
    );
}

const th = { textAlign: 'start', padding: '9px 10px', fontSize: 12.5, color: 'var(--muted)', fontWeight: 700 };
const td = { padding: '9px 10px', fontSize: 13 };
