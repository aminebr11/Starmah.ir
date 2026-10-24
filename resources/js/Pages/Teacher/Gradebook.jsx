import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';
import { MasteryTag } from '@/Components/MasteryPanel';
import { useSort, SortBar, firstName, lastName } from '@/lib/useSort';
import { useChapters } from '@/Components/Questions/QuestionChapter';
import '@/../css/gradebook.css';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const TYPES = [
    { v: 'descriptive', t: '📝 توصیفی' },
    { v: 'numeric', t: '🔢 عددی' },
    { v: 'homework', t: '📚 تکلیف' },
];
// رنگ هر ارزیابی
const RATING_COLOR = {
    'خیلی خوب': ['#e3f7ec', '#177a45'], 'خوب': ['#e7f0ff', '#1b4b8a'], 'قابل قبول': ['#fff3d6', '#8a5a00'],
    'نیاز به تلاش': ['#ffe9d6', '#a04413'], 'غایب': ['#fdecec', '#c0392b'],
    'کامل': ['#e3f7ec', '#177a45'], 'ناقص': ['#fff3d6', '#8a5a00'], 'انجام نداده': ['#fdecec', '#c0392b'],
};
const ICON = { 'غایب': '🚫', 'کامل': '✅', 'ناقص': '⚠️', 'انجام نداده': '❌' };

export default function Gradebook() {
    const { classroom, subjects = [], students = [], activities = [], descriptiveOptions = [], homeworkOptions = [], flash, highlight, impact } = usePage().props;
    // ترتیبِ دانش‌آموزان در همه‌ی جدول‌های دفتر (نام / نام خانوادگی)
    const ss = useSort(students, { name: (r) => firstName(r.name), family: (r) => lastName(r.name) }, { key: 'family', id: 'gradebook-students' });
    const ordered = ss.sorted;
    const [banner, setBanner] = useState(null);
    const [tab, setTab] = useState(highlight ? 'history' : 'new'); // new | history
    // پس از ثبت/ویرایش، کارتِ همان فعالیت در سوابق برجسته و دیده می‌شود
    const hiRef = useRef(null);
    useEffect(() => { if (highlight) { setTab('history'); setOpenCards((o) => ({ ...o, [highlight]: true })); setTimeout(() => hiRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 160); } }, [highlight]);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    // فرم فعالیت جدید
    const blank = () => Object.fromEntries(students.map((s) => [s.id, { score: '', text: '', feedback: '' }]));
    const form = useForm({ title: '', score_type: 'descriptive', lesson: '', topic: '', chapter_id: '', max: 20, date: '', grades: {} });
    const [rows, setRows] = useState(blank());
    useEffect(() => { setRows(blank()); }, [students.length]);

    const setRow = (sid, patch) => setRows((r) => ({ ...r, [sid]: { ...r[sid], ...patch } }));
    const applyAll = (field, val) => setRows((r) => Object.fromEntries(Object.entries(r).map(([k, v]) => [k, { ...v, [field]: val }])));

    const submit = (e) => {
        e.preventDefault();
        const grades = students.map((s) => ({ student_id: s.id, ...rows[s.id] }));
        router.post(route('teacher.gradebook.activities'), { ...form.data, grades }, {
            preserveScroll: true,
            onSuccess: () => { form.setData({ ...form.data, title: '', topic: '' }); setRows(blank()); setTab('history'); },
        });
    };
    const del = (id) => { if (confirm('این فعالیت و نمراتش حذف شود؟')) router.delete(route('teacher.gradebook.columns.destroy', id), { preserveScroll: true }); };

    const st = form.data.score_type;
    const opts = st === 'descriptive' ? descriptiveOptions : st === 'homework' ? homeworkOptions : [];

    // فیلترها، ترتیب و گروه‌بندیِ سوابق (ترتیب و گروه روی همین دستگاه می‌ماند)
    const [fLesson, setFLesson] = useState('');
    const [fType, setFType] = useState('');
    const [fStudent, setFStudent] = useState('');
    const [fq, setFq] = useState('');
    const [showFilters, setShowFilters] = useState(false);
    const pref = (k, d) => { try { return localStorage.getItem('gb-' + k) || d; } catch { return d; } };
    const [sortBy, setSortByS] = useState(() => pref('sort', 'new'));      // new | old | lesson | title
    const [groupBy, setGroupByS] = useState(() => pref('group', 'month')); // month | lesson | none
    const setSortBy = (v) => { setSortByS(v); try { localStorage.setItem('gb-sort', v); } catch { /* */ } };
    const setGroupBy = (v) => { setGroupByS(v); try { localStorage.setItem('gb-group', v); } catch { /* */ } };
    const [openCards, setOpenCards] = useState(() => (highlight ? { [highlight]: true } : {}));
    const [closedGroups, setClosedGroups] = useState({});
    const toggleCard = (id) => setOpenCards((o) => ({ ...o, [id]: !o[id] }));
    const filtered = activities.filter((a) =>
        (!fLesson || a.lesson === fLesson) &&
        (!fType || a.score_type === fType) &&
        (!fStudent || a.grades?.[fStudent]) &&
        (!fq || (a.title || '').includes(fq) || (a.topic || '').includes(fq) || (a.chapter || '').includes(fq) || (a.lesson || '').includes(fq)));
    const sorted = [...filtered].sort((x, y) => (
        sortBy === 'old' ? (x.ts - y.ts || x.id - y.id)
            : sortBy === 'lesson' ? ((x.lesson || '~').localeCompare(y.lesson || '~', 'fa') || y.ts - x.ts)
                : sortBy === 'title' ? ((x.title || '').localeCompare(y.title || '', 'fa'))
                    : (y.ts - x.ts || y.id - x.id)));
    const groups = [];
    sorted.forEach((a) => {
        const key = groupBy === 'month' ? (a.ym || '—') : groupBy === 'lesson' ? (a.lesson || 'بدونِ درس') : 'all';
        const label = groupBy === 'month' ? (a.month || 'بدونِ تاریخ') : groupBy === 'lesson' ? (a.lesson || 'بدونِ درس') : '';
        let g = groups.find((x) => x.key === key);
        if (!g) { g = { key, label, items: [] }; groups.push(g); }
        g.items.push(a);
    });
    const lessonsUsed = [...new Set(activities.map((a) => a.lesson).filter(Boolean))];
    const anyFilter = fLesson || fType || fStudent || fq;
    const allOpen = sorted.length > 0 && sorted.every((a) => openCards[a.id]);
    const setAll = (v) => { setOpenCards(v ? Object.fromEntries(sorted.map((a) => [a.id, true])) : {}); setClosedGroups({}); };
    const [printing, setPrinting] = useState(false);
    const doPrint = () => { setPrinting(true); setTimeout(() => { window.print(); setPrinting(false); }, 250); };

    // ویرایشِ یک فعالیت — هم مشخصات (درس، موضوع، نوع، بارم، عنوان، تاریخ) و هم نمرات
    const [editAct, setEditAct] = useState(null);
    const [erows, setErows] = useState({});
    const [emeta, setEmeta] = useState({});
    const [eerr, setEerr] = useState({});
    const [esaving, setEsaving] = useState(false);
    const optsFor = (type) => type === 'descriptive' ? descriptiveOptions : type === 'homework' ? homeworkOptions : [];
    const startEditAct = (a) => {
        const r = {}; students.forEach((s) => { const g = a.grades?.[s.id]; r[s.id] = { score: g?.score ?? '', text: g?.text ?? '', feedback: g?.feedback ?? '' }; });
        setErows(r); setEerr({});
        setEmeta({ title: a.title || '', lesson: a.lesson || '', topic: a.topic || '', chapter_id: a.chapter_id || '', score_type: a.score_type || 'descriptive', max: a.max || 20, date: a.date || '' });
        setEditAct(a.id);
        setOpenCards((o) => ({ ...o, [a.id]: true }));
    };
    const setERow = (sid, patch) => setErows((r) => ({ ...r, [sid]: { ...r[sid], ...patch } }));
    const setEM = (patch) => setEmeta((m) => ({ ...m, ...patch }));
    const saveEditAct = (a) => {
        const grades = students.map((s) => ({ student_id: s.id, ...erows[s.id], score: erows[s.id]?.score === '' ? null : erows[s.id]?.score }));
        router.post(route('teacher.gradebook.columns.update', a.id), { ...emeta, grades }, {
            preserveScroll: true,
            onStart: () => setEsaving(true),
            onFinish: () => setEsaving(false),
            onSuccess: () => setEditAct(null),
            onError: (e) => setEerr(e || {}),
        });
    };
    // فهرستِ درس‌ها؛ اگر درسِ قدیمیِ فعالیت دیگر در برنامه نیست، باز هم نمایش داده شود
    const lessonOptions = (cur) => {
        const names = subjects.map((s) => s.name);
        return subjects.length && cur && !names.includes(cur) ? [...subjects, { name: cur, icon: '📘' }] : subjects;
    };

    if (!classroom) return <DashLayout title="دفتر کلاسی" roleLabel="معلم" menu={teacherMenu} active="gradebook"><div className="panel">ابتدا کلاس بساز.</div></DashLayout>;

    return (
        <DashLayout title="دفتر کلاسی" roleLabel="معلم" menu={teacherMenu} active="gradebook">
            {banner && <div className="panel no-print" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            {/* تب‌ها */}
            <div className="panel no-print" style={{ display: 'flex', gap: 8, padding: 10 }}>
                <button onClick={() => setTab('new')} className={`btn btn-sm ${tab === 'new' ? '' : 'btn-ghost'}`}>➕ ثبت فعالیت</button>
                <button onClick={() => setTab('history')} className={`btn btn-sm ${tab === 'history' ? '' : 'btn-ghost'}`}>📚 سوابق نمرات ({fa(activities.length)})</button>
            </div>

            {tab === 'new' && (
                <form onSubmit={submit}>
                    {/* گام ۱: مشخصات فعالیت */}
                    <div className="panel">
                        <h3 style={{ marginTop: 0 }}>① مشخصات فعالیت <span style={{ fontSize: 12, fontWeight: 500, color: 'var(--muted)' }}>درس، موضوع و نوع نمره‌دهی</span></h3>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 12 }}>
                            <Field label="📖 درس">
                                <LessonPicker subjects={subjects} value={form.data.lesson} placeholder="— انتخاب درس —"
                                    onChange={(v) => { form.setData((d) => ({ ...d, lesson: v, chapter_id: '', title: d.title || v })); }} />
                            </Field>
                            <Field label="📘 فصل (برای رصدِ یادگیری)">
                                <ChapterPick grade={classroom?.grade} lesson={form.data.lesson} value={form.data.chapter_id} onChange={(v) => form.setData('chapter_id', v)} />
                            </Field>
                            <Field label="📋 موضوع (اختیاری)"><input className="input" value={form.data.topic} onChange={(e) => form.setData('topic', e.target.value)} placeholder="مثلاً: جمع و تفریق" /></Field>
                            <Field label="🎯 نوع نمره">
                                <select className="input" value={form.data.score_type} onChange={(e) => form.setData('score_type', e.target.value)}>
                                    {TYPES.map((t) => <option key={t.v} value={t.v}>{t.t}</option>)}
                                </select>
                            </Field>
                            {st === 'numeric' && <Field label="بارم (حداکثر نمره)"><input type="number" className="input" value={form.data.max} onChange={(e) => form.setData('max', e.target.value)} /></Field>}
                            <Field label="🏷️ عنوان" err={form.errors.title}><input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: ارزیابی کلاسی" /></Field>
                            <Field label="📅 تاریخ (پیش‌فرض امروز)"><JalaliDatePicker value={form.data.date} onChange={(v) => form.setData('date', v)} placeholder="امروز" /></Field>
                        </div>
                        <div className="gb-mhint">
                            🧠 این نمره به‌عنوانِ «نمره‌ی معلم» — قوی‌ترین شاهد — در <b>تسلطِ درسِ {form.data.lesson || 'انتخاب‌شده'}</b>
                            {form.data.topic ? <> و مبحثِ «{form.data.topic}»</> : null} برای هر دانش‌آموز حساب می‌شود.
                            {!form.data.lesson && <span style={{ color: '#a04413' }}> درس را انتخاب کن تا اثرش در تسلطِ همان درس بنشیند.</span>}
                        </div>
                    </div>

                    {/* گام ۲: نمرات دانش‌آموزان */}
                    <div className="panel">
                        <h3 style={{ marginTop: 0 }}>② نمره و بازخورد دانش‌آموزان</h3>

                        <SortBar s={ss} options={[['name', 'نام'], ['family', 'نام خانوادگی']]} />
                        {/* اعمال گروهی */}
                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 12 }}>
                            {st !== 'numeric' && opts.map((o) => (
                                <button type="button" key={o} onClick={() => applyAll('text', o)} className="btn btn-ghost btn-sm">همه: {ICON[o] || ''} {o}</button>
                            ))}
                            {st === 'numeric' && <>
                                <button type="button" onClick={() => { const v = prompt('نمره برای همه:'); if (v !== null) applyAll('score', v); }} className="btn btn-ghost btn-sm">اعمال به همه</button>
                                <button type="button" onClick={() => applyAll('score', '')} className="btn btn-ghost btn-sm">پاک کردن همه</button>
                            </>}
                        </div>

                        <div style={{ overflowX: 'auto' }}>
                            <table className="tbl">
                                <thead><tr><th>#</th><th>دانش‌آموز</th><th>{st === 'numeric' ? 'نمره' : 'ارزیابی'}</th><th style={{ minWidth: 160 }}>بازخورد</th></tr></thead>
                                <tbody>
                                    {ordered.map((s, i) => (
                                        <tr key={s.id}>
                                            <td>{fa(i + 1)}</td>
                                            <td style={{ fontWeight: 700 }}>{s.name}</td>
                                            <td>
                                                {st === 'numeric' ? (
                                                    <input type="number" step="0.25" max={form.data.max} value={rows[s.id]?.score ?? ''} onChange={(e) => setRow(s.id, { score: e.target.value })} className="grade-input" placeholder="—" style={{ width: 80 }} />
                                                ) : (
                                                    <div style={{ display: 'flex', gap: 5, flexWrap: 'wrap' }}>
                                                        {opts.map((o) => {
                                                            const on = rows[s.id]?.text === o; const [bg, fg] = RATING_COLOR[o] || ['#eee', '#333'];
                                                            return <button type="button" key={o} onClick={() => setRow(s.id, { text: on ? '' : o })}
                                                                style={{ cursor: 'pointer', fontFamily: 'inherit', fontSize: 12, fontWeight: 700, padding: '6px 10px', borderRadius: 9, border: on ? `2px solid ${fg}` : '1px solid var(--line)', background: on ? bg : '#fff', color: on ? fg : 'var(--muted)' }}>
                                                                {ICON[o] || ''} {o}</button>;
                                                        })}
                                                    </div>
                                                )}
                                            </td>
                                            <td><input value={rows[s.id]?.feedback ?? ''} onChange={(e) => setRow(s.id, { feedback: e.target.value })} className="grade-input" style={{ width: '100%' }} placeholder="بازخورد (اختیاری)" /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {st === 'numeric' && Object.values(rows).some((r) => r.score !== '' && Number(r.score) > Number(form.data.max)) && (
                            <div className="gb-warn">⚠️ بعضی نمره‌ها از بارمِ {fa(form.data.max)} بیشترند.</div>
                        )}
                        <button type="submit" disabled={form.processing || !form.data.title} className="btn" style={{ marginTop: 14 }}>💾 ثبت فعالیت و نمرات</button>
                    </div>
                </form>
            )}

            {tab === 'history' && (
                <div className={`panel printable gbh ${printing ? 'printing' : ''}`}>
                    <div className="gbh-head no-print">
                        <div>
                            <h3>📚 سوابقِ نمرات — {classroom?.name}</h3>
                            <small>{fa(activities.length)} فعالیت{anyFilter ? ` · ${fa(sorted.length)} موردِ پیدا شده` : ''}{impact === undefined && activities.length ? ' · 🧠 تحلیلِ تسلط در حالِ بارگذاری…' : ''}</small>
                        </div>
                        <div className="gbh-head-a">
                            {sorted.length > 0 && <button type="button" onClick={() => setAll(!allOpen)} className="btn btn-ghost btn-sm">{allOpen ? '▴ بستنِ همه' : '▾ بازکردنِ همه'}</button>}
                            <button type="button" onClick={doPrint} className="btn btn-ghost btn-sm">🖨️ پرینت</button>
                        </div>
                    </div>
                    <h3 className="print-title">دفتر کلاسی — {classroom?.name}{fStudent ? ` — ${students.find((x) => String(x.id) === String(fStudent))?.name}` : ''}</h3>

                    {activities.length > 0 && (
                        <div className="gbh-tools no-print">
                            <div className="gbh-search">
                                <input className="input" value={fq} onChange={(e) => setFq(e.target.value)} placeholder="🔍 جست‌وجو در عنوان، درس، فصل یا موضوع…" />
                                <button type="button" className={`gbh-fbtn ${showFilters || anyFilter ? 'on' : ''}`} onClick={() => setShowFilters((v) => !v)}>
                                    ⚙️ فیلتر{anyFilter ? ` (${fa([fLesson, fType, fStudent].filter(Boolean).length + (fq ? 1 : 0))})` : ''}
                                </button>
                            </div>
                            <div className="gbh-seg" role="group" aria-label="ترتیب">
                                <span>ترتیب:</span>
                                {[['new', '🕒 جدیدترین'], ['old', 'قدیمی‌ترین'], ['lesson', '📖 درس'], ['title', '🔤 عنوان']].map(([k, t]) => (
                                    <button key={k} type="button" className={sortBy === k ? 'on' : ''} onClick={() => setSortBy(k)}>{t}</button>
                                ))}
                            </div>
                            <div className="gbh-seg" role="group" aria-label="گروه‌بندی">
                                <span>گروه:</span>
                                {[['month', '📅 ماه'], ['lesson', '📖 درس'], ['none', 'بدونِ گروه']].map(([k, t]) => (
                                    <button key={k} type="button" className={groupBy === k ? 'on' : ''} onClick={() => setGroupBy(k)}>{t}</button>
                                ))}
                            </div>
                            {(showFilters || anyFilter) && (
                                <div className="gbh-filters">
                                    {lessonsUsed.length > 0 && (
                                        <div className="gbh-chips">
                                            <button type="button" className={!fLesson ? 'on' : ''} onClick={() => setFLesson('')}>همه‌ی درس‌ها</button>
                                            {lessonsUsed.map((l) => <button key={l} type="button" className={fLesson === l ? 'on' : ''} onClick={() => setFLesson(fLesson === l ? '' : l)}>{l}</button>)}
                                        </div>
                                    )}
                                    <div className="gbh-chips">
                                        <button type="button" className={!fType ? 'on' : ''} onClick={() => setFType('')}>همه‌ی انواع</button>
                                        {TYPES.map((t) => <button key={t.v} type="button" className={fType === t.v ? 'on' : ''} onClick={() => setFType(fType === t.v ? '' : t.v)}>{t.t}</button>)}
                                    </div>
                                    <div className="gbh-frow">
                                        <select className="input" value={fStudent} onChange={(e) => setFStudent(e.target.value)}>
                                            <option value="">👥 همه‌ی دانش‌آموزان</option>
                                            {ordered.map((st2) => <option key={st2.id} value={st2.id}>👤 {st2.name}</option>)}
                                        </select>
                                        <SortBar s={ss} label="ترتیبِ نام‌ها:" options={[['name', 'نام'], ['family', 'نام خانوادگی']]} />
                                        {anyFilter && <button type="button" onClick={() => { setFLesson(''); setFType(''); setFStudent(''); setFq(''); }} className="btn btn-ghost btn-sm">✕ پاک‌کردنِ فیلترها</button>}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {fStudent && <StudentSummary student={students.find((x) => String(x.id) === String(fStudent))} acts={sorted} />}
                    {activities.length === 0 && <p className="no-print" style={{ color: 'var(--muted)' }}>هنوز فعالیتی ثبت نشده. از تب «ثبت فعالیت» شروع کن.</p>}
                    {activities.length > 0 && sorted.length === 0 && <p className="no-print" style={{ color: 'var(--muted)' }}>با این فیلترها موردی پیدا نشد.</p>}

                    {groups.map((g) => {
                        const gClosed = !printing && closedGroups[g.key];
                        return (
                            <section key={g.key} className="gbh-group">
                                {groupBy !== 'none' && (
                                    <button type="button" className={`gbh-ghead ${gClosed ? 'shut' : ''}`} onClick={() => setClosedGroups((c) => ({ ...c, [g.key]: !c[g.key] }))}>
                                        <span className="chev">▾</span>
                                        <b>{groupBy === 'month' ? '📅' : '📖'} {g.label}</b>
                                        <small>{fa(g.items.length)} فعالیت</small>
                                    </button>
                                )}
                                {!gClosed && g.items.map((a) => {
                                    const editing = editAct === a.id;
                                    const open = printing || editing || openCards[a.id];
                                    const mi = impact?.[a.id];
                                    const isHi = String(highlight) === String(a.id);
                                    const stats = statsOf(a, students);
                                    const mine = fStudent ? a.grades?.[fStudent] : null;
                                    return (
                                        <div key={a.id} ref={isHi ? hiRef : null} className={`gbh-card ${open ? 'open' : ''} ${isHi ? 'gb-hi' : ''}`}>
                                            <button type="button" className="gbh-sum" onClick={() => !editing && toggleCard(a.id)} aria-expanded={!!open}>
                                                <span className={`gbh-date t-${a.score_type}`}>{a.jdate ? <><b>{fa(a.jdate.split(' ')[0])}</b><small>{a.jdate.split(' ')[1]}</small></> : '—'}</span>
                                                <span className="gbh-main">
                                                    <b>{a.title}</b>
                                                    <span className="gbh-meta">
                                                        {a.lesson && <span className="tag tag-info">{a.lesson}</span>}
                                                        <span className="gbh-type">{TYPES.find((t) => t.v === a.score_type)?.t || a.score_type}</span>
                                                        {a.chapter && <span className="gbh-ch">📘 {a.chapter}</span>}
                                                        {a.topic && <span className="gbh-topic">· {a.topic}</span>}
                                                    </span>
                                                </span>
                                                <span className="gbh-stats">
                                                    {mine ? <GradeChip a={a} g={mine} /> : <>
                                                        {a.score_type === 'numeric'
                                                            ? (stats.avg != null && <span className="gbh-avg">میانگین <b>{fa(stats.avg)}</b><small>/{fa(a.max)}</small></span>)
                                                            : <Dist dist={stats.dist} total={stats.n} />}
                                                        <small className="gbh-count">✍️ {fa(stats.n)}/{fa(students.length)}</small>
                                                    </>}
                                                    {mi?.avg_delta != null && <span className={`gb-d ${mi.avg_delta > 0 ? 'up' : mi.avg_delta < 0 ? 'down' : ''}`} title="میانگینِ سهمِ این نمره در تسلط">🧠 {mi.avg_delta > 0 ? '+' : ''}{fa(mi.avg_delta)}٪</span>}
                                                </span>
                                                {!editing && <span className="gbh-chev no-print">▾</span>}
                                            </button>

                                            {open && (
                                                <div className="gbh-body">
                                                    <div className="gbh-acts no-print">
                                                        {editing ? <>
                                                            <button onClick={() => saveEditAct(a)} disabled={esaving || !emeta.title} className="btn btn-sm">{esaving ? '…' : '💾 ذخیره‌ی تغییرات'}</button>
                                                            <button onClick={() => setEditAct(null)} className="btn btn-ghost btn-sm">انصراف</button>
                                                        </> : <>
                                                            <button onClick={() => startEditAct(a)} className="btn btn-ghost btn-sm">✏️ ویرایش</button>
                                                            <button onClick={() => del(a.id)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️ حذف</button>
                                                        </>}
                                                    </div>
                                                    {editing ? (
                                                        <EditBlock a={a} emeta={emeta} setEM={setEM} eerr={eerr} erows={erows} setERow={setERow} ordered={fStudent ? ordered.filter((x) => String(x.id) === String(fStudent)) : ordered}
                                                            optsFor={optsFor} lessonOptions={lessonOptions} classroom={classroom} />
                                                    ) : (
                                                        <>
                                                            {mi && Object.keys(mi.students || {}).length > 0 && (
                                                                <div className="gb-impact">
                                                                    <span>🧠 اثر در تسلطِ <b>{mi.subject}</b></span>
                                                                    {mi.avg_delta != null && <span className={`gb-d ${mi.avg_delta > 0 ? 'up' : mi.avg_delta < 0 ? 'down' : ''}`}>میانگینِ سهم: {mi.avg_delta > 0 ? '+' : ''}{fa(mi.avg_delta)}٪</span>}
                                                                    <span className="gb-impact-note">سهم = تسلطِ فعلی منهای تسلط بدونِ این نمره</span>
                                                                </div>
                                                            )}
                                                            <div className="gbh-list">
                                                                <div className="gbh-row gbh-row-h">
                                                                    <span>دانش‌آموز</span><span>{a.score_type === 'numeric' ? `نمره (از ${fa(a.max)})` : 'ارزیابی'}</span><span>بازخورد</span><span className="gb-mcol">🧠 تسلط</span>
                                                                </div>
                                                                {(fStudent ? ordered.filter((x) => String(x.id) === String(fStudent)) : ordered).map((st2) => {
                                                                    const g = a.grades?.[st2.id];
                                                                    return (
                                                                        <div key={st2.id} className={`gbh-row ${g ? '' : 'none'}`}>
                                                                            <span className="nm">{st2.name}</span>
                                                                            <span className="gr">{g ? <GradeChip a={a} g={g} /> : <span className="gbh-empty">—</span>}</span>
                                                                            <span className="fb">{g?.feedback || ''}</span>
                                                                            <span className="gb-mcol ms">{impact === undefined ? <span className="gbh-wait">…</span> : <Impact r={mi?.students?.[st2.id]} />}</span>
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                        </>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </section>
                        );
                    })}
                </div>
            )}
        </DashLayout>
    );
}

/** آمارِ سریعِ یک فعالیت: تعدادِ ثبت‌شده، میانگینِ عددی یا توزیعِ ارزیابی‌ها. */
function statsOf(a, students) {
    const vals = students.map((s) => a.grades?.[s.id]).filter((g) => g && (g.score != null || g.text));
    const nums = vals.map((g) => Number(g.score)).filter((x) => !Number.isNaN(x) && x !== null);
    const dist = {};
    vals.forEach((g) => { if (g.text) dist[g.text] = (dist[g.text] || 0) + 1; });
    return { n: vals.length, avg: a.score_type === 'numeric' && nums.length ? Math.round((nums.reduce((t, x) => t + x, 0) / nums.length) * 10) / 10 : null, dist };
}

/** نوارِ رنگیِ توزیعِ ارزیابی‌ها (خیلی خوب، خوب، …). */
function Dist({ dist, total }) {
    if (!total) return <span className="gbh-empty">بدونِ نمره</span>;
    return (
        <span className="gbh-dist" title={Object.entries(dist).map(([k, v]) => `${k}: ${v}`).join(' · ')}>
            {Object.entries(dist).map(([k, v]) => {
                const c = RATING_COLOR[k] || ['#eee', '#777'];
                return <i key={k} style={{ flexGrow: v, background: c[1] }} />;
            })}
        </span>
    );
}

function GradeChip({ a, g }) {
    if (a.score_type === 'numeric') {
        if (g?.score == null) return <span className="gbh-empty">—</span>;
        const r = Number(g.score) / (a.max || 20);
        const c = r >= 0.85 ? RATING_COLOR['خیلی خوب'] : r >= 0.7 ? RATING_COLOR['خوب'] : r >= 0.5 ? RATING_COLOR['قابل قبول'] : RATING_COLOR['نیاز به تلاش'];
        return <span className="gbh-chip" style={{ background: c[0], color: c[1] }}>{fa(g.score)}</span>;
    }
    const c = RATING_COLOR[g?.text];
    if (!g?.text) return <span className="gbh-empty">—</span>;
    return <span className="gbh-chip" style={c ? { background: c[0], color: c[1] } : undefined}>{ICON[g.text] || ''} {g.text}</span>;
}

/** خلاصه‌ی یک دانش‌آموز (وقتی فیلترِ دانش‌آموز انتخاب شده): تعداد، میانگینِ عددی و توزیعِ ارزیابی‌ها. */
function StudentSummary({ student, acts }) {
    if (!student) return null;
    const mine = acts.map((a) => [a, a.grades?.[student.id]]).filter(([, g]) => g);
    const nums = mine.filter(([a, g]) => a.score_type === 'numeric' && g.score != null).map(([a, g]) => (Number(g.score) / (a.max || 20)) * 20);
    const dist = {};
    mine.forEach(([, g]) => { if (g.text) dist[g.text] = (dist[g.text] || 0) + 1; });
    return (
        <div className="gbh-student">
            <b>👤 {student.name}</b>
            <span>{fa(mine.length)} فعالیتِ ثبت‌شده</span>
            {nums.length > 0 && <span>میانگینِ نمره‌های عددی: <b>{fa(Math.round((nums.reduce((t, x) => t + x, 0) / nums.length) * 10) / 10)}</b> از ۲۰</span>}
            {Object.keys(dist).length > 0 && (
                <span className="gbh-sdist">{Object.entries(dist).map(([k, v]) => { const c = RATING_COLOR[k] || ['#eee', '#333']; return <i key={k} style={{ background: c[0], color: c[1] }}>{ICON[k] || ''} {k} ×{fa(v)}</i>; })}</span>
            )}
        </div>
    );
}

/** ویرایشِ مشخصات و نمره‌های یک فعالیت (داخلِ کارتِ بازشده). */
function EditBlock({ a, emeta, setEM, eerr, erows, setERow, ordered, optsFor, lessonOptions, classroom }) {
    const etype = emeta.score_type;
    const aopts = optsFor(etype);
    return (
        <>
            <div className="gb-edit no-print">
                <div className="gb-edit-h">✏️ مشخصاتِ فعالیت</div>
                <div className="gb-edit-grid">
                    <Field label="🏷️ عنوان" err={eerr.title}><input className="input" value={emeta.title} onChange={(e) => setEM({ title: e.target.value })} /></Field>
                    <Field label="📖 درس" err={eerr.lesson}>
                        <LessonPicker subjects={lessonOptions(emeta.lesson)} value={emeta.lesson} placeholder="— بدون درس —" onChange={(v) => setEM({ lesson: v, chapter_id: '' })} />
                    </Field>
                    <Field label="📘 فصل" err={eerr.chapter_id}>
                        <ChapterPick grade={classroom?.grade} lesson={emeta.lesson} value={emeta.chapter_id} onChange={(v) => setEM({ chapter_id: v })} />
                    </Field>
                    <Field label="📋 موضوع" err={eerr.topic}><input className="input" value={emeta.topic} onChange={(e) => setEM({ topic: e.target.value })} placeholder="مثلاً: کسرها" /></Field>
                    <Field label="🎯 نوع نمره" err={eerr.score_type}>
                        <select className="input" value={emeta.score_type} onChange={(e) => setEM({ score_type: e.target.value })}>
                            {TYPES.map((t) => <option key={t.v} value={t.v}>{t.t}</option>)}
                        </select>
                    </Field>
                    {emeta.score_type === 'numeric' && <Field label="بارم" err={eerr.max}><input type="number" className="input" value={emeta.max} onChange={(e) => setEM({ max: e.target.value })} /></Field>}
                    <Field label="📅 تاریخ" err={eerr.date}><JalaliDatePicker value={emeta.date} onChange={(v) => setEM({ date: v })} placeholder="تاریخ" /></Field>
                </div>
                {emeta.score_type !== a.score_type && (
                    <div className="gb-warn">⚠️ نوعِ نمره عوض شد؛ ارزیابی‌های قبلی با نوعِ تازه سازگار نیستند. نمره‌ی هر دانش‌آموز را دوباره انتخاب کن (خانه‌های خالی حذف می‌شوند).</div>
                )}
                {emeta.score_type === 'numeric' && Object.values(erows).some((r) => r.score !== '' && r.score != null && Number(r.score) > Number(emeta.max)) && (
                    <div className="gb-warn">⚠️ بعضی نمره‌ها از بارمِ {fa(emeta.max)} بیشترند؛ در تسلط، بیشتر از بارم «۱۰۰٪» حساب می‌شود. اگر بارم را کم کرده‌ای، نمره‌ها را هم اصلاح کن.</div>
                )}
                {(emeta.lesson !== (a.lesson || '') || emeta.topic !== (a.topic || '')) && (
                    <div className="gb-mhint" style={{ marginTop: 8 }}>🧠 با ذخیره، این نمره‌ها از تسلطِ «{a.lesson || 'سایر'}» برداشته و در تسلطِ «{emeta.lesson || 'سایر'}»{emeta.topic ? ` (مبحثِ ${emeta.topic})` : ''} حساب می‌شوند.</div>
                )}
            </div>
            <div className="gbh-list edit">
                {ordered.map((s) => {
                    const er = erows[s.id] || {};
                    return (
                        <div key={s.id} className="gbh-erow">
                            <span className="nm">{s.name}</span>
                            <span className="gr">
                                {etype === 'numeric' ? (
                                    <input type="number" inputMode="decimal" step="0.25" max={emeta.max} value={er.score ?? ''} onChange={(e) => setERow(s.id, { score: e.target.value })} className="grade-input" style={{ width: 84 }} placeholder="—" />
                                ) : (
                                    <span className="gbh-opts">
                                        {aopts.map((o) => { const on = er.text === o; const rc = RATING_COLOR[o] || ['#eee', '#333']; return <button type="button" key={o} onClick={() => setERow(s.id, { text: on ? '' : o })} style={{ border: on ? `2px solid ${rc[1]}` : '1px solid var(--line)', background: on ? rc[0] : '#fff', color: on ? rc[1] : 'var(--muted)' }}>{ICON[o] || ''} {o}</button>; })}
                                    </span>
                                )}
                            </span>
                            <input value={er.feedback ?? ''} onChange={(e) => setERow(s.id, { feedback: e.target.value })} className="grade-input fb" placeholder="بازخورد (اختیاری)" />
                        </div>
                    );
                })}
            </div>
        </>
    );
}

/** درس‌های رایجِ دبستان — وقتی برای پایه‌ی کلاس کتابی تعریف نشده باشد. */
const COMMON_LESSONS = ['ریاضی', 'علوم', 'فارسی', 'املا', 'نگارش', 'مطالعات اجتماعی', 'هدیه‌های آسمانی', 'قرآن', 'تفکر و پژوهش', 'هنر', 'ورزش', 'انگلیسی'];

/** انتخابِ درس: از کتاب‌های پایه، یا اگر تعریف نشده‌اند، تایپ با پیشنهاد. */
function LessonPicker({ subjects, value, onChange, placeholder }) {
    if (subjects.length) {
        return (
            <select className="input" value={value} onChange={(e) => onChange(e.target.value)}>
                <option value="">{placeholder}</option>
                {subjects.map((s) => <option key={s.name} value={s.name}>{s.icon} {s.name}</option>)}
            </select>
        );
    }
    return (
        <>
            <input className="input" list="gb-lessons" value={value} onChange={(e) => onChange(e.target.value)} placeholder="مثلاً: ریاضی" />
            <datalist id="gb-lessons">{COMMON_LESSONS.map((l) => <option key={l} value={l} />)}</datalist>
        </>
    );
}

/** تسلطِ فعلیِ دانش‌آموز در درسِ این فعالیت و سهمِ همین نمره در آن. */
function Impact({ r }) {
    if (!r) return <span style={{ color: 'var(--muted-2)', fontSize: 12 }}>—</span>;
    if (r.after == null) {
        return <span className="gb-need" title="برای عددِ قابلِ اعتماد دستِ‌کم ۳ شاهد لازم است">⏳ {r.need > 0 ? `${fa(r.need)} شاهدِ دیگر` : 'به‌زودی'}</span>;
    }
    const d = r.delta;
    return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, flexWrap: 'wrap' }}>
            <MasteryTag value={r.after} title={r.level?.label} />
            {d == null && r.before == null && <span className="gb-d up" title="پیش از این نمره، شاهدِ کافی برای این درس نبود">✨ نخستین</span>}
            {d != null && <span className={`gb-d ${d > 0 ? 'up' : d < 0 ? 'down' : ''}`} title={`بدونِ این نمره: ${r.before}٪`}>{d > 0 ? '▲ +' : d < 0 ? '▼ ' : '● '}{fa(Math.abs(d))}</span>}
            {r.topic != null && <span style={{ fontSize: 11, color: 'var(--muted)' }}>مبحث {fa(r.topic)}٪</span>}
        </span>
    );
}

function Field({ label, err, children }) {
    return <div className="field" style={{ margin: 0 }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}

/** فصلِ ستونِ نمره — از فهرستِ رسمیِ فصل‌های همان درس و پایه. */
function ChapterPick({ grade, lesson, value, onChange }) {
    const chapters = useChapters(grade, lesson);
    return (
        <select className="input" value={value || ''} onChange={(e) => onChange(e.target.value ? Number(e.target.value) : '')} disabled={!lesson || !chapters.length}>
            <option value="">{!lesson ? 'اول درس را انتخاب کن' : chapters.length ? '— همه‌ی درس (بدونِ فصل) —' : 'فصلی برای این درس ثبت نشده'}</option>
            {chapters.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
        </select>
    );
}
