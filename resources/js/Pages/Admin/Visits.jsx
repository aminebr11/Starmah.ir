import { useState } from 'react';
import { usePage, router, Link } from '@inertiajs/react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';
import { Donut, HBars } from '@/Components/Charts';
import { PeriodPicker, Kpi, DailyBars, HoursStrip, Pct, Dot, fa, mins, tone } from '@/Components/VisitKit';

const DEV = { app: ['app', 'اپ'], mobile: ['mobile', 'موبایل'], desktop: ['desktop', 'کامپیوتر'] };

/** ادمینِ کل: گزارشِ کاملِ بازدیدِ سایت، آنلاین‌ها، مشارکتِ نقش‌ها و مدارس. */
export default function Visits() {
    const { days, periods, kpis: k, roles = [], schools = [], onlineNow = [], series = [], hours = [], sections = [], devices = [], users, filters = {}, schoolOptions = [] } = usePage().props;
    const [q, setQ] = useState(filters.q || '');
    const go = (patch) => router.get(route('admin.visits'), { days, q, role: filters.role || '', school: filters.school || '', status: filters.status || '', ...patch }, { preserveState: true, preserveScroll: true, replace: true, only: ['users', 'filters'] });

    return (
        <DashLayout title="گزارشِ بازدیدِ سایت" roleLabel="ادمین کل" menu={adminMenu} active="visits">
            <div className="panel">
                <div className="vk-head">
                    <p>همه‌ی بازدیدها، کاربرانِ آنلاین و میزانِ مشارکتِ هر نقش و هر مدرسه.</p>
                    <PeriodPicker days={days} periods={periods} />
                </div>
                <div className="vk-grid">
                    <Kpi pulse label="الان آنلاین" value={fa(k.online)} sub={`+ ${fa(k.online_guests)} مهمان`} color="#2bb673" />
                    <Kpi icon="📅" label="بازدیدکنندگانِ امروز" value={fa(k.today_members + k.today_guests)} sub={`${fa(k.today_members)} کاربر · ${fa(k.today_guests)} مهمان · ${fa(k.today_hits)} صفحه`} color="#3d7bf0" />
                    <Kpi icon="👥" label={`کاربرانِ فعالِ ${fa(days)} روز`} value={fa(k.members)} sub={`از ${fa(k.users)} کاربر (${fa(k.users ? Math.round((k.members / k.users) * 100) : 0)}٪)`} color="#8b5cf6" />
                    <Kpi icon="🌐" label="بازدیدِ مهمان‌ها" value={fa(k.guest_visits)} sub="روز-بازدیدکننده، بدونِ ورود" color="#e0912f" />
                    <Kpi icon="📄" label="صفحه‌های دیده‌شده" value={fa(k.hits)} sub={`در ${fa(days)} روز`} color="#149d8a" />
                    <Kpi icon="⏱️" label="میانگینِ زمانِ روزانه" value={mins(k.avg_minutes)} sub={`${fa(k.never)} کاربر هرگز وارد نشده`} color="#d6516c" />
                </div>
            </div>

            <div className="panel vk-sec">
                <h3>🧑‍🤝‍🧑 مشارکتِ هر نقش</h3>
                <div className="vk-grid">
                    {roles.map((r) => (
                        <div key={r.role} className="vk-kpi" style={{ '--vk': tone(r.rate), display: 'block' }}>
                            <div className="vk-kpi-l">{r.label} · <Dot on={r.online > 0} /> {fa(r.online)} آنلاین</div>
                            <div className="vk-kpi-v">{fa(r.rate)}٪</div>
                            <div className="vk-kpi-s">{fa(r.active)} از {fa(r.total)} نفر فعال · {mins(r.minutes)}</div>
                            <div className="vk-rank-bar"><i style={{ width: `${r.rate}%`, background: tone(r.rate) }} /></div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="panel vk-sec">
                <h3>📈 بازدیدکنندگانِ روزانه</h3>
                <DailyBars data={series} guests />
            </div>

            <div className="vk-two vk-sec">
                <div className="panel"><h3>🕐 ساعت‌های پربازدید</h3><HoursStrip hours={hours} /></div>
                <div className="panel"><h3>📱 دستگاه‌ها</h3><Donut items={devices} centerLabel="روز-بازدیدکننده" /></div>
            </div>

            <div className="vk-two vk-sec">
                <div className="panel"><h3>🧭 پربازدیدترین بخش‌ها</h3><HBars items={sections} colorByIndex /></div>
                <div className="panel">
                    <h3>🟢 همین حالا آنلاین ({fa(onlineNow.length)})</h3>
                    {onlineNow.length ? (
                        <div style={{ display: 'grid', gap: 6, maxHeight: 330, overflowY: 'auto' }}>
                            {onlineNow.map((o) => (
                                <div key={o.id} className="vk-online-p" style={{ justifyContent: 'space-between' }}>
                                    <span style={{ display: 'flex', alignItems: 'center', gap: 7, minWidth: 0 }}><Dot on /><b>{o.name}</b><small>{o.role}{o.school ? ` · ${o.school}` : ''}</small></span>
                                    <span style={{ display: 'flex', gap: 6, alignItems: 'center' }}><small>📍 {o.section}</small>{o.device && <span className={`vk-tag ${DEV[o.device]?.[0]}`}>{DEV[o.device]?.[1]}</span>}</span>
                                </div>
                            ))}
                        </div>
                    ) : <div className="vk-empty">الان کسی آنلاین نیست.</div>}
                </div>
            </div>

            <div className="panel vk-sec">
                <h3>🏫 رتبه‌ی مدارس در مشارکت</h3>
                <div className="vk-tblwrap">
                    <table className="tbl vk-tbl">
                        <thead><tr><th>#</th><th>مدرسه</th><th>کاربران</th><th>فعال</th><th>مشارکتِ کل</th><th>دانش‌آموزان</th><th>معلم‌ها</th><th>والدین</th><th>صفحه</th><th>زمان</th></tr></thead>
                        <tbody>
                            {schools.map((s, i) => (
                                <tr key={s.id}>
                                    <td>{['🥇', '🥈', '🥉'][i] || fa(i + 1)}</td>
                                    <td><b>{s.name}</b>{s.city && <small style={{ color: 'var(--muted)' }}> · {s.city}</small>}</td>
                                    <td>{fa(s.users)}</td><td>{fa(s.active)}</td>
                                    <td><Pct v={s.rate} /></td>
                                    <td><Pct v={s.student_rate} w={44} /></td>
                                    <td><Pct v={s.teacher_rate} w={44} /></td>
                                    <td><Pct v={s.parent_rate} w={44} /></td>
                                    <td>{fa(s.hits)}</td><td>{mins(s.minutes)}</td>
                                </tr>
                            ))}
                            {!schools.length && <tr><td colSpan={10} style={{ color: 'var(--muted)' }}>مدرسه‌ای ثبت نشده.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="panel vk-sec">
                <h3>👤 همه‌ی کاربران</h3>
                <form className="vk-filters" onSubmit={(e) => { e.preventDefault(); go({ page: 1 }); }}>
                    <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 نام یا موبایل…" />
                    <select className="input" value={filters.role || ''} onChange={(e) => go({ role: e.target.value, page: 1 })}>
                        <option value="">همه‌ی نقش‌ها</option>
                        {roles.map((r) => <option key={r.role} value={r.role}>{r.label}</option>)}
                    </select>
                    <select className="input" value={filters.school || ''} onChange={(e) => go({ school: e.target.value, page: 1 })}>
                        <option value="">همه‌ی مدارس</option>
                        {schoolOptions.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                    <select className="input" value={filters.status || ''} onChange={(e) => go({ status: e.target.value, page: 1 })}>
                        <option value="">همه</option><option value="online">🟢 آنلاین</option><option value="offline">⚪ آفلاین</option>
                        <option value="inactive">💤 غیرفعالِ ۷ روز</option><option value="never">🚫 هرگز وارد نشده</option>
                    </select>
                    <button className="btn btn-sm" type="submit">جست‌وجو</button>
                    <span className="vk-count">{fa(users?.total ?? 0)} کاربر</span>
                </form>
                <div className="vk-tblwrap">
                    <table className="tbl vk-tbl">
                        <thead><tr><th>کاربر</th><th>نقش</th><th>مدرسه</th><th>وضعیت</th><th>آخرین حضور</th><th>روزهای حضور</th><th>صفحه</th><th>زمان</th><th>ورودها</th></tr></thead>
                        <tbody>
                            {(users?.data || []).map((u) => (
                                <tr key={u.id}>
                                    <td><b>{u.name}</b><div dir="ltr" style={{ fontSize: 11, color: 'var(--muted)', textAlign: 'right' }}>{u.phone}</div></td>
                                    <td>{u.role}</td><td>{u.school || '—'}</td>
                                    <td><span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}><Dot on={u.online} />{u.online ? 'آنلاین' : 'آفلاین'}</span></td>
                                    <td>{u.last_seen_label}</td>
                                    <td>{fa(u.days)} <small style={{ color: 'var(--muted)' }}>({fa(u.presence)}٪)</small></td>
                                    <td>{fa(u.hits)}</td><td>{mins(u.minutes)}</td><td>{fa(u.logins)}</td>
                                </tr>
                            ))}
                            {!users?.data?.length && <tr><td colSpan={9} style={{ color: 'var(--muted)' }}>کاربری پیدا نشد.</td></tr>}
                        </tbody>
                    </table>
                </div>
                {users?.last_page > 1 && (
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 12 }}>
                        {users.links.filter((l) => l.url).map((l, i) => (
                            <Link key={i} href={l.url} preserveState preserveScroll only={['users', 'filters']} className={`btn btn-sm ${l.active ? '' : 'btn-ghost'}`}
                                dangerouslySetInnerHTML={{ __html: l.label }} />
                        ))}
                    </div>
                )}
            </div>
            <div className="vk-note" style={{ marginTop: 8 }}>«روز-بازدیدکننده» یعنی هر نفر در هر روز یک بار شمرده می‌شود. مهمان‌ها با نشانیِ اینترنت و مرورگرشان (بی‌نام) از هم جدا می‌شوند. آمار از روزِ فعال‌شدنِ این گزارش جمع می‌شود.</div>
        </DashLayout>
    );
}
