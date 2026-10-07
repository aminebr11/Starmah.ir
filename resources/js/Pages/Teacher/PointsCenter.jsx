import { usePage, router, useForm } from '@inertiajs/react';
import { useState, useEffect, useMemo } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import Avatar from '@/Components/Avatar';
import PointsTrend from '@/Components/PointsTrend';
import PointsRankPanel from '@/Components/PointsRankPanel';
import JalaliDatePicker from '@/Components/JalaliDatePicker';
import { useSort, SortBar, firstName, lastName, normDigits } from '@/lib/useSort';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const sgn = (n) => (n > 0 ? `+${fa(n)}` : n < 0 ? `−${fa(Math.abs(n))}` : fa(0));
const norm = (s) => normDigits(String(s || '')).replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/\s+/g, ' ').trim().toLowerCase();

/**
 * مرکزِ امتیاز — «امتیازِ دانش‌آموزان»، «امتیازدهیِ گروهی» و «امتیازِ تیم‌ها» در یک‌جا.
 *
 * امتیازدهی در سه گام: «به چه کسی» (یک/چند دانش‌آموز، یک/چند تیم، کلِ کلاس) ← «چقدر» ← «برای چه».
 * هر بار امتیازدهی یک «نوبت» است که در «سوابق» یک‌جا ویرایش یا برگردانده می‌شود.
 */
const TABS = [
    { k: 'give', t: '⚡ امتیازدهی' },
    { k: 'history', t: '🧾 سوابق' },
    { k: 'students', t: '🎓 دانش‌آموزان' },
    { k: 'teams', t: '🏆 تیم‌ها' },
    { k: 'rank', t: '📊 رتبه‌بندی' },
    { k: 'activities', t: '🏅 فعالیت‌های آماده' },
];

const PLUS = [
    { r: 'پاسخِ درست', i: '✅', c: 'answer' }, { r: 'مشارکت در کلاس', i: '🙋', c: 'participation' },
    { r: 'همکاریِ گروهی', i: '🤝', c: 'teamwork' }, { r: 'نظم و انضباط', i: '📏', c: 'discipline' },
    { r: 'تکلیفِ کامل', i: '📚', c: 'homework' }, { r: 'خلاقیت', i: '💡', c: 'creativity' },
    { r: 'کمک به دوستان', i: '💛', c: 'kindness' }, { r: 'پیشرفتِ عالی', i: '🚀', c: 'progress' },
];
const MINUS = [
    { r: 'بی‌نظمی در کلاس', i: '⚠️', c: 'discipline' }, { r: 'تکلیف انجام نشده', i: '📕', c: 'homework' },
    { r: 'تأخیر', i: '⏰', c: 'late' }, { r: 'رفتارِ نامناسب', i: '🚫', c: 'behavior' },
];
const QUICK_PLUS = [1, 2, 5, 10, 20, 50];
const QUICK_MINUS = [-1, -2, -5, -10];
const TYPES = [['game', '🎮 بازی'], ['exam', '📝 آزمون'], ['homework', '📚 تکلیف'], ['podcast', '🎧 پادکست'], ['online_exam', '💻 آزمون آنلاین'], ['custom', '⭐ فعالیت']];

export default function PointsCenter() {
    const P = usePage().props;
    const { classrooms = [], students = [], teams = [], batches = [], activities = [], flash, lastBatch = null } = P;
    const [tab, setTab] = useState(() => TABS.some((t) => t.k === P.tab) ? P.tab : (P.selected ? 'students' : P.teamKey ? 'teams' : 'give'));
    const [banner, setBanner] = useState(null);
    useEffect(() => {
        const m = flash?.flash;
        if (m) setBanner({ text: typeof m === 'string' ? m : m.message, undo: lastBatch, at: Date.now() });
    }, [flash, lastBatch]);
    const go = (k) => { setTab(k); try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch { /* */ } };

    // ─── وضعیتِ فرمِ امتیازدهی (بین زبانه‌ها مشترک است تا «دادن» از هر جا کار کند) ───
    const give = useForm({ student_ids: [], teams: [], whole_class: null, team_mode: 'each', amount: 5, reason: '', category: '', activity_id: null, repeat: false, notify: true });
    const preset = (patch) => { give.setData((d) => ({ ...d, ...patch })); go('give'); };

    const weekSum = students.reduce((a, s) => a + (s.week || 0), 0);
    const topTeam = teams.length ? [...teams].sort((a, b) => b.total - a.total)[0] : null;

    return (
        <DashLayout title="مرکزِ امتیاز" roleLabel="معلم" menu={teacherMenu} active="points">
            <div className="pc">
                <div className="pc-hero">
                    <div className="pc-hero-t">
                        <span className="pc-bolt">⚡</span>
                        <div>
                            <h2>مرکزِ امتیاز</h2>
                            <p>امتیاز به یک نفر، چند نفر، یک یا چند تیم یا کلِ کلاس — همه‌چیز قابلِ ویرایش و برگشت.</p>
                        </div>
                    </div>
                    <div className="pc-kpis">
                        <div><em>{fa(students.length)}</em><span>دانش‌آموز</span></div>
                        <div><em>{fa(teams.length)}</em><span>تیم</span></div>
                        <div><em>{sgn(weekSum)}</em><span>امتیازِ کلاس در {P.weekLabel || 'این هفته'}</span></div>
                        <div><em>{topTeam ? `${topTeam.emoji} ${topTeam.name}` : '—'}</em><span>تیمِ اول</span></div>
                    </div>
                </div>

                {banner && (
                    <div className="pc-banner" key={banner.at}>
                        <b>{banner.text}</b>
                        {banner.undo && (
                            <button className="btn btn-ghost btn-sm" onClick={() => router.delete(route('teacher.points.batch.destroy', banner.undo), { preserveScroll: true, onSuccess: () => setBanner(null) })}>↩️ برگردان</button>
                        )}
                        <button className="pc-x" onClick={() => setBanner(null)} aria-label="بستن">×</button>
                    </div>
                )}

                <div className="pc-tabs no-print" role="tablist">
                    {TABS.map((t) => (
                        <button key={t.k} role="tab" aria-selected={tab === t.k} className={tab === t.k ? 'on' : ''} onClick={() => go(t.k)}>
                            {t.t}
                            {t.k === 'history' && batches.length > 0 && <i>{fa(batches.length)}</i>}
                        </button>
                    ))}
                </div>

                {tab === 'give' && <GiveTab form={give} students={students} teams={teams} classrooms={classrooms} activities={activities} />}
                {tab === 'history' && <HistoryTab batches={batches} students={students} />}
                {tab === 'students' && <StudentsTab onGive={(id) => preset({ student_ids: [id], teams: [], whole_class: null })} />}
                {tab === 'teams' && <TeamsTab onGive={(key) => preset({ teams: [key], student_ids: [], whole_class: null })} onStudent={(id) => { setTab('students'); router.get(route('teacher.points'), { student: id, tab: 'students' }, { preserveState: true, preserveScroll: true, only: ['selected', 'ledger', 'trend'] }); }} />}
                {tab === 'rank' && <RankTab onPick={(id) => { setTab('students'); router.get(route('teacher.points'), { student: id, tab: 'students' }, { preserveState: true, preserveScroll: true, only: ['selected', 'ledger', 'trend'] }); }} />}
                {tab === 'activities' && <ActivitiesTab activities={activities} onGive={(a) => preset({ activity_id: a.id, amount: a.points, reason: `${a.type_label} — ${a.title}`, category: a.type, repeat: false })} />}
            </div>
        </DashLayout>
    );
}

/* ═════════════════════════ ۱) امتیازدهی ═════════════════════════ */
function GiveTab({ form, students, teams, classrooms, activities }) {
    const d = form.data;
    const [who, setWho] = useState('students');   // students | teams | class
    const [q, setQ] = useState('');
    const [teamFilter, setTeamFilter] = useState('');
    const ss = useSort(students, { name: (r) => firstName(r.name), family: (r) => lastName(r.name), total: 'total', week: 'week' }, { id: 'pc-give-students', key: 'name' });
    const shown = ss.sorted.filter((s) => (!q || norm(s.name).includes(norm(q))) && (!teamFilter || s.team_key === teamFilter));

    const toggle = (id) => form.setData('student_ids', d.student_ids.includes(id) ? d.student_ids.filter((x) => x !== id) : [...d.student_ids, id]);
    const toggleTeam = (k) => form.setData('teams', d.teams.includes(k) ? d.teams.filter((x) => x !== k) : [...d.teams, k]);
    const allShown = shown.length > 0 && shown.every((s) => d.student_ids.includes(s.id));
    const selectShown = () => form.setData('student_ids', allShown
        ? d.student_ids.filter((id) => !shown.some((s) => s.id === id))
        : [...new Set([...d.student_ids, ...shown.map((s) => s.id)])]);

    // چند نفر واقعاً امتیاز می‌گیرند؟
    const recipients = useMemo(() => {
        const set = new Set(d.student_ids);
        if (d.whole_class) students.filter((s) => s.classroom_id === d.whole_class).forEach((s) => set.add(s.id));
        if (d.team_mode === 'each') students.filter((s) => d.teams.includes(s.team_key)).forEach((s) => set.add(s.id));
        return set.size;
    }, [d.student_ids, d.teams, d.whole_class, d.team_mode, students]);
    const nothing = recipients === 0 && !(d.team_mode === 'team' && d.teams.length);
    const clearAll = () => form.setData((x) => ({ ...x, student_ids: [], teams: [], whole_class: null }));

    const amt = Number(d.amount) || 0;
    const reasons = amt < 0 ? MINUS : PLUS;
    const act = activities.find((a) => a.id === d.activity_id);
    const already = act && !d.repeat ? new Set(act.awarded_ids || []) : null;

    const submit = () => {
        const sent = { ...d };
        form.transform((x) => ({ ...x, amount: Number(x.amount), reason: x.reason || (amt >= 0 ? 'امتیازِ تشویقیِ معلم' : 'کسرِ امتیاز توسطِ معلم') }));
        form.post(route('teacher.points.give'), {
            preserveScroll: true,
            // فقط اگر در این فاصله انتخابِ تازه‌ای نشده، فرم پاک می‌شود
            onSuccess: () => form.setData((x) => (x.activity_id === sent.activity_id && x.whole_class === sent.whole_class && x.student_ids.length === sent.student_ids.length && x.teams.length === sent.teams.length
                ? { ...x, student_ids: [], teams: [], whole_class: null, activity_id: null, repeat: false, team_mode: 'each' } : x)),
        });
    };

    const teamsByClass = classrooms.length > 1;

    return (
        <div className="pc-give">
            {/* گامِ ۱ */}
            <section className="pc-step">
                <header><span className="pc-n">۱</span><b>به چه کسی؟</b>
                    {(d.student_ids.length > 0 || d.teams.length > 0 || d.whole_class) && <button className="pc-link" onClick={clearAll}>پاک کردنِ انتخاب‌ها</button>}
                </header>
                <div className="pc-seg">
                    <button className={who === 'students' ? 'on' : ''} onClick={() => setWho('students')}>🎓 دانش‌آموزان {d.student_ids.length > 0 && <i>{fa(d.student_ids.length)}</i>}</button>
                    <button className={who === 'teams' ? 'on' : ''} onClick={() => setWho('teams')}>🏆 تیم‌ها {d.teams.length > 0 && <i>{fa(d.teams.length)}</i>}</button>
                    <button className={who === 'class' ? 'on' : ''} onClick={() => setWho('class')}>👥 کلِ کلاس {d.whole_class && <i>✓</i>}</button>
                </div>

                {who === 'students' && (
                    <>
                        <div className="pc-tools">
                            <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 جست‌وجوی نام…" />
                            <select className="input" value={teamFilter} onChange={(e) => setTeamFilter(e.target.value)}>
                                <option value="">همه‌ی تیم‌ها</option>
                                {teams.map((t) => <option key={t.key} value={t.key}>{t.emoji} {t.name}{teamsByClass ? ` (${t.classroom})` : ''}</option>)}
                            </select>
                            <button className="btn btn-ghost btn-sm" onClick={selectShown} disabled={!shown.length}>{allShown ? '☐ برداشتنِ همه' : '☑️ انتخابِ همه'}{q || teamFilter ? ' (فیلترشده)' : ''}</button>
                        </div>
                        {students.length > 1 && <SortBar s={ss} options={[['name', 'نام'], ['family', 'نام خانوادگی'], ['total', 'امتیاز', 'desc'], ['week', 'این هفته', 'desc']]} />}
                        <div className="pc-grid">
                            {shown.map((s) => {
                                const on = d.student_ids.includes(s.id);
                                const got = already?.has(s.id);
                                return (
                                    <button key={s.id} className={`pc-stu ${on ? 'on' : ''} ${got ? 'got' : ''}`} onClick={() => toggle(s.id)} title={got ? 'امتیازِ این فعالیت را قبلاً گرفته' : ''}>
                                        <span className="pc-check">{on ? '✓' : ''}</span>
                                        <Avatar src={s.avatar} name={s.name} size={38} />
                                        <span className="pc-stu-t"><b>{s.name}</b><small>{s.emoji ? `${s.emoji} ${s.team}` : 'بدونِ تیم'}</small></span>
                                        <span className="pc-xp">⚡{fa(s.total)}</span>
                                        {got && <span className="pc-got">گرفته</span>}
                                    </button>
                                );
                            })}
                            {!shown.length && <p className="pc-muted">دانش‌آموزی پیدا نشد.</p>}
                        </div>
                    </>
                )}

                {who === 'teams' && (
                    <>
                        {teams.length === 0 && <p className="pc-muted">هنوز تیمی ساخته نشده؛ در «دانش‌آموزان» برای بچه‌ها تیم (تم) انتخاب کنید.</p>}
                        <div className="pc-teams">
                            {teams.map((t) => {
                                const on = d.teams.includes(t.key);
                                return (
                                    <button key={t.key} className={`pc-team ${on ? 'on' : ''}`} style={{ '--tc': t.color }} onClick={() => toggleTeam(t.key)}>
                                        <span className="pc-check">{on ? '✓' : ''}</span>
                                        <span className="pc-team-e">{t.emoji}</span>
                                        <b>{t.name}</b>
                                        <small>{fa(t.count)} عضو{teamsByClass ? ` · ${t.classroom}` : ''}</small>
                                        <span className="pc-xp">⚡{fa(t.total)}</span>
                                    </button>
                                );
                            })}
                        </div>
                        {teams.length > 0 && (
                            <div className="pc-mode">
                                <label className={d.team_mode === 'each' ? 'on' : ''}>
                                    <input type="radio" checked={d.team_mode === 'each'} onChange={() => form.setData('team_mode', 'each')} />
                                    <span><b>👥 به هر عضوِ تیم</b><small>امتیاز به حسابِ تک‌تکِ اعضا اضافه می‌شود و امتیازِ تیم هم به همان اندازه بالا می‌رود (پیشنهادی).</small></span>
                                </label>
                                <label className={d.team_mode === 'team' ? 'on' : ''}>
                                    <input type="radio" checked={d.team_mode === 'team'} onChange={() => form.setData('team_mode', 'team')} />
                                    <span><b>🏆 فقط امتیازِ تیم</b><small>یک امتیازِ تشویقی برای خودِ تیم؛ به امتیازِ شخصیِ اعضا اضافه نمی‌شود.</small></span>
                                </label>
                            </div>
                        )}
                    </>
                )}

                {who === 'class' && (
                    <div className="pc-teams">
                        {classrooms.map((c) => {
                            const on = d.whole_class === c.id;
                            return (
                                <button key={c.id} className={`pc-team ${on ? 'on' : ''}`} onClick={() => form.setData('whole_class', on ? null : c.id)}>
                                    <span className="pc-check">{on ? '✓' : ''}</span>
                                    <span className="pc-team-e">🏫</span>
                                    <b>{c.name}</b>
                                    <small>{fa(c.count)} دانش‌آموز</small>
                                </button>
                            );
                        })}
                    </div>
                )}
                {form.errors.targets && <div className="pc-err">{form.errors.targets}</div>}
            </section>

            {/* گامِ ۲ */}
            <section className="pc-step">
                <header><span className="pc-n">۲</span><b>چقدر؟</b></header>
                <div className="pc-amount">
                    <button className="pc-stepper" onClick={() => form.setData('amount', amt - 1 === 0 ? -1 : amt - 1)} aria-label="کم کن">−</button>
                    <div className={`pc-big ${amt < 0 ? 'neg' : ''}`}>
                        <input type="number" dir="ltr" value={d.amount} onChange={(e) => form.setData('amount', e.target.value)} aria-label="مقدارِ امتیاز" />
                        <small>{amt < 0 ? 'کسرِ امتیاز' : 'امتیازِ مثبت'}</small>
                    </div>
                    <button className="pc-stepper" onClick={() => form.setData('amount', amt + 1 === 0 ? 1 : amt + 1)} aria-label="زیاد کن">+</button>
                </div>
                <div className="pc-chips">
                    {QUICK_PLUS.map((v) => <button key={v} className={`pc-chip plus ${amt === v ? 'on' : ''}`} onClick={() => form.setData('amount', v)}>+{fa(v)}</button>)}
                    {QUICK_MINUS.map((v) => <button key={v} className={`pc-chip minus ${amt === v ? 'on' : ''}`} onClick={() => form.setData('amount', v)}>−{fa(-v)}</button>)}
                </div>
                {form.errors.amount && <div className="pc-err">{form.errors.amount}</div>}
            </section>

            {/* گامِ ۳ */}
            <section className="pc-step">
                <header><span className="pc-n">۳</span><b>برای چه؟</b></header>
                <div className="pc-chips">
                    {reasons.map((r) => (
                        <button key={r.r} className={`pc-chip ${d.reason === r.r ? 'on' : ''}`} onClick={() => form.setData((x) => ({ ...x, reason: r.r, category: r.c }))}>{r.i} {r.r}</button>
                    ))}
                </div>
                <input className="input" value={d.reason} maxLength={160} onChange={(e) => form.setData('reason', e.target.value)} placeholder="یا علت را بنویسید… (مثلاً: حلِ مسئله‌ی پای تخته)" />
                {form.errors.reason && <div className="pc-err">{form.errors.reason}</div>}

                {activities.length > 0 && (
                    <div className="pc-act">
                        <label>🏅 از فعالیتِ آماده (اختیاری)</label>
                        <select className="input" value={d.activity_id || ''} onChange={(e) => {
                            const a = activities.find((x) => x.id === Number(e.target.value));
                            form.setData((x) => (a ? { ...x, activity_id: a.id, amount: a.points, reason: `${a.type_label} — ${a.title}`, category: a.type } : { ...x, activity_id: null }));
                        }}>
                            <option value="">— بدونِ فعالیت —</option>
                            {activities.map((a) => <option key={a.id} value={a.id}>{a.type_label} — {a.title} ({fa(a.points)})</option>)}
                        </select>
                        {act && (
                            <label className="pc-toggle">
                                <input type="checkbox" checked={!!d.repeat} onChange={(e) => form.setData('repeat', e.target.checked)} />
                                اجازه‌ی تکرار (به کسانی که قبلاً گرفته‌اند هم دوباره بده) — {fa(act.awarded)} نفر قبلاً گرفته‌اند
                            </label>
                        )}
                    </div>
                )}
                <label className="pc-toggle">
                    <input type="checkbox" checked={!!d.notify} onChange={(e) => form.setData('notify', e.target.checked)} />
                    🔔 برای دانش‌آموزان اعلان برود
                </label>
            </section>

            {/* نوارِ ثبت */}
            <div className="pc-submit">
                <div className="pc-sum">
                    {nothing ? <span>هنوز کسی انتخاب نشده</span> : (
                        <span>
                            {d.team_mode === 'team' && d.teams.length > 0 && <b>{fa(d.teams.length)} تیم</b>}
                            {d.team_mode === 'team' && d.teams.length > 0 && recipients > 0 && ' + '}
                            {recipients > 0 && <b>{fa(recipients)} دانش‌آموز</b>}
                        </span>
                    )}
                    <em className={amt < 0 ? 'neg' : ''}>{sgn(amt)}</em>
                </div>
                <button className="btn pc-go" disabled={form.processing || nothing || !amt} onClick={submit}>
                    {form.processing ? 'در حالِ ثبت…' : amt < 0 ? '➖ ثبتِ کسرِ امتیاز' : '⭐ ثبتِ امتیاز'}
                </button>
            </div>
        </div>
    );
}

/* ═════════════════════════ ۲) سوابق ═════════════════════════ */
function HistoryTab({ batches, students }) {
    const [q, setQ] = useState('');
    const [edit, setEdit] = useState(null);
    const [open, setOpen] = useState({});
    const hs = useSort(batches, { date: 'ts', amount: 'amount', reason: 'reason', count: (b) => b.students.length + b.teams.length }, { id: 'pc-history', key: 'date', dir: 'desc', firstDir: { date: 'desc', amount: 'desc', count: 'desc' } });
    const rows = hs.sorted.filter((b) => !q || norm(`${b.reason} ${b.label} ${b.students.map((s) => s.name).join(' ')}`).includes(norm(q)));
    const del = (b) => { if (confirm(`نوبتِ «${b.reason}» حذف شود؟ امتیازِ همه‌ی گیرنده‌ها (${fa(b.students.length)} نفر${b.teams.length ? ` و ${fa(b.teams.length)} تیم` : ''}) پس گرفته می‌شود.`)) router.delete(route('teacher.points.batch.destroy', b.id), { preserveScroll: true }); };
    const dropOne = (b, s) => { if (confirm(`امتیازِ این نوبت از «${s.name}» پس گرفته شود؟`)) router.delete(route('teacher.points.batch.student', [b.id, s.id]), { preserveScroll: true }); };

    return (
        <div className="panel pc-panel">
            <div className="pc-head">
                <h3>🧾 سوابقِ امتیازدهی</h3>
                <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 جست‌وجو در علت یا نام…" />
            </div>
            {batches.length > 1 && <SortBar s={hs} options={[['date', 'تاریخ', 'desc'], ['amount', 'مقدار', 'desc'], ['count', 'تعدادِ گیرنده', 'desc'], ['reason', 'علت']]} />}
            {batches.length === 0 && <div className="pc-empty">🧾 هنوز از «مرکزِ امتیاز» امتیازی داده نشده. هر امتیازدهی اینجا ثبت می‌شود و می‌توانید ویرایش یا برگردانش کنید.</div>}
            <div className="pc-list">
                {rows.map((b) => (
                    <div key={b.id} className="pc-batch">
                        <div className="pc-batch-h">
                            <span className={`pc-amt ${b.amount < 0 ? 'neg' : ''}`}>{sgn(b.amount)}</span>
                            <div className="pc-batch-t">
                                <b>{b.reason}</b>
                                <small>{b.label || '—'} · {b.date}{b.mode === 'team' && b.teams.length ? ' · فقط امتیازِ تیم' : ''}</small>
                            </div>
                            <div className="pc-actions">
                                <button className="btn btn-ghost btn-sm" onClick={() => setEdit(edit === b.id ? null : b.id)}>✏️ ویرایش</button>
                                <button className="btn btn-ghost btn-sm pc-danger" onClick={() => del(b)}>↩️ حذف</button>
                            </div>
                        </div>
                        <div className="pc-recips">
                            {b.teams.map((t) => <span key={`t${t.id}`} className="pc-pill team">{t.emoji} {t.name}</span>)}
                            {(open[b.id] ? b.students : b.students.slice(0, 8)).map((s) => (
                                <span key={s.id} className="pc-pill">{s.name}<button onClick={() => dropOne(b, s)} title="پس گرفتن از این دانش‌آموز" aria-label="حذف">×</button></span>
                            ))}
                            {b.students.length > 8 && <button className="pc-link" onClick={() => setOpen((o) => ({ ...o, [b.id]: !o[b.id] }))}>{open[b.id] ? 'کمتر' : `+${fa(b.students.length - 8)} نفرِ دیگر`}</button>}
                        </div>
                        {edit === b.id && <BatchEditor b={b} students={students} onDone={() => setEdit(null)} />}
                    </div>
                ))}
            </div>
        </div>
    );
}

function BatchEditor({ b, students, onDone }) {
    const f = useForm({ amount: b.amount, reason: b.reason, category: b.category || '', student_ids: b.students.map((s) => s.id) });
    const [q, setQ] = useState('');
    const toggle = (id) => f.setData('student_ids', f.data.student_ids.includes(id) ? f.data.student_ids.filter((x) => x !== id) : [...f.data.student_ids, id]);
    const list = students.filter((s) => !q || norm(s.name).includes(norm(q)));
    return (
        <div className="pc-editor">
            <div className="pc-editor-row">
                <div className="field"><label>مقدار</label><input type="number" dir="ltr" className="input" value={f.data.amount} onChange={(e) => f.setData('amount', e.target.value)} /></div>
                <div className="field" style={{ flex: 1 }}><label>علت</label><input className="input" value={f.data.reason} onChange={(e) => f.setData('reason', e.target.value)} /></div>
            </div>
            {(f.errors.amount || f.errors.reason) && <div className="pc-err">{f.errors.amount || f.errors.reason}</div>}
            {b.mode !== 'team' || b.students.length > 0 ? (
                <>
                    <div className="pc-editor-row">
                        <b style={{ fontSize: 13 }}>گیرنده‌ها ({fa(f.data.student_ids.length)} نفر) — برای افزودن/برداشتن بزنید:</b>
                        <input className="input" style={{ maxWidth: 200 }} value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 نام…" />
                    </div>
                    <div className="pc-recips">
                        {list.map((s) => (
                            <button key={s.id} className={`pc-pill sel ${f.data.student_ids.includes(s.id) ? 'on' : ''}`} onClick={() => toggle(s.id)}>{f.data.student_ids.includes(s.id) ? '✓ ' : ''}{s.name}</button>
                        ))}
                    </div>
                </>
            ) : null}
            <div className="pc-editor-row">
                <button className="btn btn-sm" disabled={f.processing} onClick={() => f.put(route('teacher.points.batch.update', b.id), { preserveScroll: true, onSuccess: onDone })}>💾 ذخیره‌ی تغییرات</button>
                <button className="btn btn-ghost btn-sm" onClick={onDone}>انصراف</button>
            </div>
        </div>
    );
}

/* ═════════════════════════ ۳) دانش‌آموزان ═════════════════════════ */
function StudentsTab({ onGive }) {
    const { students = [], selected, ledger = [], trend } = usePage().props;
    const [q, setQ] = useState('');
    const ss = useSort(students, { name: (r) => firstName(r.name), family: (r) => lastName(r.name), total: 'total', week: 'week', team: 'team' }, { id: 'pc-students', key: 'name' });
    const ls = useSort(ledger, { amount: 'amount', date: 'date_raw', reason: 'reason' }, { id: 'pc-ledger', key: 'date', dir: 'desc', firstDir: { amount: 'desc', date: 'desc' } });
    const keep = { preserveState: true, preserveScroll: true, only: ['selected', 'ledger', 'trend'] };
    const pick = (id) => router.get(route('teacher.points'), { student: id, tab: 'students' }, keep);

    const [sel, setSel] = useState([]);
    useEffect(() => { setSel([]); }, [selected?.id, ledger.length]);
    const [edit, setEdit] = useState(null);
    const [ev, setEv] = useState({ amount: 0, reason: '' });
    const quick = (amount) => router.post(route('teacher.points.give'), { student_ids: [selected.id], amount, reason: amount > 0 ? 'امتیازِ تشویقیِ معلم' : 'کسرِ امتیاز توسطِ معلم', notify: true }, { preserveScroll: true });
    const saveEdit = (id) => router.put(route('teacher.points.entry.update', id), { amount: Number(ev.amount), reason: ev.reason }, { preserveScroll: true, onSuccess: () => setEdit(null) });
    const delEntry = (id) => { if (confirm('این ردیفِ امتیاز حذف شود؟')) router.delete(route('teacher.points.entry.destroy', id), { preserveScroll: true }); };
    const delSel = () => { if (sel.length && confirm(`${fa(sel.length)} ردیفِ انتخاب‌شده حذف شود؟`)) router.post(route('teacher.points.destroyMany'), { ids: sel }, { preserveScroll: true }); };
    const clearAll = () => { if (confirm(`کلِ سابقه‌ی امتیازِ «${selected.name}» پاک شود؟ این کار برگشت‌پذیر نیست.`)) router.post(route('teacher.points.clear'), { student_id: selected.id }, { preserveScroll: true }); };
    const list = ss.sorted.filter((s) => !q || norm(s.name).includes(norm(q)));

    return (
        <div className="pc-split">
            <div className="panel pc-panel pc-side">
                <h3>🎓 دانش‌آموزان</h3>
                <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 جست‌وجو…" />
                {students.length > 1 && <SortBar s={ss} options={[['name', 'نام'], ['family', 'نام خانوادگی'], ['total', 'امتیاز', 'desc'], ['week', 'این هفته', 'desc'], ['team', 'تیم']]} />}
                <div className="pc-rows">
                    {list.map((s) => (
                        <button key={s.id} className={`pc-row ${selected?.id === s.id ? 'on' : ''}`} onClick={() => pick(s.id)}>
                            <Avatar src={s.avatar} name={s.name} size={32} />
                            <span className="pc-row-t"><b>{s.name}</b><small>{s.emoji ? `${s.emoji} ${s.team}` : 'بدونِ تیم'}{s.week ? ` · این هفته ${sgn(s.week)}` : ''}</small></span>
                            <span className="pc-xp">⚡{fa(s.total)}</span>
                        </button>
                    ))}
                </div>
            </div>

            <div className="panel pc-panel">
                {!selected ? (
                    <div className="pc-empty">👈 یک دانش‌آموز را انتخاب کنید تا سابقه، نمودار و ابزارِ ویرایشِ امتیازش را ببینید.</div>
                ) : (
                    <>
                        <div className="pc-who">
                            <Avatar src={selected.avatar} name={selected.name} size={54} />
                            <div><h3>{selected.name}</h3><small>{selected.emoji ? `${selected.emoji} ${selected.team}` : 'بدونِ تیم'}</small></div>
                            <div className="pc-who-k"><em>⚡{fa(selected.total)}</em><span>کلِ امتیاز</span></div>
                            <div className="pc-who-k"><em>{sgn(selected.week)}</em><span>این هفته</span></div>
                        </div>
                        <div className="pc-chips">
                            {[1, 2, 5, 10, 20].map((v) => <button key={v} className="pc-chip plus" onClick={() => quick(v)}>+{fa(v)}</button>)}
                            {[-1, -5, -10].map((v) => <button key={v} className="pc-chip minus" onClick={() => quick(v)}>−{fa(-v)}</button>)}
                            <button className="pc-chip" onClick={() => onGive(selected.id)}>✍️ امتیاز با علت…</button>
                        </div>

                        {trend && <div style={{ marginTop: 12 }}><PointsTrend data={trend} title={`📊 روندِ امتیاز و رتبه‌ی ${selected.name}`} /></div>}

                        <div className="pc-head" style={{ marginTop: 14 }}>
                            <h3>📜 سابقه‌ی امتیاز ({fa(ledger.length)})</h3>
                            {ledger.length > 0 && <label className="pc-toggle" style={{ margin: 0 }}><input type="checkbox" checked={sel.length === ledger.length} onChange={(e) => setSel(e.target.checked ? ledger.map((x) => x.id) : [])} /> همه</label>}
                            {sel.length > 0 && <button className="btn btn-sm pc-dangerbtn" onClick={delSel}>🗑️ حذفِ {fa(sel.length)} ردیف</button>}
                            <button className="btn btn-ghost btn-sm pc-danger" onClick={clearAll} style={{ marginInlineStart: 'auto' }}>پاک‌کردنِ کلِ سابقه</button>
                        </div>
                        {ledger.length > 1 && <SortBar s={ls} options={[['date', 'تاریخ', 'desc'], ['amount', 'مقدار', 'desc'], ['reason', 'علت']]} />}
                        {ledger.length === 0 && <p className="pc-muted">سابقه‌ای ثبت نشده است.</p>}
                        <div className="pc-list">
                            {ls.sorted.map((e) => (
                                <div key={e.id} className={`pc-entry ${sel.includes(e.id) ? 'sel' : ''}`}>
                                    <input type="checkbox" checked={sel.includes(e.id)} onChange={() => setSel((s) => s.includes(e.id) ? s.filter((x) => x !== e.id) : [...s, e.id])} />
                                    {edit === e.id ? (
                                        <>
                                            <input type="number" dir="ltr" className="input pc-mini" value={ev.amount} onChange={(x) => setEv({ ...ev, amount: x.target.value })} />
                                            <input className="input" style={{ flex: 1 }} value={ev.reason} onChange={(x) => setEv({ ...ev, reason: x.target.value })} />
                                            <button className="btn btn-sm" onClick={() => saveEdit(e.id)}>💾</button>
                                            <button className="btn btn-ghost btn-sm" onClick={() => setEdit(null)}>✕</button>
                                        </>
                                    ) : (
                                        <>
                                            <span className={`pc-amt sm ${e.amount < 0 ? 'neg' : ''}`}>{sgn(e.amount)}</span>
                                            <span className="pc-entry-t">{e.reason}{e.batch && <small> · نوبتِ گروهی</small>}</span>
                                            <small className="pc-date">{e.date}</small>
                                            <button className="btn btn-ghost btn-sm" onClick={() => { setEdit(e.id); setEv({ amount: e.amount, reason: e.reason || '' }); }} title="ویرایش">✏️</button>
                                            <button className="btn btn-ghost btn-sm pc-danger" onClick={() => delEntry(e.id)} title="حذف">🗑️</button>
                                        </>
                                    )}
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

/* ═════════════════════════ ۴) تیم‌ها ═════════════════════════ */
function TeamsTab({ onGive, onStudent }) {
    const { teams = [], teamKey, teamLedger = [], classrooms = [] } = usePage().props;
    const ts = useSort(teams, { total: 'total', name: 'name', count: 'count', bonus: 'bonus' }, { id: 'pc-teams', key: 'total', dir: 'desc', firstDir: { total: 'desc', count: 'desc', bonus: 'desc' } });
    const ranked = [...teams].sort((a, b) => b.total - a.total).map((t) => t.key);
    const sel = teams.find((t) => t.key === teamKey);
    const pick = (k) => router.get(route('teacher.points'), { team: k, tab: 'teams' }, { preserveState: true, preserveScroll: true, only: ['teamKey', 'teamLedger'] });
    const [edit, setEdit] = useState(null);
    const [ev, setEv] = useState({ amount: 0, reason: '' });
    const max = Math.max(1, ...teams.map((t) => t.total));

    if (!teams.length) return <div className="panel pc-panel"><div className="pc-empty">🏆 هنوز تیمی نیست. در «دانش‌آموزان» برای هر دانش‌آموز یک تیم (تم) انتخاب کنید تا تیم‌ها اینجا ساخته شوند.</div></div>;

    return (
        <div className="pc-split">
            <div className="panel pc-panel pc-side">
                <h3>🏆 جدولِ تیم‌ها</h3>
                <SortBar s={ts} options={[['total', 'امتیاز', 'desc'], ['name', 'نامِ تیم'], ['count', 'تعدادِ عضو', 'desc'], ['bonus', 'امتیازِ تیمی', 'desc']]} />
                <div className="pc-rows">
                    {ts.sorted.map((t) => {
                        const r = ranked.indexOf(t.key) + 1;
                        return (
                            <button key={t.key} className={`pc-row pc-trow ${teamKey === t.key ? 'on' : ''}`} onClick={() => pick(t.key)} style={{ '--tc': t.color }}>
                                <span className="pc-rank">{r === 1 ? '🥇' : r === 2 ? '🥈' : r === 3 ? '🥉' : fa(r)}</span>
                                <span className="pc-team-e sm">{t.emoji}</span>
                                <span className="pc-row-t"><b>{t.name}</b><small>{fa(t.count)} عضو{t.bonus ? ` · تیمی ${sgn(t.bonus)}` : ''}{classrooms.length > 1 ? ` · ${t.classroom}` : ''}</small>
                                    <span className="pc-bar"><i style={{ width: `${Math.max(4, (t.total / max) * 100)}%` }} /></span>
                                </span>
                                <span className="pc-xp">⚡{fa(t.total)}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="panel pc-panel">
                {!sel ? (
                    <div className="pc-empty">👈 یک تیم را انتخاب کنید تا اعضا، امتیاز و دفترِ ریزش را ببینید.</div>
                ) : (
                    <>
                        <div className="pc-who" style={{ '--tc': sel.color }}>
                            <span className="pc-team-e lg">{sel.emoji}</span>
                            <div><h3>{sel.name}</h3><small>{fa(sel.count)} عضو</small></div>
                            <div className="pc-who-k"><em>⚡{fa(sel.total)}</em><span>امتیازِ تیم</span></div>
                            <div className="pc-who-k"><em>{fa(sel.members_xp)}</em><span>جمعِ اعضا</span></div>
                            <div className="pc-who-k"><em>{sgn(sel.bonus)}</em><span>امتیازِ تیمی</span></div>
                        </div>
                        <p className="pc-muted">امتیازِ تیم = جمعِ امتیازِ اعضا + امتیازهای «فقط تیم».</p>
                        <button className="btn btn-sm" onClick={() => onGive(sel.key)}>⭐ امتیاز به این تیم</button>

                        <h4 className="pc-sub">👥 اعضا</h4>
                        <div className="pc-members">
                            {sel.members.map((m) => (
                                <button key={m.id} className="pc-member" onClick={() => onStudent(m.id)} title="مدیریتِ امتیازِ این دانش‌آموز">
                                    <Avatar name={m.name} size={30} /><b>{m.name}</b><span className="pc-xp">⚡{fa(m.xp)}</span>
                                </button>
                            ))}
                        </div>

                        <h4 className="pc-sub">📜 دفترِ ریزِ تیم</h4>
                        {teamLedger.length === 0 && <p className="pc-muted">هنوز ردیفی نیست.</p>}
                        <div className="pc-list">
                            {teamLedger.map((e, i) => (
                                <div key={`${e.kind}${e.id || i}`} className={`pc-entry ${e.kind === 'team' ? 'teamrow' : ''}`}>
                                    {edit === e.id && e.kind === 'team' ? (
                                        <>
                                            <input type="number" dir="ltr" className="input pc-mini" value={ev.amount} onChange={(x) => setEv({ ...ev, amount: x.target.value })} />
                                            <input className="input" style={{ flex: 1 }} value={ev.reason} onChange={(x) => setEv({ ...ev, reason: x.target.value })} />
                                            <button className="btn btn-sm" onClick={() => router.put(route('teacher.groups.entry.update', e.id), { amount: Number(ev.amount), reason: ev.reason }, { preserveScroll: true, onSuccess: () => setEdit(null) })}>💾</button>
                                            <button className="btn btn-ghost btn-sm" onClick={() => setEdit(null)}>✕</button>
                                        </>
                                    ) : (
                                        <>
                                            <span className={`pc-amt sm ${e.amount < 0 ? 'neg' : ''}`}>{sgn(e.amount)}</span>
                                            <span className="pc-entry-t"><b>{e.who}</b> — {e.reason}</span>
                                            <small className="pc-date">{e.date}</small>
                                            {e.kind === 'team' && e.id && (
                                                <>
                                                    <button className="btn btn-ghost btn-sm" onClick={() => { setEdit(e.id); setEv({ amount: e.amount, reason: (e.reason || '').replace(/^🏆 امتیازِ گروهی( — )?/, '') }); }}>✏️</button>
                                                    <button className="btn btn-ghost btn-sm pc-danger" onClick={() => { if (confirm('این امتیازِ تیمی حذف شود؟')) router.delete(route('teacher.groups.entry.destroy', e.id), { preserveScroll: true }); }}>🗑️</button>
                                                </>
                                            )}
                                        </>
                                    )}
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

/* ═════════════════════════ ۵) رتبه‌بندی ═════════════════════════ */
function RankTab({ onPick }) {
    const { rank = [], range = {}, weeks = [], months = [], yearLabel = '', classroom = '' } = usePage().props;
    const [preset, setPreset] = useState(range.preset || 'week');
    const [from, setFrom] = useState(range.from || '');
    const [to, setTo] = useState(range.to || '');
    const rs = useSort(rank, {
        rank: 'rank', name: (r) => firstName(r.name), family: (r) => lastName(r.name),
        xp: 'xp', plus: 'plus', minus: 'minus', entries: 'entries', team: 'team', last: 'last_raw',
    }, { key: 'rank', id: 'teacher-points-rank', firstDir: { xp: 'desc', plus: 'desc', minus: 'desc', entries: 'desc', last: 'desc' } });
    const apply = (p, f, t) => {
        setPreset(p); setFrom(f || ''); setTo(t || '');
        router.get(route('teacher.points'), { tab: 'rank', range: p, from: f || undefined, to: t || undefined }, { preserveState: true, preserveScroll: true, only: ['rank', 'range'] });
    };
    const printUrl = `${route('teacher.points.print')}?range=${preset}${from ? `&from=${from}` : ''}${to ? `&to=${to}` : ''}`;
    return <PointsRankPanel rank={rank} rs={rs} range={range} preset={preset} from={from} to={to} weeks={weeks} months={months}
        yearLabel={yearLabel} classroom={classroom} onApply={apply} printUrl={printUrl} onPick={onPick} />;
}

/* ═════════════════════════ ۶) فعالیت‌های آماده ═════════════════════════ */
function ActivitiesTab({ activities, onGive }) {
    const form = useForm({ type: 'custom', title: '', points: 10, scheduled_at: '', description: '' });
    const [edit, setEdit] = useState(null);
    const ef = useForm({ type: 'custom', title: '', points: 10, description: '', scheduled_at: '' });
    const as = useSort(activities, { created: 'created_raw', title: 'title', points: 'points', awarded: 'awarded' }, { id: 'pc-activities', key: 'created', dir: 'desc', firstDir: { created: 'desc', points: 'desc', awarded: 'desc' } });
    const del = (a) => { if (confirm(`فعالیتِ «${a.title}» و همه‌ی امتیازهایی که بابتش داده شده حذف شود؟`)) router.delete(route('teacher.activities.destroy', a.id), { preserveScroll: true }); };

    return (
        <div className="pc-split">
            <form className="panel pc-panel pc-side" onSubmit={(e) => { e.preventDefault(); form.post(route('teacher.activities.store'), { preserveScroll: true, onSuccess: () => form.reset() }); }}>
                <h3>➕ فعالیتِ آماده‌ی جدید</h3>
                <p className="pc-muted">فعالیت‌های پرتکرار (بازیِ شنبه، تکلیفِ هفتگی…) را یک‌بار بسازید و بعد با یک کلیک به هر کس خواستید امتیازش را بدهید؛ از امتیازِ تکراری هم جلوگیری می‌شود.</p>
                <div className="pc-chips">
                    {TYPES.map(([v, l]) => <button type="button" key={v} className={`pc-chip ${form.data.type === v ? 'on' : ''}`} onClick={() => form.setData('type', v)}>{l}</button>)}
                </div>
                <div className="field"><label>عنوان</label><input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: بازیِ ریاضیِ شنبه" />{form.errors.title && <div className="pc-err">{form.errors.title}</div>}</div>
                <div className="pc-editor-row">
                    <div className="field" style={{ flex: 1 }}><label>امتیاز</label><input type="number" min="1" max="1000" dir="ltr" className="input" value={form.data.points} onChange={(e) => form.setData('points', e.target.value)} /></div>
                    <div className="field" style={{ flex: 1 }}><label>تاریخ (اختیاری)</label><JalaliDatePicker value={form.data.scheduled_at} onChange={(v) => form.setData('scheduled_at', v)} /></div>
                </div>
                <button className="btn" style={{ width: '100%' }} disabled={form.processing}>ثبتِ فعالیت</button>
            </form>

            <div className="panel pc-panel">
                <h3>🏅 فعالیت‌ها</h3>
                {activities.length > 1 && <SortBar s={as} options={[['created', 'زمانِ ثبت', 'desc'], ['title', 'عنوان'], ['points', 'امتیاز', 'desc'], ['awarded', 'تعدادِ گیرنده', 'desc']]} />}
                {activities.length === 0 && <div className="pc-empty">هنوز فعالیتی نساخته‌اید.</div>}
                <div className="pc-list">
                    {as.sorted.map((a) => (
                        <div key={a.id} className="pc-batch">
                            <div className="pc-batch-h">
                                <span className="pc-amt">{sgn(a.points)}</span>
                                <div className="pc-batch-t"><b>{a.type_label} — {a.title}</b><small>{fa(a.awarded)} نفر گرفته‌اند{a.scheduled ? ` · 📅 ${a.scheduled}` : ''}</small></div>
                                <div className="pc-actions">
                                    <button className="btn btn-sm" onClick={() => onGive(a)}>🎁 دادن</button>
                                    <button className="btn btn-ghost btn-sm" onClick={() => { setEdit(edit === a.id ? null : a.id); ef.setData({ type: a.type, title: a.title, points: a.points, description: a.description || '', scheduled_at: '' }); }}>✏️</button>
                                    <button className="btn btn-ghost btn-sm pc-danger" onClick={() => del(a)}>🗑️</button>
                                </div>
                            </div>
                            {edit === a.id && (
                                <div className="pc-editor">
                                    <div className="pc-editor-row">
                                        <select className="input" value={ef.data.type} onChange={(e) => ef.setData('type', e.target.value)}>{TYPES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
                                        <input className="input" style={{ flex: 1 }} value={ef.data.title} onChange={(e) => ef.setData('title', e.target.value)} />
                                        <input type="number" dir="ltr" className="input pc-mini" value={ef.data.points} onChange={(e) => ef.setData('points', e.target.value)} />
                                    </div>
                                    <p className="pc-muted">تغییرِ امتیازِ فعالیت فقط برای امتیازدهی‌های بعدی است؛ امتیازهای داده‌شده را از «سوابق» ویرایش کنید.</p>
                                    <div className="pc-editor-row">
                                        <button className="btn btn-sm" disabled={ef.processing} onClick={() => ef.put(route('teacher.activities.update', a.id), { preserveScroll: true, onSuccess: () => setEdit(null) })}>💾 ذخیره</button>
                                        <button className="btn btn-ghost btn-sm" onClick={() => setEdit(null)}>انصراف</button>
                                    </div>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
