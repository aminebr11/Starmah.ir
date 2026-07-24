import { usePage, router, Link } from '@inertiajs/react';
import DashLayout, { parentMenu } from '@/Layouts/DashLayout';
import Avatar from '@/Components/Avatar';
import { AreaTrend, Donut, HBars, Heatmap, Gauge, Stat, OK, WARN, CRIT, PAL } from '@/Components/Charts';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** داشبوردِ والد — «وضعیتِ فرزندِ من» با نمودارهای BI و توصیه‌ی قابل‌فهم. */
export default function Dashboard() {
    const { children = [], selectedId, report } = usePage().props;
    const pick = (id) => router.get(route('parent.home'), { child: id }, { preserveScroll: true });

    if (!children.length) {
        return (
            <DashLayout title="وضعیتِ فرزندِ من" roleLabel="والد" menu={parentMenu} active="home">
                <div className="panel" style={{ textAlign: 'center', padding: 40 }}>
                    <div style={{ fontSize: 44 }}>👨‍👩‍👧</div>
                    <h3>هنوز فرزندی به حسابِ شما متصل نشده</h3>
                    <p style={{ color: 'var(--muted)' }}>از مدرسه بخواهید حسابِ شما را به دانش‌آموزتان متصل کند؛ بعد از آن گزارشِ کاملِ او را همین‌جا می‌بینید.</p>
                    <Link href="/messages" className="btn" style={{ marginTop: 8 }}>💬 پیام به مدرسه</Link>
                </div>
            </DashLayout>
        );
    }

    const r = report;
    const weekDelta = r ? r.week_xp - r.prev_week_xp : 0;

    return (
        <DashLayout title="وضعیتِ فرزندِ من" roleLabel="والد" menu={parentMenu} active="home">
            {/* انتخابِ فرزند */}
            {children.length > 1 && (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 16 }}>
                    {children.map((c) => (
                        <button key={c.id} onClick={() => pick(c.id)}
                            style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 14px', borderRadius: 14, cursor: 'pointer', fontFamily: 'inherit', fontWeight: 700, fontSize: 13.5,
                                border: c.id === selectedId ? '2px solid var(--gold)' : '1px solid var(--line)', background: c.id === selectedId ? '#fff8e8' : '#fff' }}>
                            <Avatar src={c.avatar} name={c.name} size={26} /> {c.emoji} {c.name}
                        </button>
                    ))}
                </div>
            )}

            {r && (
                <>
                    {/* هدرِ فرزند */}
                    <div className="panel" style={{ background: 'linear-gradient(135deg,#16264f,#0a1836)', border: 0, color: '#fff' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap' }}>
                            <span style={{ fontSize: 38 }}>{r.team?.emoji ?? '🎓'}</span>
                            <div style={{ flex: 1, minWidth: 180 }}>
                                <div style={{ fontWeight: 900, fontSize: 19 }}>{r.name}</div>
                                <div style={{ color: '#c4d2f0', fontSize: 13 }}>
                                    {r.classroom ? `کلاسِ ${r.classroom}` : ''}{r.teacher ? ` · معلم: ${r.teacher}` : ''}{r.team ? ` · تیمِ ${r.team.name}` : ''}
                                </div>
                            </div>
                            {r.rank && (
                                <div style={{ background: 'rgba(245,181,63,.18)', border: '1px solid rgba(245,181,63,.4)', borderRadius: 14, padding: '8px 16px', textAlign: 'center' }}>
                                    <b style={{ fontSize: 20, color: '#ffd87a' }}>{fa(r.rank)}</b>
                                    <div style={{ fontSize: 10.5, color: '#c4d2f0' }}>رتبه در کلاسِ {fa(r.class_size)} نفره</div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* کاشی‌های آماری */}
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 12, marginTop: 16 }}>
                        <Stat icon="⚡" label="کلِ امتیاز" value={fa(r.xp_total)} />
                        <Stat icon="📅" label="امتیازِ این هفته" value={fa(r.week_xp)}
                            sub={weekDelta === 0 ? 'مثلِ هفته‌ی قبل' : weekDelta > 0 ? `↑ ${fa(weekDelta)} بیشتر از هفته‌ی قبل` : `↓ ${fa(-weekDelta)} کمتر از هفته‌ی قبل`}
                            tone={weekDelta > 0 ? 'ok' : weekDelta < 0 ? 'warn' : undefined} />
                        {r.class_avg_week != null && <Stat icon="👥" label="میانگینِ هفتگیِ کلاس" value={fa(r.class_avg_week)}
                            sub={r.week_xp >= r.class_avg_week ? 'فرزندِ شما بالاتر از میانگین است' : 'فرزندِ شما زیرِ میانگین است'}
                            tone={r.week_xp >= r.class_avg_week ? 'ok' : 'warn'} />}
                        <Stat icon="✅" label="حضور (۳۰ روز)" value={fa(r.attendance.present)}
                            sub={r.attendance.absent ? `${fa(r.attendance.absent)} غیبت · ${fa(r.attendance.late)} تأخیر` : 'بدونِ غیبت 👏'}
                            tone={r.attendance.absent >= 2 ? 'warn' : 'ok'} />
                    </div>

                    {/* توصیه‌ی این هفته */}
                    <div className="panel" style={{ marginTop: 16 }}>
                        <h3 style={{ marginTop: 0 }}>💡 توصیه‌ی این هفته برای شما</h3>
                        <div style={{ display: 'grid', gap: 8 }}>
                            {r.advice.map((a, i) => (
                                <div key={i} style={{ display: 'flex', gap: 10, alignItems: 'flex-start', padding: '10px 13px', borderRadius: 12, fontSize: 13.5, lineHeight: 1.9,
                                    background: a.tone === 'ok' ? 'rgba(43,182,115,.09)' : 'rgba(232,134,46,.09)',
                                    border: `1px solid ${a.tone === 'ok' ? 'rgba(43,182,115,.3)' : 'rgba(232,134,46,.3)'}` }}>
                                    <span>{a.tone === 'ok' ? '✅' : '⚠️'}</span><span>{a.text}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* روند + ترکیب */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 16, marginTop: 16 }} className="themes-grid">
                        <div className="panel">
                            <h3 style={{ marginTop: 0 }}>📈 روندِ ۲۸ روزِ اخیر</h3>
                            <p style={{ color: 'var(--muted)', fontSize: 12, margin: '0 0 8px' }}>امتیازِ روزانه‌ای که فرزندتان از بازی، مأموریت و آزمون گرفته است.</p>
                            <AreaTrend data={r.trend} color={PAL[0]} />
                        </div>
                        <div className="panel">
                            <h3 style={{ marginTop: 0 }}>🧩 امتیاز از کجا آمده</h3>
                            <p style={{ color: 'var(--muted)', fontSize: 12, margin: '0 0 8px' }}>سهمِ هر نوع فعالیت در یادگیری.</p>
                            <Donut items={(r.by_type || []).map((t) => ({ label: t.label, value: t.points }))} centerLabel="مجموعِ امتیاز" />
                        </div>
                    </div>

                    {/* تسلط + عادتِ مطالعه + انضباط */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.4fr 1fr', gap: 16, marginTop: 16 }} className="themes-grid">
                        <div className="panel" style={{ textAlign: 'center' }}>
                            <h3 style={{ marginTop: 0 }}>🎯 تسلطِ مهارتی</h3>
                            <Gauge value={r.mastery} label="میانگینِ تسلط بر مهارت‌ها" size={120} />
                            <p style={{ color: 'var(--muted)', fontSize: 11.5, marginTop: 8 }}>
                                {r.mastery >= 70 ? 'تسلطِ خیلی خوب — یادگیری تثبیت شده.' : r.mastery >= 40 ? 'در مسیرِ رشد — تمرینِ بیشتر کمک می‌کند.' : 'نیازمندِ تمرین — بازی‌های درسی را با هم انجام دهید.'}
                            </p>
                        </div>
                        <div className="panel">
                            <h3 style={{ marginTop: 0 }}>🗓️ عادتِ فعالیت (۶ هفته)</h3>
                            <p style={{ color: 'var(--muted)', fontSize: 12, margin: '0 0 10px' }}>کدام روزها فعال است؟ نظمِ روزانه مهم‌تر از حجم است.</p>
                            <Heatmap weeks={r.heatmap.weeks} days={r.heatmap.days} legend="فعالیت" />
                        </div>
                        <div className="panel">
                            <h3 style={{ marginTop: 0 }}>⭐ انضباط (۳۰ روز)</h3>
                            <HBars items={[
                                { label: 'موردِ مثبت', value: r.discipline.pos, color: OK },
                                { label: 'موردِ منفی', value: r.discipline.neg, color: CRIT },
                            ]} />
                            <div style={{ marginTop: 12, fontSize: 12.5, color: 'var(--muted)' }}>
                                {r.discipline.neg === 0 ? 'هیچ موردِ منفی‌ای ثبت نشده 🎉' : 'برای جزئیات با معلم در ارتباط باشید.'}
                            </div>
                            <Link href="/messages" className="btn btn-sm" style={{ marginTop: 10 }}>💬 پیام به معلم</Link>
                        </div>
                    </div>
                </>
            )}
        </DashLayout>
    );
}
