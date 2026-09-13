import { useState } from 'react';
import { usePage, useForm, router } from '@inertiajs/react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** پنلِ پیامکِ ادمینِ کل: درگاه + دسترسیِ مدرسه‌ها + مصرف + سابقه. */
export default function Sms() {
    const { gateway = {}, schools = [], stats = {}, log = [], flash } = usePage().props;
    const [tab, setTab] = useState('gateway');

    const g = useForm({
        sms_enabled: gateway.enabled ?? false,
        sms_provider: gateway.provider || 'iransms',
        sms_sender: gateway.sender || '',
        sms_api_key: '',
    });
    const [testPhone, setTestPhone] = useState('');

    const saveGateway = (e) => { e.preventDefault(); g.post(route('admin.sms.gateway'), { preserveScroll: true }); };
    const test = () => router.post(route('admin.sms.test'), { phone: testPhone }, { preserveScroll: true });

    const banner = flash?.flash;
    const msg = typeof banner === 'string' ? banner : banner?.message;
    const bad = typeof banner === 'object' && banner?.type === 'error';

    const TABS = [
        { v: 'gateway', t: '🔌 درگاهِ پیامک' },
        { v: 'schools', t: `🏫 دسترسیِ مدرسه‌ها (${fa(schools.length)})` },
        { v: 'log', t: `📜 سابقه (${fa(log.length)})` },
    ];

    return (
        <DashLayout title="سامانه‌ی پیامک" roleLabel="ادمین کل" menu={adminMenu} active="sms">
            {msg && (
                <div className="panel" style={{ borderColor: bad ? '#e8505b' : 'var(--gold)', background: bad ? '#fdecee' : '#fff8e8' }}>
                    <b style={{ color: bad ? '#b0333f' : 'inherit' }}>{msg}</b>
                </div>
            )}

            {/* وضعیتِ کلی */}
            <div className="dash-cards" style={{ marginBottom: 4 }}>
                <div className="dcard">
                    <div style={{ fontSize: 28 }}>{gateway.ready ? '🟢' : '🔴'}</div>
                    <div style={{ fontWeight: 800, marginTop: 6 }}>{gateway.ready ? 'درگاه فعال است' : 'درگاه خاموش است'}</div>
                    <div style={{ color: 'var(--muted)', fontSize: 12 }}>{gateway.provider === 'iransms' ? 'ایران‌اس‌ام‌اس‌سرویس' : gateway.provider === 'custom' ? 'قالبِ دلخواه' : '—'}</div>
                </div>
                <div className="dcard">
                    <div style={{ fontSize: 28 }}>📤</div>
                    <div style={{ fontWeight: 800, marginTop: 6 }}>{fa(stats.sent ?? 0)} قطعه</div>
                    <div style={{ color: 'var(--muted)', fontSize: 12 }}>ارسالِ {fa(stats.window ?? 30)} روزِ اخیر</div>
                </div>
                <div className="dcard">
                    <div style={{ fontSize: 28 }}>⚠️</div>
                    <div style={{ fontWeight: 800, marginTop: 6 }}>{fa(stats.failed ?? 0)}</div>
                    <div style={{ color: 'var(--muted)', fontSize: 12 }}>ارسالِ ناموفق</div>
                </div>
                <div className="dcard">
                    <div style={{ fontSize: 28 }}>🏫</div>
                    <div style={{ fontWeight: 800, marginTop: 6 }}>{fa(stats.schools ?? 0)}</div>
                    <div style={{ color: 'var(--muted)', fontSize: 12 }}>مدرسه‌ی دارای دسترسی</div>
                </div>
            </div>

            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', margin: '14px 0' }}>
                {TABS.map((t) => (
                    <button key={t.v} onClick={() => setTab(t.v)} className={`tag ${tab === t.v ? 'tag-warn' : 'tag-info'}`}
                        style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 16px', fontSize: 13 }}>{t.t}</button>
                ))}
            </div>

            {tab === 'gateway' && (
                <form onSubmit={saveGateway} className="panel">
                    <h3 style={{ marginTop: 0 }}>🔌 اتصال به سامانه‌ی پیامک</h3>
                    <p style={{ color: 'var(--muted)', fontSize: 13, lineHeight: 1.9 }}>
                        کلید فقط روی سرورِ شما ذخیره می‌شود و هرگز به مرورگر برنمی‌گردد؛ در این صفحه تنها
                        چهار رقمِ آخرش نشان داده می‌شود. برای تغییرِ کلید، کلیدِ تازه را بنویسید؛ خالی گذاشتن
                        یعنی «کلیدِ فعلی دست‌نخورده بماند».
                    </p>

                    <label style={{ display: 'flex', gap: 10, alignItems: 'center', margin: '10px 0 16px' }}>
                        <input type="checkbox" checked={g.data.sms_enabled} onChange={(e) => g.setData('sms_enabled', e.target.checked)} />
                        <b>سامانه‌ی پیامک روشن باشد</b>
                    </label>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 12 }}>
                        <Field label="سرویس‌دهنده">
                            <select className="input" value={g.data.sms_provider} onChange={(e) => g.setData('sms_provider', e.target.value)}>
                                <option value="iransms">ایران‌اس‌ام‌اس‌سرویس (iransmsservice.com)</option>
                                <option value="custom">سامانه‌ی دیگر (قالبِ دلخواه)</option>
                                <option value="off">خاموش</option>
                            </select>
                        </Field>
                        <Field label="خطِ ارسال (sender)" err={g.errors.sms_sender}>
                            <input className="input" dir="ltr" value={g.data.sms_sender} onChange={(e) => g.setData('sms_sender', e.target.value)} placeholder="30006708155155" />
                        </Field>
                        <Field label={`کلیدِ API ${gateway.api_key_set ? `(فعلی: ${gateway.api_key_hint})` : '(ثبت نشده)'}`} err={g.errors.sms_api_key}>
                            <input className="input" dir="ltr" type="password" autoComplete="new-password"
                                value={g.data.sms_api_key} onChange={(e) => g.setData('sms_api_key', e.target.value)} placeholder="برای تغییر، کلیدِ تازه را بنویس" />
                        </Field>
                    </div>

                    {g.data.sms_provider === 'iransms' && (
                        <div style={{ marginTop: 12, background: '#f6f8fc', borderRadius: 12, padding: '10px 14px', fontSize: 12.5, color: 'var(--muted)', lineHeight: 2 }}>
                            نقطه‌ی ارسال: <span dir="ltr">{gateway.endpoint}</span><br />
                            کلید در هدرِ <span dir="ltr">apikey</span> فرستاده می‌شود و هر درخواست می‌تواند چند گیرنده داشته باشد.
                        </div>
                    )}

                    <button type="submit" disabled={g.processing} className="btn" style={{ marginTop: 14 }}>
                        {g.processing ? 'در حال ذخیره…' : '💾 ذخیره‌ی تنظیمات'}
                    </button>

                    <div style={{ marginTop: 18, borderTop: '1px solid var(--line)', paddingTop: 14 }}>
                        <b style={{ fontSize: 14 }}>📲 ارسالِ پیامکِ آزمایشی</b>
                        <div style={{ display: 'flex', gap: 8, marginTop: 8, flexWrap: 'wrap' }}>
                            <input className="input" dir="ltr" value={testPhone} onChange={(e) => setTestPhone(e.target.value)}
                                placeholder="09xxxxxxxxx" style={{ maxWidth: 200 }} />
                            <button type="button" onClick={test} disabled={!testPhone} className="btn btn-ghost">ارسالِ آزمایشی</button>
                        </div>
                        <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 6 }}>⚠️ این یک پیامکِ واقعی است و از اعتبارِ پنلِ شما کم می‌کند.</div>
                    </div>
                </form>
            )}

            {tab === 'schools' && (
                <div className="panel">
                    <h3 style={{ marginTop: 0 }}>🏫 دسترسیِ پیامکِ مدرسه‌ها</h3>
                    <p style={{ color: 'var(--muted)', fontSize: 13 }}>
                        سهمیه بر حسبِ «قطعه‌ی پیامک» در {fa(stats.window ?? 30)} روزِ اخیر است. خالی گذاشتنِ سهمیه یعنی بدونِ سقف.
                    </p>
                    <div style={{ display: 'grid', gap: 10, marginTop: 10 }}>
                        {schools.map((s) => <SchoolRow key={s.id} s={s} />)}
                        {schools.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز مدرسه‌ای ثبت نشده.</p>}
                    </div>
                </div>
            )}

            {tab === 'log' && <LogTable log={log} showSchool />}
        </DashLayout>
    );
}

function SchoolRow({ s }) {
    const f = useForm({ sms_enabled: s.enabled, sms_quota: s.quota ?? '', sms_sender: s.sender ?? '' });
    const save = () => f.post(route('admin.sms.school', s.id), { preserveScroll: true });
    const pct = s.quota ? Math.min(100, Math.round((s.used / s.quota) * 100)) : 0;

    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 13, background: f.data.sms_enabled ? '#f6fffa' : '#fff' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontWeight: 800, cursor: 'pointer' }}>
                    <input type="checkbox" checked={f.data.sms_enabled} onChange={(e) => f.setData('sms_enabled', e.target.checked)} />
                    🏫 {s.name}
                </label>
                <span className="tag" style={{ fontSize: 11 }}>{s.city || '—'}</span>
                <span style={{ marginInlineStart: 'auto', fontSize: 12, color: 'var(--muted)' }}>
                    مصرف: <b>{fa(s.used)}</b> قطعه{s.quota != null ? ` از ${fa(s.quota)}` : ' (بدونِ سقف)'}
                </span>
            </div>

            {s.quota != null && (
                <div style={{ height: 6, background: '#eef2f8', borderRadius: 6, overflow: 'hidden', margin: '9px 0' }}>
                    <div style={{ height: '100%', width: `${pct}%`, background: pct > 85 ? '#e8505b' : 'var(--gold)' }} />
                </div>
            )}

            <div style={{ display: 'flex', gap: 8, marginTop: 8, flexWrap: 'wrap', alignItems: 'center' }}>
                <input className="input" type="number" min="0" inputMode="numeric" value={f.data.sms_quota}
                    onChange={(e) => f.setData('sms_quota', e.target.value)} placeholder="سهمیه (خالی = بدونِ سقف)" style={{ maxWidth: 210 }} />
                <input className="input" dir="ltr" value={f.data.sms_sender} onChange={(e) => f.setData('sms_sender', e.target.value)}
                    placeholder="خطِ اختصاصی (اختیاری)" style={{ maxWidth: 200 }} />
                <button onClick={save} disabled={f.processing} className="btn btn-sm">{f.processing ? '…' : '💾 ذخیره'}</button>
            </div>
        </div>
    );
}

export function LogTable({ log = [], showSchool = false }) {
    if (log.length === 0) {
        return <div className="panel"><p style={{ color: 'var(--muted)', margin: 0 }}>هنوز پیامکی ارسال نشده.</p></div>;
    }
    return (
        <div className="panel">
            <h3 style={{ marginTop: 0 }}>📜 سابقه‌ی ارسال</h3>
            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 640 }}>
                    <thead>
                        <tr style={{ background: '#f6f8fc' }}>
                            <th style={th}>تاریخ</th>
                            {showSchool && <th style={th}>مدرسه</th>}
                            <th style={th}>فرستنده</th>
                            <th style={th}>گیرنده</th>
                            <th style={th}>نوع</th>
                            <th style={th}>متن</th>
                            <th style={th}>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        {log.map((m) => (
                            <tr key={m.id} style={{ borderTop: '1px solid var(--line)' }}>
                                <td style={td}>{fa(m.date)}</td>
                                {showSchool && <td style={td}>{m.school || '—'}</td>}
                                <td style={td}>{m.sender}</td>
                                <td style={td}>{m.to ? `${m.to} · ` : ''}<span dir="ltr">{fa(m.phone)}</span></td>
                                <td style={td}>{m.kind}</td>
                                <td style={{ ...td, maxWidth: 260 }}>{m.body}</td>
                                <td style={td}>
                                    {m.status === 'sent'
                                        ? <span className="tag tag-ok" style={{ fontSize: 11 }}>✅ ارسال شد ({fa(m.segments)})</span>
                                        : <span className="tag" style={{ fontSize: 11, background: '#fdecee', color: '#b0333f' }} title={m.error || ''}>❌ ناموفق</span>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function Field({ label, err, children }) {
    return <div className="field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}

const th = { textAlign: 'start', padding: '9px 10px', fontSize: 12.5, color: 'var(--muted)', fontWeight: 700 };
const td = { padding: '9px 10px', fontSize: 12.5 };
