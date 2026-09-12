import { usePage, useForm, router, Link } from '@inertiajs/react';
import { useState, useEffect, useCallback, useRef } from 'react';
import axios from 'axios';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const DIFF = { easy: 'آسان', medium: 'متوسط', hard: 'دشوار' };
const BADGES = ['🎖️', '🏅', '🥇', '⭐', '🚀', '🔥', '💎', '🧠', '🎯', '👑', '🦉', '🌟'];

/**
 * هفت منبعِ مأموریت. هر کدام به بخشی که از قبل در سامانه هست وصل می‌شود،
 * و «ساختِ تازه» مستقیم به همان بخش می‌برد تا معلم دوباره چیزی نسازد.
 */
const TYPES = [
    { v: 'quiz', ic: '🧠', t: 'سؤال از بانک', d: 'چند سؤال از بانکِ سؤالِ تو', make: null },
    { v: 'podcast', ic: '🎧', t: 'پادکست', d: 'یک پادکستِ کلاس را کامل گوش بده', make: '/teacher/materials', makeT: 'افزودن پادکست' },
    { v: 'video', ic: '🎬', t: 'ویدیوی درسی', d: 'یک ویدیو را کامل ببین', make: '/teacher/materials', makeT: 'افزودن ویدیو' },
    { v: 'material', ic: '📄', t: 'جزوه و فایل', d: 'یک جزوه را مطالعه کن', make: '/teacher/materials', makeT: 'افزودن جزوه' },
    { v: 'worksheet', ic: '🎨', t: 'کاربرگ', d: 'یک کاربرگ را پر و ارسال کن', make: '/teacher/worksheets/create', makeT: 'ساخت کاربرگ' },
    { v: 'game', ic: '🎮', t: 'بازی', d: 'یکی از بازی‌های کلاس را کامل کن', make: '/teacher/studio', makeT: 'استودیوی بازی' },
    { v: 'exam', ic: '🧪', t: 'آزمونِ هوشمند', d: 'یک آزمونِ منتشرشده را بده', make: '/teacher/smart-exams', makeT: 'آزمایشگاه آزمون', flag: 'smart' },
];
const TYPE_LABEL = Object.fromEntries(TYPES.map((t) => [t.v, `${t.ic} ${t.t}`]));

/** الگوهای آماده — معلم با یک کلیک یک مأموریتِ نیم‌ساخته می‌گیرد. */
const TEMPLATES = [
    { ic: '⚡', t: 'چالشِ سریعِ صبح', p: { type: 'quiz', title: 'چالشِ سریعِ امروز', question_count: 5, xp_reward: 20, difficulty: 'easy', badge_icon: '⚡', pass_percent: 60 } },
    { ic: '🎧', t: 'پادکستِ روز', p: { type: 'podcast', title: 'پادکستِ امروز را گوش بده', xp_reward: 25, badge_icon: '🎧' } },
    { ic: '🔥', t: 'آزمونِ سختِ هفته', p: { type: 'quiz', title: 'آزمونِ سختِ این هفته', question_count: 10, xp_reward: 60, difficulty: 'hard', badge_icon: '🔥', pass_percent: 70 } },
    { ic: '🎮', t: 'بازیِ کلاسی', p: { type: 'game', title: 'یک بازی را کامل کن', xp_reward: 30, badge_icon: '🎮' } },
];

export default function Missions() {
    const { missions = [], facets = [], classrooms = [], themes = [], resources = {}, stats = {}, bankTotal = 0, smartLab = false, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [editId, setEditId] = useState(null);
    const formRef = useRef(null);
    useEffect(() => { if (flash?.flash) { setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); window.scrollTo({ top: 0, behavior: 'smooth' }); } }, [flash]);

    const blank = {
        title: '', description: '', type: 'quiz', resource_id: '', theme_id: '', subject: '', lesson_no: '',
        difficulty: '', classroom_id: '', question_ids: [], question_count: 5, pass_percent: 60,
        xp_reward: 20, badge_name: '', badge_icon: '🎖️', is_active: true,
    };
    const form = useForm(blank);
    const lessons = (facets.find((s) => s.subject === form.data.subject)?.lessons) || [];
    const types = TYPES.filter((t) => !t.flag || smartLab);
    const activeType = types.find((t) => t.v === form.data.type) || types[0];

    const submit = (e) => {
        e.preventDefault();
        const done = () => { form.reset(); setEditId(null); };
        if (editId) form.put(route('teacher.missions.update', editId), { preserveScroll: true, onSuccess: done });
        else form.post(route('teacher.missions.store'), { preserveScroll: true, onSuccess: done });
    };
    const edit = (m) => {
        setEditId(m.id);
        form.setData({
            ...blank, ...m,
            subject: m.subject || '', lesson_no: m.lesson_no || '', difficulty: m.difficulty || '',
            classroom_id: m.classroom_id || '', theme_id: m.theme_id || '', resource_id: m.resource_id || '',
            question_ids: m.question_ids || [], badge_name: m.badge_name || '', badge_icon: m.badge_icon || '🎖️',
            description: m.description || '', pass_percent: m.pass_percent ?? 60,
        });
        formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    const cancel = () => { setEditId(null); form.reset(); };
    const applyTemplate = (t) => { setEditId(null); form.setData({ ...blank, ...t.p }); formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }); };
    const del = (m) => confirm(`مأموریت «${m.title}» حذف شود؟`) && router.delete(route('teacher.missions.destroy', m.id), { preserveScroll: true });
    const toggle = (m) => router.post(route('teacher.missions.toggle', m.id), {}, { preserveScroll: true });
    const dup = (m) => router.post(route('teacher.missions.duplicate', m.id), {}, { preserveScroll: true });

    return (
        <DashLayout title="مأموریت‌های روزانه" roleLabel="معلم" menu={teacherMenu} active="missions">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            {/* ── نبضِ مأموریت‌ها ── */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10, marginBottom: 14 }}>
                <Stat ic="🎯" v={fa(stats.active ?? 0)} l="مأموریتِ فعال" c="#3d7bf0" />
                <Stat ic="🙋" v={fa(stats.today_players ?? 0)} l="دانش‌آموزِ امروز" c="#2bb673" />
                <Stat ic="✅" v={fa(stats.today_done ?? 0)} l="انجامِ امروز" c="#a24cf0" />
                <Stat ic="⚡" v={fa(stats.week_xp ?? 0)} l="امتیازِ ۷ روز" c="#e8862e" />
            </div>

            {/* ── الگوهای آماده ── */}
            <div className="panel" style={{ paddingBottom: 12 }}>
                <div style={{ fontWeight: 800, fontSize: 13.5, marginBottom: 8 }}>⚡ شروعِ سریع — یک الگو را بزن و فقط جزئیاتش را کامل کن</div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {TEMPLATES.map((t) => (
                        <button key={t.t} type="button" onClick={() => applyTemplate(t)}
                            style={{ cursor: 'pointer', fontFamily: 'inherit', border: '1px solid var(--line)', background: '#fff', borderRadius: 12, padding: '9px 14px', fontWeight: 700, fontSize: 13 }}>
                            {t.ic} {t.t}
                        </button>
                    ))}
                </div>
            </div>

            {/* ── مأموریت‌ساز ── */}
            <div className="panel" ref={formRef}>
                <h3 style={{ marginTop: 0 }}>🎯 {editId ? 'ویرایشِ مأموریت' : 'ساختِ مأموریتِ روزانه'}</h3>
                <p style={{ color: 'var(--muted)', fontSize: 13, marginTop: 0, lineHeight: 1.9 }}>
                    مأموریت را از هر چیزی که در سامانه داری بساز — بانکِ سؤال، پادکست، ویدیو، جزوه، کاربرگ، بازی یا آزمون.
                    دانش‌آموز هر روز می‌تواند هر مأموریت را یک‌بار انجام دهد و امتیاز/نشان بگیرد.
                </p>

                <form onSubmit={submit}>
                    {/* ① نوع */}
                    <Step n="①" t="مأموریت از کجا بیاید؟" />
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(140px,1fr))', gap: 8, marginBottom: 6 }}>
                        {types.map((ty) => (
                            <button type="button" key={ty.v} onClick={() => { form.setData('type', ty.v); form.setData('resource_id', ''); }}
                                style={{ textAlign: 'right', cursor: 'pointer', fontFamily: 'inherit', borderRadius: 12, padding: 10, border: form.data.type === ty.v ? '2px solid var(--gold)' : '1px solid var(--line)', background: form.data.type === ty.v ? '#fff8e8' : '#fff' }}>
                                <div style={{ fontSize: 22 }}>{ty.ic}</div>
                                <div style={{ fontWeight: 800, fontSize: 13, marginTop: 2 }}>{ty.t}</div>
                                <div style={{ fontSize: 11, color: 'var(--muted)' }}>{ty.d}</div>
                            </button>
                        ))}
                    </div>

                    {/* ② منبع */}
                    <Step n="②" t={form.data.type === 'quiz' ? 'کدام سؤال‌ها؟' : `کدام ${activeType?.t}؟`} />
                    {form.data.type === 'quiz'
                        ? <BankPicker form={form} facets={facets} lessons={lessons} bankTotal={bankTotal} />
                        : <ResourcePicker form={form} type={activeType} list={resources[form.data.type] || []} />}

                    {/* ③ جزئیات */}
                    <Step n="③" t="جزئیات و جایزه" />
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(190px,1fr))', gap: 12 }}>
                        <Field label="عنوانِ مأموریت" err={form.errors.title}>
                            <input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)}
                                placeholder={form.data.type === 'quiz' ? 'مثلاً: چالشِ ضرب امروز' : `مثلاً: ${activeType?.d}`} />
                        </Field>
                        <Field label="برای کدام کلاس؟ (خالی = همه)">
                            <select className="input" value={form.data.classroom_id} onChange={(e) => form.setData('classroom_id', e.target.value)}>
                                <option value="">همه‌ی کلاس‌ها</option>
                                {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </Field>
                        <Field label="برای کدام تیم؟ (خالی = همه)">
                            <select className="input" value={form.data.theme_id} onChange={(e) => form.setData('theme_id', e.target.value)}>
                                <option value="">همه‌ی تیم‌ها</option>
                                {themes.map((t) => <option key={t.id} value={t.id}>{t.emoji} {t.name}</option>)}
                            </select>
                        </Field>
                        <Field label="امتیاز (XP)" err={form.errors.xp_reward}>
                            <input type="number" min={0} max={500} dir="ltr" className="input" value={form.data.xp_reward} onChange={(e) => form.setData('xp_reward', +e.target.value)} />
                        </Field>
                        {form.data.type === 'quiz' && (
                            <Field label={`حدِ قبولی: ${fa(form.data.pass_percent)}٪`}>
                                <input type="range" min={0} max={100} step={5} value={form.data.pass_percent} onChange={(e) => form.setData('pass_percent', +e.target.value)} style={{ width: '100%' }} />
                            </Field>
                        )}
                        <Field label="نامِ نشان (اختیاری)">
                            <input className="input" value={form.data.badge_name} onChange={(e) => form.setData('badge_name', e.target.value)} placeholder="مثلاً: قهرمانِ ضرب" />
                        </Field>
                    </div>

                    <Field label="یادداشت برای دانش‌آموز (اختیاری)">
                        <input className="input" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="مثلاً: قبل از شروع، درس ۳ را یک‌بار مرور کن." maxLength={400} />
                    </Field>

                    <div style={{ marginTop: 4 }}>
                        <label style={{ fontWeight: 700, display: 'block', marginBottom: 6, fontSize: 13 }}>آیکونِ نشان</label>
                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                            {BADGES.map((b) => (
                                <button type="button" key={b} onClick={() => form.setData('badge_icon', b)}
                                    style={{ fontSize: 20, width: 42, height: 42, borderRadius: 10, cursor: 'pointer', border: form.data.badge_icon === b ? '2px solid var(--gold)' : '1px solid var(--line)', background: form.data.badge_icon === b ? '#fff8e8' : '#fff' }}>{b}</button>
                            ))}
                        </div>
                    </div>

                    <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 14, marginTop: 12 }}>
                        <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} /> از همین امروز فعال باشد
                    </label>
                    <div style={{ display: 'flex', gap: 8, marginTop: 12, flexWrap: 'wrap' }}>
                        <button type="submit" disabled={form.processing} className="btn">{editId ? '💾 ذخیره‌ی تغییرات' : '➕ ساختِ مأموریت'}</button>
                        {editId && <button type="button" onClick={cancel} className="btn btn-ghost">انصراف</button>}
                    </div>
                </form>
            </div>

            {/* ── فهرست ── */}
            <div className="panel">
                <h3>🗄️ مأموریت‌های من ({fa(missions.length)})</h3>
                {missions.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز مأموریتی نساخته‌ای. از «شروعِ سریع» بالا یک الگو را بزن.</p>}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(280px,1fr))', gap: 12, marginTop: 8 }}>
                    {missions.map((m) => (
                        <MissionCard key={m.id} m={m} themes={themes} onEdit={() => edit(m)} onToggle={() => toggle(m)} onDup={() => dup(m)} onDel={() => del(m)} />
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}

/* ═══════════════════ انتخابگرِ بانکِ سؤال ═══════════════════ */
function BankPicker({ form, facets, lessons, bankTotal }) {
    const [q, setQ] = useState('');
    const [data, setData] = useState({ total: 0, items: [], pinned: [], page: 1, pages: 1 });
    const [busy, setBusy] = useState(false);
    const [page, setPage] = useState(1);
    const [maker, setMaker] = useState(false);
    const pinned = form.data.question_ids || [];

    const load = useCallback(async (p = 1) => {
        setBusy(true);
        try {
            const { data: r } = await axios.get(route('teacher.missions.bank'), {
                params: { subject: form.data.subject, lesson_no: form.data.lesson_no, difficulty: form.data.difficulty, q, page: p, ids: pinned },
            });
            if (r.ok) { setData(r); setPage(r.page); }
        } catch { /* بی‌صدا */ } finally { setBusy(false); }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.subject, form.data.lesson_no, form.data.difficulty, q, JSON.stringify(pinned)]);

    // جست‌وجوی تأخیردار تا با هر حرف یک درخواست نرود
    useEffect(() => { const h = setTimeout(() => load(1), 350); return () => clearTimeout(h); }, [load]);

    const togglePin = (id) => {
        const next = pinned.includes(id) ? pinned.filter((x) => x !== id) : [...pinned, id];
        form.setData('question_ids', next);
    };

    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 12, background: '#fafbff', marginBottom: 6 }}>
            {bankTotal === 0 && (
                <div style={{ background: '#fff4e5', border: '1px solid #ffd9a8', borderRadius: 12, padding: '10px 13px', fontSize: 13, lineHeight: 1.9, marginBottom: 10 }}>
                    ⚠️ بانکِ سؤالِ تو هنوز خالی است. همین‌جا با «➕ سؤالِ تازه» بساز — سؤال در <b>بانکِ سؤال</b> هم ذخیره می‌شود و بعداً در آزمون و بازی هم به‌کار می‌آید.
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10 }}>
                <Field label="درس">
                    <select className="input" value={form.data.subject} onChange={(e) => { form.setData('subject', e.target.value); form.setData('lesson_no', ''); }}>
                        <option value="">همه‌ی درس‌ها</option>
                        {facets.map((s) => <option key={s.subject} value={s.subject}>{s.subject}</option>)}
                    </select>
                </Field>
                <Field label="شماره درس">
                    <select className="input" value={form.data.lesson_no} onChange={(e) => form.setData('lesson_no', e.target.value)} disabled={!form.data.subject}>
                        <option value="">همه</option>
                        {lessons.map((l) => <option key={l} value={l === '—' ? '' : l}>{l}</option>)}
                    </select>
                </Field>
                <Field label="سطح">
                    <select className="input" value={form.data.difficulty} onChange={(e) => form.setData('difficulty', e.target.value)}>
                        <option value="">همه</option><option value="easy">آسان</option><option value="medium">متوسط</option><option value="hard">دشوار</option>
                    </select>
                </Field>
                <Field label="جست‌وجو در متنِ سؤال">
                    <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="مثلاً: کسر" />
                </Field>
            </div>

            {/* خلاصه‌ی زنده */}
            <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap', margin: '8px 0 10px' }}>
                <span className="tag tag-info">{busy ? '…' : `${fa(data.total)} سؤالِ منطبق`}</span>
                {pinned.length > 0
                    ? <>
                        <span className="tag tag-ok">📌 {fa(pinned.length)} سؤالِ سنجاق‌شده — دقیقاً همین‌ها پرسیده می‌شود</span>
                        <button type="button" onClick={() => form.setData('question_ids', [])} className="btn btn-ghost btn-sm">برداشتنِ سنجاق‌ها</button>
                      </>
                    : <span style={{ fontSize: 12, color: 'var(--muted)' }}>هر روز تصادفی از همین فیلترها انتخاب می‌شود. برای انتخابِ دقیق، سؤال‌ها را سنجاق کن 📌</span>}
            </div>

            {pinned.length === 0 && (
                <Field label={`تعدادِ سؤالِ هر روز: ${fa(form.data.question_count)}`}>
                    <input type="range" min={1} max={Math.max(1, Math.min(30, data.total || 30))} value={form.data.question_count}
                        onChange={(e) => form.setData('question_count', +e.target.value)} style={{ width: '100%' }} />
                </Field>
            )}

            {/* فهرستِ سؤال‌ها */}
            <div style={{ maxHeight: 330, overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: 8, marginTop: 6 }}>
                {data.pinned.filter((p) => !data.items.some((i) => i.id === p.id)).map((it) => (
                    <QRow key={'p' + it.id} it={it} pinned onToggle={() => togglePin(it.id)} />
                ))}
                {data.items.map((it) => (
                    <QRow key={it.id} it={it} pinned={pinned.includes(it.id)} onToggle={() => togglePin(it.id)} />
                ))}
                {!busy && data.items.length === 0 && data.pinned.length === 0 && (
                    <div style={{ textAlign: 'center', color: 'var(--muted)', fontSize: 13, padding: 14 }}>سؤالی با این فیلترها پیدا نشد 🔍</div>
                )}
            </div>

            {data.pages > 1 && (
                <div style={{ display: 'flex', gap: 8, justifyContent: 'center', alignItems: 'center', marginTop: 10 }}>
                    <button type="button" disabled={page <= 1} onClick={() => load(page - 1)} className="btn btn-ghost btn-sm">› قبلی</button>
                    <span style={{ fontSize: 12.5, color: 'var(--muted)' }}>صفحه {fa(page)} از {fa(data.pages)}</span>
                    <button type="button" disabled={page >= data.pages} onClick={() => load(page + 1)} className="btn btn-ghost btn-sm">بعدی ‹</button>
                </div>
            )}

            <div style={{ display: 'flex', gap: 8, marginTop: 10, flexWrap: 'wrap' }}>
                <button type="button" onClick={() => setMaker(!maker)} className="btn btn-sm">{maker ? '✕ بستن' : '➕ سؤالِ تازه'}</button>
                <Link href="/teacher/smart-exams" className="btn btn-ghost btn-sm">🗂️ مدیریتِ کاملِ بانک</Link>
            </div>

            {maker && <QuestionMaker form={form} onSaved={(id) => { form.setData('question_ids', [...pinned, id]); load(1); }} />}
        </div>
    );
}

function QRow({ it, pinned, onToggle }) {
    return (
        <label style={{ display: 'flex', gap: 10, alignItems: 'flex-start', border: `1px solid ${pinned ? 'var(--gold)' : 'var(--line)'}`, background: pinned ? '#fff8e8' : '#fff', borderRadius: 12, padding: 10, cursor: 'pointer' }}>
            <input type="checkbox" checked={pinned} onChange={onToggle} style={{ marginTop: 4 }} />
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 700, fontSize: 13.5, lineHeight: 1.9 }}>{it.prompt}</div>
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 5 }}>
                    {it.subject && <span className="tag tag-info" style={{ fontSize: 10.5 }}>{it.subject}</span>}
                    {it.lesson_no && <span className="tag" style={{ fontSize: 10.5, background: '#f0f3f8' }}>درس {fa(it.lesson_no)}</span>}
                    {it.difficulty && <span className="tag" style={{ fontSize: 10.5, background: '#f0f3f8' }}>{DIFF[it.difficulty]}</span>}
                    <span className="tag" style={{ fontSize: 10.5, background: '#f0f3f8' }}>{fa(it.choices?.length || 0)} گزینه</span>
                </div>
                {it.choices?.length > 0 && (
                    <div style={{ fontSize: 11.5, color: 'var(--muted)', marginTop: 4 }}>
                        ✅ {it.choices.find((c) => c.correct)?.value || '—'}
                    </div>
                )}
            </div>
        </label>
    );
}

/* ═══════════════════ ساختِ سؤالِ تازه (ذخیره در بانک) ═══════════════════ */
function QuestionMaker({ form, onSaved }) {
    const empty = { prompt: '', choices: [{ value: '', correct: true }, { value: '', correct: false }, { value: '', correct: false }, { value: '', correct: false }], explanation: '' };
    const [q, setQ] = useState(empty);
    const [msg, setMsg] = useState(null);
    const [busy, setBusy] = useState(false);
    const [ai, setAi] = useState([]);

    const setChoice = (i, v) => setQ((s) => ({ ...s, choices: s.choices.map((c, k) => (k === i ? { ...c, value: v } : c)) }));
    const setCorrect = (i) => setQ((s) => ({ ...s, choices: s.choices.map((c, k) => ({ ...c, correct: k === i })) }));

    const save = async (payload = null) => {
        const body = payload || q;
        if (!body.prompt.trim()) { setMsg({ t: 'err', m: 'متنِ سؤال را بنویس.' }); return; }
        if (body.choices.filter((c) => c.value.trim()).length < 2) { setMsg({ t: 'err', m: 'حداقل دو گزینه لازم است.' }); return; }
        setBusy(true); setMsg(null);
        try {
            const { data } = await axios.post(route('teacher.missions.quick'), {
                prompt: body.prompt, type: 'mc',
                choices: body.choices.filter((c) => c.value.trim()),
                explanation: body.explanation || null,
                subject: form.data.subject || null, lesson_no: form.data.lesson_no || null,
                difficulty: form.data.difficulty || 'medium',
            });
            if (data.ok) { setMsg({ t: 'ok', m: data.message }); setQ(empty); onSaved(data.id); }
            else setMsg({ t: 'err', m: data.message || 'ذخیره نشد.' });
        } catch (e) {
            setMsg({ t: 'err', m: e.response?.data?.message || 'ذخیره نشد.' });
        } finally { setBusy(false); }
    };

    const suggest = async (sample = false) => {
        setBusy(true); setMsg(null);
        try {
            const { data } = await axios.post(route('teacher.missions.ai'), {
                subject: form.data.subject, lesson_no: form.data.lesson_no,
                difficulty: form.data.difficulty || 'medium', count: 4, sample,
            });
            if (data.ok) { setAi(data.questions || []); setMsg(data.message ? { t: 'info', m: data.message } : null); }
            else setMsg({ t: 'err', m: data.message || 'تولید نشد.' });
        } catch { setMsg({ t: 'err', m: 'خطا در ارتباط با سرور.' }); } finally { setBusy(false); }
    };

    return (
        <div style={{ border: '1px dashed var(--line)', borderRadius: 12, padding: 12, marginTop: 10, background: '#fff' }}>
            <div style={{ fontWeight: 800, fontSize: 13 }}>➕ سؤالِ تازه</div>
            <div style={{ fontSize: 11.5, color: 'var(--muted)', margin: '3px 0 8px', lineHeight: 1.9 }}>
                این سؤال در <b>بانکِ سؤالِ تو</b> ذخیره می‌شود (نه فقط داخلِ این مأموریت) و خودکار به مأموریت سنجاق می‌شود.
            </div>

            <Field label="متنِ سؤال">
                <textarea className="input" rows={2} value={q.prompt} onChange={(e) => setQ({ ...q, prompt: e.target.value })} style={{ resize: 'vertical' }} />
            </Field>
            {q.choices.map((c, i) => (
                <div key={i} style={{ display: 'flex', gap: 7, alignItems: 'center', marginBottom: 6 }}>
                    <input type="radio" name="qm-correct" checked={!!c.correct} onChange={() => setCorrect(i)} title="پاسخ درست" />
                    <input className="input" value={c.value} onChange={(e) => setChoice(i, e.target.value)} placeholder={`گزینه‌ی ${fa(i + 1)}`} style={{ flex: 1 }} />
                </div>
            ))}
            <Field label="توضیحِ پاسخ (اختیاری — بعد از پاسخ به دانش‌آموز نشان داده می‌شود)">
                <input className="input" value={q.explanation} onChange={(e) => setQ({ ...q, explanation: e.target.value })} />
            </Field>

            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                <button type="button" disabled={busy} onClick={() => save()} className="btn btn-sm">💾 ذخیره در بانک و سنجاق</button>
                <button type="button" disabled={busy} onClick={() => suggest(false)} className="btn btn-ghost btn-sm">✨ پیشنهاد با هوش مصنوعی</button>
                <button type="button" disabled={busy} onClick={() => suggest(true)} className="btn btn-ghost btn-sm" title="بدون کلید API">نمونه</button>
            </div>
            {msg && <div style={{ marginTop: 8, fontSize: 12.5, borderRadius: 10, padding: '8px 11px', background: msg.t === 'err' ? '#fdecee' : msg.t === 'ok' ? '#e6f7ee' : '#eef3ff', color: msg.t === 'err' ? '#b0333f' : msg.t === 'ok' ? '#1a8a52' : '#2555c0' }}>{msg.m}</div>}

            {ai.length > 0 && (
                <div style={{ marginTop: 10 }}>
                    <div style={{ fontWeight: 800, fontSize: 12.5, marginBottom: 6 }}>پیشنهادها — هرکدام را خواستی در بانک ذخیره کن:</div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8, maxHeight: 240, overflowY: 'auto' }}>
                        {ai.map((s, i) => (
                            <div key={i} style={{ border: '1px solid var(--line)', borderRadius: 10, padding: 9 }}>
                                <div style={{ fontWeight: 700, fontSize: 13, lineHeight: 1.9 }}>{s.prompt}</div>
                                <div style={{ fontSize: 11.5, color: 'var(--muted)', marginTop: 3 }}>✅ {(s.choices || []).find((c) => c.correct)?.value || '—'}</div>
                                <div style={{ display: 'flex', gap: 6, marginTop: 7 }}>
                                    <button type="button" disabled={busy} className="btn btn-sm" onClick={() => save({ prompt: s.prompt, choices: s.choices || [], explanation: s.explanation || '' })}>💾 ذخیره و سنجاق</button>
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => setQ({ prompt: s.prompt, choices: (s.choices || []).map((c) => ({ value: c.value || '', correct: !!c.correct })), explanation: s.explanation || '' })}>✏️ ویرایش</button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

/* ═══════════════════ انتخابگرِ منبعِ غیرِسؤالی ═══════════════════ */
function ResourcePicker({ form, type, list }) {
    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 12, background: '#fafbff', marginBottom: 6 }}>
            {list.length === 0 ? (
                <div style={{ background: '#fff4e5', border: '1px solid #ffd9a8', borderRadius: 12, padding: '11px 13px', fontSize: 13, lineHeight: 1.9 }}>
                    ⚠️ هنوز هیچ {type?.t}ِ آماده‌ای نداری.
                    {type?.make && <> اول یکی بساز: <Link href={type.make} className="btn btn-sm" style={{ marginInlineStart: 8 }}>← {type.makeT}</Link></>}
                    <div style={{ fontSize: 11.5, color: 'var(--muted)', marginTop: 6 }}>
                        می‌توانی مأموریت را همین حالا هم بسازی و گزینه‌ی «هر موردی» را بگذاری؛ بعداً که ساختی خودبه‌خود به‌کار می‌آید.
                    </div>
                </div>
            ) : (
                <Field label={`کدام ${type?.t}؟ (خالی = هر موردی از این نوع)`}>
                    <select className="input" value={form.data.resource_id} onChange={(e) => form.setData('resource_id', e.target.value)}>
                        <option value="">هر {type?.t}ی</option>
                        {list.map((r) => <option key={r.id} value={r.id}>{r.title}</option>)}
                    </select>
                </Field>
            )}
            {list.length > 0 && type?.make && (
                <div style={{ marginTop: 6 }}>
                    <Link href={type.make} className="btn btn-ghost btn-sm">➕ {type.makeT}</Link>
                    <span style={{ fontSize: 11.5, color: 'var(--muted)', marginInlineStart: 8 }}>هرچه آنجا بسازی، همین‌جا هم در فهرست می‌آید.</span>
                </div>
            )}
        </div>
    );
}

/* ═══════════════════ اجزای کوچک ═══════════════════ */
function MissionCard({ m, themes, onEdit, onToggle, onDup, onDel }) {
    const team = themes.find((t) => t.id === m.theme_id);
    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 13, opacity: m.is_active ? 1 : .55, borderTop: `4px solid ${m.ready ? '#2bb673' : '#e8862e'}` }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span style={{ fontSize: 22 }}>{m.badge_icon || '🎯'}</span>
                <b style={{ flex: 1, minWidth: 0 }}>{m.title}</b>
                <span className={`tag ${m.is_active ? 'tag-ok' : ''}`} style={{ fontSize: 11 }}>{m.is_active ? 'فعال' : 'غیرفعال'}</span>
            </div>
            {m.description && <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 5, lineHeight: 1.8 }}>{m.description}</div>}

            <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 6 }}>
                <span className="tag tag-info" style={{ fontSize: 10.5, marginInlineEnd: 4 }}>{TYPE_LABEL[m.type] || TYPE_LABEL.quiz}</span>
                {m.type === 'quiz'
                    ? (m.question_ids?.length > 0
                        ? `📌 ${fa(m.question_ids.length)} سؤالِ انتخابی`
                        : [m.subject || 'همه‌ی درس‌ها', m.lesson_no ? `درس ${fa(m.lesson_no)}` : null, m.difficulty ? DIFF[m.difficulty] : null].filter(Boolean).join(' · '))
                    : (m.resource_title || 'هر موردی')}
            </div>

            <div style={{ fontSize: 11.5, color: 'var(--muted-2)', marginTop: 4 }}>
                🏆 {team ? `${team.emoji} تیمِ ${team.name}` : 'همه‌ی تیم‌ها'}
                {m.type === 'quiz' && ` · ${fa(m.question_count)} سؤال · قبولی ${fa(m.pass_percent)}٪`}
                {` · ⚡${fa(m.xp_reward)}`}{m.badge_name ? ` · 🎖️ ${m.badge_name}` : ''}
            </div>

            <div style={{ fontSize: 11.5, marginTop: 6, borderRadius: 9, padding: '6px 9px', lineHeight: 1.8,
                background: m.ready ? '#e6f7ee' : '#fdecee', color: m.ready ? '#1a8a52' : '#b0333f' }}>
                {m.ready ? '✅' : '⚠️'} {m.ready_note}
            </div>

            <div style={{ fontSize: 11.5, color: 'var(--muted-2)', marginTop: 6 }}>امروز {fa(m.today)} نفر · مجموع {fa(m.completions)} انجام</div>
            <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                <button onClick={onEdit} className="btn btn-ghost btn-sm">✏️ ویرایش</button>
                <button onClick={onToggle} className="btn btn-ghost btn-sm">{m.is_active ? '⏸️ غیرفعال' : '▶️ فعال'}</button>
                <button onClick={onDup} className="btn btn-ghost btn-sm" title="رونوشت برای فردا">⧉</button>
                <button onClick={onDel} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
            </div>
        </div>
    );
}

const Stat = ({ ic, v, l, c }) => (
    <div style={{ background: '#fff', border: '1px solid var(--line)', borderRadius: 14, padding: '12px 14px', borderInlineStart: `4px solid ${c}` }}>
        <div style={{ fontSize: 19 }}>{ic}</div>
        <div style={{ fontWeight: 900, fontSize: 20, color: c }}>{v}</div>
        <div style={{ fontSize: 11.5, color: 'var(--muted)' }}>{l}</div>
    </div>
);

const Step = ({ n, t }) => (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '14px 0 8px' }}>
        <span style={{ fontWeight: 900, color: 'var(--gold)' }}>{n}</span>
        <b style={{ fontSize: 13.5 }}>{t}</b>
        <span style={{ flex: 1, height: 1, background: 'var(--line)' }} />
    </div>
);

function Field({ label, err, children }) {
    return <div className="field" style={{ margin: '0 0 8px' }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
