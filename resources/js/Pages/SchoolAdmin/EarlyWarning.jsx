import { useState } from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const LV = { crit: ['🚨', 'جدی'], warn: ['⚠️', 'مهم'], info: ['💡', 'یادآوری'], ok: ['✅', 'عادی'] };
const KIND = { participation: '👥', mastery: '🎯', grades: '📝', attendance: '📅', backlog: '🔁', inactive: '💤', grading: '📥' };

/** نمودارِ کوچکِ مشارکتِ ۶ هفته. */
function Spark({ data }) {
    const w = 120, h = 34, max = 100;
    const pts = data.map((v, i) => `${(i / (data.length - 1)) * w},${h - (v / max) * (h - 4) - 2}`).join(' ');
    const last = data[data.length - 1];
    return (
        <svg width={w} height={h} viewBox={`0 0 ${w} ${h}`} className="ew-spark" aria-label="روندِ مشارکت">
            <polyline points={pts} fill="none" stroke="#2e8bff" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round" />
            <circle cx={w} cy={h - (last / max) * (h - 4) - 2} r="3.5" fill={last < 30 ? '#e8505b' : '#2bb673'} />
        </svg>
    );
}

/** مدیرِ مدرسه: «🚨 هشدارِ زودهنگام» — افتِ مشارکت و تسلطِ کلاس‌ها. */
export default function EarlyWarning() {
    const { alerts = [], classes = [], counts = {}, at } = usePage().props;
    const [filter, setFilter] = useState('all');
    const shown = alerts.filter((a) => filter === 'all' || a.level === filter);

    return (
        <DashLayout title="هشدارِ زودهنگام" roleLabel="مدیر مدرسه" menu={schoolMenu} active="early"
            actions={<button type="button" className="btn btn-sm btn-ghost" onClick={() => router.get(route('school.early.warning'), { refresh: 1 }, { preserveScroll: true })}>🔄 بررسیِ دوباره</button>}>
            <div className="at-hero ew-hero">
                <div className="at-hero-ic">🚨</div>
                <div>
                    <h2>هشدارِ زودهنگام</h2>
                    <p>سیستم هر کلاس را با هفته‌های قبلِ خودش مقایسه می‌کند تا افتِ مشارکت، تسلط، نمره، حضور و کارهای عقب‌افتاده را <b>پیش از</b> تبدیل‌شدن به مشکل نشان دهد؛ همراه با پیشنهادِ اقدام. آخرین بررسی: {at}</p>
                </div>
            </div>

            <div className="ew-sum">
                {['crit', 'warn', 'info'].map((k) => (
                    <button key={k} type="button" className={`ew-sum-i ${k} ${filter === k ? 'on' : ''}`} onClick={() => setFilter(filter === k ? 'all' : k)}>
                        <span>{LV[k][0]}</span><b>{fa(counts[k] ?? 0)}</b><small>{LV[k][1]}</small>
                    </button>
                ))}
                <div className="ew-sum-i ok"><span>✅</span><b>{fa(classes.filter((c) => c.level === 'ok').length)}</b><small>کلاسِ بی‌هشدار</small></div>
            </div>

            {shown.length === 0 ? (
                <div className="panel" style={{ textAlign: 'center', lineHeight: 2 }}>🌤️ هشداری در این دسته نیست. کلاس‌ها روالِ عادیِ خودشان را دارند.</div>
            ) : (
                <div className="ew-list">
                    {shown.map((a, i) => (
                        <div key={i} className={`ew-alert ${a.level}`}>
                            <div className="ew-alert-ic">{KIND[a.kind] || '⚠️'}</div>
                            <div className="ew-alert-b">
                                <div className="ew-alert-h"><b>{a.title}</b><span className={`ew-tag ${a.level}`}>{LV[a.level][0]} {LV[a.level][1]}</span></div>
                                <div className="ew-alert-c">🏫 {a.classroom}{a.teacher ? ` · 👩‍🏫 ${a.teacher}` : ''}</div>
                                <div>{a.detail}</div>
                                <div className="ew-tip">💡 {a.tip}</div>
                            </div>
                            <Link href="/messages" className="btn btn-sm btn-ghost">💬 پیام به معلم</Link>
                        </div>
                    ))}
                </div>
            )}

            <div className="panel">
                <h3 style={{ marginTop: 0 }}>📊 نبضِ کلاس‌ها</h3>
                <div className="ew-table-wrap">
                    <table className="ew-table">
                        <thead><tr><th>کلاس</th><th>وضعیت</th><th>مشارکتِ ۶ هفته</th><th>فعال در ۷ روز</th><th>دقتِ پاسخ</th><th>میانگینِ نمره</th><th>غیبت</th><th>بی‌فعالیت ۱۰+ روز</th></tr></thead>
                        <tbody>
                            {classes.map((c) => (
                                <tr key={c.classroom_id} className={c.level}>
                                    <td><b>{c.classroom}</b><small>{c.teacher} · {fa(c.students)} نفر</small></td>
                                    <td>{LV[c.level][0]} {LV[c.level][1]}</td>
                                    <td><Spark data={c.trend} /></td>
                                    <td>{fa(c.active)}٪{c.active_prev ? <small> (قبلاً {fa(c.active_prev)}٪)</small> : null}</td>
                                    <td>{c.accuracy != null ? <>{fa(c.accuracy)}٪{c.accuracy_prev != null && <small> ({fa(c.accuracy_prev)}٪)</small>}</> : '—'}</td>
                                    <td>{c.grade != null ? `${fa(c.grade)}٪` : '—'}</td>
                                    <td>{c.absence != null ? `${fa(c.absence)}٪` : '—'}</td>
                                    <td title={c.inactive.join('، ')}>{c.inactive_count ? <>{fa(c.inactive_count)} <small>{c.inactive.slice(0, 3).join('، ')}{c.inactive_count > 3 ? '…' : ''}</small></> : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </DashLayout>
    );
}
