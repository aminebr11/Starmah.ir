import { useState } from 'react';
import { usePage, useForm, Link, router } from '@inertiajs/react';
import axios from 'axios';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const SHAPES = ['▲', '◆', '●', '■'];
const PHASE = { draft: 'پیش‌نویس', lobby: '⏳ منتظرِ شروع', question: '🔴 در حالِ اجرا', reveal: '🔴 در حالِ اجرا', end: '🏁 تمام‌شده' };
const blank = () => ({ prompt: '', choices: ['', '', '', ''], answer: 0 });

/** معلم: «🏆 مسابقه‌ی زنده» — ساختن و رفتن به تخته. */
export default function LiveContests() {
    const { contests = [], classrooms = [], exams = [], games = [], subjects = [], ready = true, errors = {} } = usePage().props;
    const [open, setOpen] = useState(contests.length === 0);
    const [imp, setImp] = useState({ source: 'bank', id: '', subject: subjects[0] || '', topic: '', n: 10 });
    const [busy, setBusy] = useState(false);
    const [note, setNote] = useState(null);
    const form = useForm({ title: '', classroom_id: classrooms[0]?.id ?? '', mode: 'manual', starts_at: '', seconds: 20, questions: [blank()] });
    const d = form.data;
    const qs = d.questions;
    const setQ = (i, patch) => form.setData('questions', qs.map((q, k) => (k === i ? { ...q, ...patch } : q)));
    const setChoice = (i, c, v) => setQ(i, { choices: qs[i].choices.map((x, k) => (k === c ? v : x)) });
    const drop = (i) => form.setData('questions', qs.filter((_, k) => k !== i));
    const move = (i, dir) => { const a = [...qs]; const j = i + dir; if (j < 0 || j >= a.length) return; [a[i], a[j]] = [a[j], a[i]]; form.setData('questions', a); };

    const doImport = async () => {
        setBusy(true); setNote(null);
        try {
            const { data } = await axios.get(route('teacher.live.import'), { params: imp });
            const got = (data.questions || []).map((q) => ({ prompt: q.prompt, choices: [...q.choices, '', '', '', ''].slice(0, 4), answer: q.answer }));
            if (!got.length) { setNote('سؤالِ چندگزینه‌ای یا درست/غلطی پیدا نشد.'); return; }
            const keep = qs.filter((q) => q.prompt.trim());
            form.setData('questions', [...keep, ...got].slice(0, 40));
            setNote(`✅ ${fa(got.length)} سؤال اضافه شد.`);
        } catch { setNote('آوردنِ سؤال‌ها نشد؛ دوباره امتحان کنید.'); } finally { setBusy(false); }
    };
    const submit = (e) => {
        e.preventDefault();
        form.transform((x) => ({ ...x, questions: x.questions.map((q) => {
            const filled = q.choices.map((c, k) => [c.trim(), k]).filter(([c]) => c);
            return { prompt: q.prompt, choices: filled.map(([c]) => c), answer: Math.max(0, filled.findIndex(([, k]) => k === q.answer)) };
        }) }));
        form.post(route('teacher.live.store'), { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } });
    };
    const firstErr = Object.entries(errors).find(([k]) => k.startsWith('questions'))?.[1];

    return (
        <DashLayout title="مسابقه‌ی زنده" roleLabel="معلم" menu={teacherMenu} active="live">
            <div className="at-hero lv-hero">
                <div className="at-hero-ic">🏆</div>
                <div>
                    <h2>مسابقه‌ی زنده‌ی کلاس</h2>
                    <p>سؤال روی تخته‌ی هوشمند یا ویدئوپروژکتور می‌آید و بچه‌ها با گوشی یا تبلت جواب می‌دهند. نمودارِ زنده‌ی پاسخ‌ها، جدولِ امتیاز و سکوی قهرمانی؛ برای شروع یا پایانِ درس، یا یک مسابقه‌ی همگانی سرِ یک ساعتِ مشخص.</p>
                </div>
                <button type="button" className="btn" onClick={() => setOpen(!open)}>{open ? '✕ بستنِ فرم' : '➕ مسابقه‌ی تازه'}</button>
            </div>
            {!ready && <div className="panel" style={{ background: '#fff4f4' }}>جدول‌های این بخش هنوز روی سرور ساخته نشده‌اند؛ مدیرِ کل از «🩺 سلامتِ سیستم» مایگریشن‌ها را اجرا کند.</div>}

            {open && (
                <form className="panel at-form" onSubmit={submit}>
                    <div className="at-grid">
                        <label>عنوان
                            <input className="input" value={d.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً مسابقه‌ی ضربِ دو رقمی" />
                        </label>
                        <label>شرکت‌کننده‌ها
                            <select className="input" value={d.classroom_id} onChange={(e) => form.setData('classroom_id', e.target.value)}>
                                {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                <option value="">همه‌ی کلاس‌هایم (مسابقه‌ی همگانی)</option>
                            </select>
                        </label>
                        <label>زمانِ هر سؤال
                            <select className="input" value={d.seconds} onChange={(e) => form.setData('seconds', Number(e.target.value))}>
                                {[10, 15, 20, 30, 45, 60].map((s) => <option key={s} value={s}>{fa(s)} ثانیه</option>)}
                            </select>
                        </label>
                    </div>
                    {errors.title && <div className="rm-err">{errors.title}</div>}

                    <div className="at-kinds">
                        {[['manual', '🧑‍🏫', 'خودم روی تخته اجرا می‌کنم', 'رفتن به سؤالِ بعد با شما؛ اگر ساعت بگذارید، سرِ همان ساعت خودش شروع می‌شود'], ['auto', '⏰', 'کاملاً خودکار', 'سرِ ساعت شروع می‌شود و بدونِ تخته هم تا آخر جلو می‌رود']].map(([k, ic, t, sub]) => (
                            <button key={k} type="button" className={`at-kind ${d.mode === k ? 'on' : ''}`} onClick={() => form.setData('mode', k)}>
                                <span>{ic}</span><b>{t}</b><small>{sub}</small>
                            </button>
                        ))}
                    </div>
                    <label className="at-text">{d.mode === 'auto' ? 'زمانِ شروع' : 'زمانِ شروع (اختیاری؛ سرِ این ساعت مسابقه خودش برای بچه‌ها باز می‌شود)'}
                        <JalaliDatePicker value={d.starts_at} onChange={(v) => form.setData('starts_at', v)} withTime placeholder="انتخابِ تاریخ و ساعت" />
                    </label>
                    {errors.starts_at && <div className="rm-err">{errors.starts_at}</div>}

                    <div className="lv-import">
                        <b>📥 آوردنِ سؤال</b>
                        <select className="input" value={imp.source} onChange={(e) => setImp({ ...imp, source: e.target.value, id: '' })}>
                            <option value="bank">از بانکِ سؤال</option>
                            <option value="exam" disabled={!exams.length}>از آزمون‌هایم</option>
                            <option value="game" disabled={!games.length}>از بازی‌هایم</option>
                        </select>
                        {imp.source === 'bank' ? (
                            <>
                                <select className="input" value={imp.subject} onChange={(e) => setImp({ ...imp, subject: e.target.value })}>
                                    <option value="">همه‌ی درس‌ها</option>
                                    {subjects.map((s) => <option key={s} value={s}>{s}</option>)}
                                </select>
                                <input className="input" placeholder="موضوع (اختیاری)" value={imp.topic} onChange={(e) => setImp({ ...imp, topic: e.target.value })} />
                                <select className="input" value={imp.n} onChange={(e) => setImp({ ...imp, n: e.target.value })}>
                                    {[5, 10, 15, 20].map((n) => <option key={n} value={n}>{fa(n)} سؤال</option>)}
                                </select>
                            </>
                        ) : (
                            <select className="input" value={imp.id} onChange={(e) => setImp({ ...imp, id: e.target.value })}>
                                <option value="">انتخاب…</option>
                                {(imp.source === 'exam' ? exams : games).map((x) => <option key={x.id} value={x.id}>{x.title}</option>)}
                            </select>
                        )}
                        <button type="button" className="btn btn-sm" disabled={busy || (imp.source !== 'bank' && !imp.id)} onClick={doImport}>{busy ? '…' : 'بیاور'}</button>
                        {note && <span className="lv-note">{note}</span>}
                    </div>

                    <div className="lv-qs">
                        {qs.map((q, i) => (
                            <div key={i} className="lv-q">
                                <div className="lv-q-head">
                                    <b>سؤالِ {fa(i + 1)}</b>
                                    <span>
                                        <button type="button" onClick={() => move(i, -1)} disabled={i === 0} aria-label="بالا">↑</button>
                                        <button type="button" onClick={() => move(i, 1)} disabled={i === qs.length - 1} aria-label="پایین">↓</button>
                                        <button type="button" onClick={() => drop(i)} disabled={qs.length === 1} aria-label="حذف">🗑️</button>
                                    </span>
                                </div>
                                <textarea className="input" rows={2} value={q.prompt} onChange={(e) => setQ(i, { prompt: e.target.value })} placeholder="متنِ سؤال…" />
                                <div className="lv-choices">
                                    {q.choices.map((c, k) => (
                                        <label key={k} className={`lv-choice c${k} ${q.answer === k ? 'ok' : ''}`}>
                                            <input type="radio" name={`ans${i}`} checked={q.answer === k} onChange={() => setQ(i, { answer: k })} title="جوابِ درست" />
                                            <i>{SHAPES[k]}</i>
                                            <input className="input" value={c} onChange={(e) => setChoice(i, k, e.target.value)} placeholder={k < 2 ? `گزینه‌ی ${fa(k + 1)}` : 'اختیاری'} />
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                    <button type="button" className="btn btn-ghost" onClick={() => form.setData('questions', [...qs, blank()])} disabled={qs.length >= 40}>➕ سؤالِ دیگر</button>
                    {(firstErr || errors.questions) && <div className="rm-err">{errors.questions || firstErr}</div>}
                    <button type="submit" className="btn at-go" disabled={form.processing}>{form.processing ? 'در حالِ ذخیره…' : `🏆 ساختنِ مسابقه (${fa(qs.length)} سؤال)`}</button>
                </form>
            )}

            <div className="at-list">
                {contests.length === 0 ? <div className="panel" style={{ color: 'var(--muted)' }}>هنوز مسابقه‌ای نساخته‌اید.</div> : contests.map((c) => (
                    <div key={c.id} className="at-card lv-card">
                        <div className="at-card-ic">{c.phase === 'end' ? '🏁' : '🏆'}</div>
                        <div className="at-card-b">
                            <b>{c.title}</b>
                            <span>{c.classroom} · {fa(c.questions)} سؤال · {fa(c.seconds)} ثانیه · {c.mode === 'auto' ? '⏰ خودکار' : '🧑‍🏫 دستی'}</span>
                            <span>{c.when ? `🗓️ ${c.when}` : c.date}{c.players ? ` · 👥 ${fa(c.players)} شرکت‌کننده` : ''}{c.winner ? ` · 🥇 ${c.winner}` : ''}</span>
                        </div>
                        <span className={`at-pub ${c.phase !== 'end' ? 'on' : ''}`}>{PHASE[c.phase]}</span>
                        <div className="lv-card-act">
                            <a href={route('teacher.live.host', c.id)} className="btn btn-sm">📺 {c.phase === 'end' ? 'نتیجه روی تخته' : 'اجرا روی تخته'}</a>
                            <button type="button" className="btn btn-sm btn-ghost" onClick={() => confirm('این مسابقه حذف شود؟') && router.delete(route('teacher.live.destroy', c.id))}>🗑️</button>
                        </div>
                    </div>
                ))}
            </div>
        </DashLayout>
    );
}
