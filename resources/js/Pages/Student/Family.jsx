import { usePage, useForm, router, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import ThemedDash from '@/Layouts/ThemedDash';
import { AreaTrend, Donut, Gauge, HBars, OK, CRIT, PAL } from '@/Components/Charts';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.06)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 16, color: '#fff' };
const lightPanel = { background: '#fff', color: '#1a2440', borderRadius: 18, padding: 16 };

/** «بخشِ والدین» — پشتِ رمزِ والدین: گزارشِ محرمانه + پیام‌های معلم/مدیر + پاسخِ والد. */
export default function Family() {
    const { unlocked = false, hasPin = true, report = null, notes = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    return (
        <ThemedDash title="بخشِ والدین" active="family">
            {!unlocked ? <LockScreen hasPin={hasPin} /> : <ParentArea r={report} notes={notes} banner={banner} />}
        </ThemedDash>
    );
}

/* ---------- صفحه‌ی قفل (ورودِ والد با رمز) ---------- */
function LockScreen({ hasPin }) {
    const { data, setData, post, processing, errors } = useForm({ pin: '' });
    const submit = (e) => { e.preventDefault(); post(route('family.unlock')); };

    return (
        <div style={{ maxWidth: 420, margin: '30px auto', textAlign: 'center' }}>
            <div style={{ ...card, padding: 28 }}>
                <div style={{ fontSize: 52 }}>🔐</div>
                <h2 style={{ margin: '10px 0 4px', fontSize: 20 }}>بخشِ والدین</h2>
                <p style={{ opacity: .8, fontSize: 13.5, lineHeight: 2 }}>
                    این بخش مخصوصِ پدر و مادر است. گزارشِ محرمانه‌ی پیشرفت و پیام‌های معلم و مدیر این‌جاست.
                    <br />🧒 اگر دانش‌آموز هستی، این‌جا چیزی برای تو نیست — برگرد به بازی! 🎮
                </p>
                {hasPin ? (
                    <form onSubmit={submit} style={{ marginTop: 14 }}>
                        <input value={data.pin} onChange={(e) => setData('pin', e.target.value.replace(/\D/g, '').slice(0, 8))}
                            dir="ltr" inputMode="numeric" placeholder="• • • • •" autoFocus
                            style={{ width: 190, textAlign: 'center', fontSize: 24, letterSpacing: 8, fontWeight: 900, padding: '10px 12px', borderRadius: 14, border: '2px solid rgba(255,255,255,.25)', background: 'rgba(0,0,0,.25)', color: '#fff', fontFamily: 'inherit' }} />
                        {errors.pin && <div style={{ color: '#ffb3b3', fontSize: 12.5, marginTop: 8 }}>{errors.pin}</div>}
                        <button type="submit" disabled={processing || !data.pin} className="k3-btn" style={{ width: '100%', marginTop: 14, fontSize: 15 }}>
                            ورودِ والدین 🔓
                        </button>
                    </form>
                ) : (
                    <div style={{ marginTop: 12, background: 'rgba(232,134,46,.2)', border: '1px solid rgba(232,134,46,.45)', borderRadius: 12, padding: '10px 14px', fontSize: 13 }}>
                        هنوز رمزِ والدین برای این حساب تنظیم نشده — از مدیرِ مدرسه بخواهید رمز را تنظیم کند.
                    </div>
                )}
                <div style={{ marginTop: 14, fontSize: 12, opacity: .7 }}>رمز را فراموش کرده‌اید؟ مدیرِ مدرسه می‌تواند آن را ببیند و عوض کند.</div>
            </div>
        </div>
    );
}

/* ---------- ناحیه‌ی بازشده‌ی والدین ---------- */
function ParentArea({ r, notes, banner }) {
    const reply = useForm({ body: '' });
    const send = (e) => { e.preventDefault(); reply.post(route('family.reply'), { preserveScroll: true, onSuccess: () => reply.setData('body', '') }); };
    const weekDelta = r ? r.week_xp - r.prev_week_xp : 0;

    return (
        <>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 14 }}>
                <span style={{ background: 'rgba(43,182,115,.2)', border: '1px solid rgba(43,182,115,.45)', borderRadius: 20, padding: '5px 14px', fontSize: 12.5, fontWeight: 800 }}>🔓 واردِ بخشِ والدین شده‌اید</span>
                <a href={`/print/student/${r?.id}`} target="_blank" rel="noopener" className="k3-btn ghost" style={{ fontSize: 12.5 }}>🖨️ چاپِ کارنامه‌ی جامع</a>
                <button onClick={() => router.post(route('family.lock'))} className="k3-btn ghost" style={{ fontSize: 12.5, marginInlineStart: 'auto' }}>🔒 خروج از بخشِ والدین</button>
            </div>
            {banner && <div style={{ ...card, borderColor: 'rgba(43,182,115,.5)', marginBottom: 12, padding: '10px 14px' }}><b>{banner}</b></div>}

            {/* پیام‌های محرمانه */}
            <div style={{ ...lightPanel, marginBottom: 14 }}>
                <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 4 }}>💌 پیام‌های محرمانه‌ی معلم و مدیر</div>
                <div style={{ color: '#6b7794', fontSize: 12, marginBottom: 10 }}>این پیام‌ها فقط این‌جا (پشتِ رمزِ والدین) نمایش داده می‌شوند.</div>
                {notes.length === 0 && <div style={{ color: '#6b7794', fontSize: 13 }}>هنوز پیامی ارسال نشده است.</div>}
                <div style={{ display: 'grid', gap: 8, maxHeight: 320, overflowY: 'auto' }}>
                    {notes.map((n) => (
                        <div key={n.id} style={{ borderRadius: 12, padding: '10px 13px', fontSize: 13.5, lineHeight: 1.9,
                            background: n.from_parent ? '#eef7ff' : '#fff8e8',
                            border: `1px solid ${n.from_parent ? '#cfe3fa' : '#f3ddaa'}` }}>
                            <div style={{ display: 'flex', gap: 8, fontSize: 12, color: '#6b7794', marginBottom: 3 }}>
                                <b style={{ color: '#1a2440' }}>{n.from_parent ? '👨‍👩‍👧 شما' : `🏫 ${n.sender}`}</b>
                                {n.title && <span>· {n.title}</span>}
                                <span style={{ marginInlineStart: 'auto' }}>{n.date}</span>
                            </div>
                            {n.body}
                        </div>
                    ))}
                </div>
                <form onSubmit={send} style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                    <input className="input" value={reply.data.body} onChange={(e) => reply.setData('body', e.target.value)}
                        placeholder="پاسخ/پیامِ شما به مدرسه…" style={{ flex: 1 }} />
                    <button type="submit" disabled={reply.processing || !reply.data.body.trim()} className="btn">ارسال 📤</button>
                </form>
                {reply.errors.body && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{reply.errors.body}</div>}
            </div>

            {r && (
                <>
                    {/* خلاصه‌ی وضعیت */}
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10, marginBottom: 14 }}>
                        <MiniStat l="کلِ امتیاز" v={`⚡${fa(r.xp_total)}`} />
                        <MiniStat l="این هفته" v={fa(r.week_xp)} sub={weekDelta > 0 ? `↑ ${fa(weekDelta)}` : weekDelta < 0 ? `↓ ${fa(-weekDelta)}` : '='} tone={weekDelta >= 0 ? 'ok' : 'warn'} />
                        {r.rank && <MiniStat l="رتبه در کلاس" v={`${fa(r.rank)} از ${fa(r.class_size)}`} />}
                        <MiniStat l="غیبت (۳۰ روز)" v={fa(r.attendance.absent)} tone={r.attendance.absent >= 2 ? 'warn' : 'ok'} />
                    </div>

                    {/* توصیه */}
                    <div style={{ ...lightPanel, marginBottom: 14 }}>
                        <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 8 }}>💡 توصیه‌ی این هفته</div>
                        <div style={{ display: 'grid', gap: 7 }}>
                            {r.advice.map((a, i) => (
                                <div key={i} style={{ display: 'flex', gap: 9, padding: '9px 12px', borderRadius: 11, fontSize: 13, lineHeight: 1.9,
                                    background: a.tone === 'ok' ? 'rgba(43,182,115,.09)' : 'rgba(232,134,46,.09)',
                                    border: `1px solid ${a.tone === 'ok' ? 'rgba(43,182,115,.3)' : 'rgba(232,134,46,.35)'}` }}>
                                    <span>{a.tone === 'ok' ? '✅' : '⚠️'}</span><span>{a.text}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* نمودارها */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 14 }} className="themes-grid">
                        <div style={lightPanel}>
                            <div style={{ fontWeight: 900, fontSize: 14.5, marginBottom: 8 }}>📈 روندِ ۲۸ روزِ اخیر</div>
                            <AreaTrend data={r.trend} color={PAL[0]} />
                        </div>
                        <div style={lightPanel}>
                            <div style={{ fontWeight: 900, fontSize: 14.5, marginBottom: 8 }}>🧩 امتیاز از کجا آمده</div>
                            <Donut items={(r.by_type || []).map((t) => ({ label: t.label, value: t.points }))} centerLabel="مجموع" size={120} thickness={16} />
                        </div>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginTop: 14 }} className="themes-grid">
                        <div style={{ ...lightPanel, textAlign: 'center' }}>
                            <div style={{ fontWeight: 900, fontSize: 14.5, marginBottom: 6 }}>🎯 تسلطِ مهارتی</div>
                            <Gauge value={r.mastery} size={110} label="میانگینِ تسلط" />
                        </div>
                        <div style={lightPanel}>
                            <div style={{ fontWeight: 900, fontSize: 14.5, marginBottom: 8 }}>⭐ انضباط (۳۰ روز)</div>
                            <HBars items={[
                                { label: 'موردِ مثبت', value: r.discipline.pos, color: OK },
                                { label: 'موردِ منفی', value: r.discipline.neg, color: CRIT },
                            ]} />
                        </div>
                    </div>
                </>
            )}
        </>
    );
}

function MiniStat({ l, v, sub, tone }) {
    const c = tone === 'ok' ? '#7be0b0' : tone === 'warn' ? '#ffcf99' : '#fff';
    return (
        <div style={{ ...card, padding: '11px 14px' }}>
            <div style={{ fontSize: 11.5, opacity: .75 }}>{l}</div>
            <div style={{ fontWeight: 900, fontSize: 18, color: c }}>{v} {sub && <small style={{ fontSize: 11, opacity: .9 }}>{sub}</small>}</div>
        </div>
    );
}
