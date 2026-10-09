import { useState } from 'react';
import { usePage, useForm, Link } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import AudioRecorder from '@/Components/Audio/AudioRecorder';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const SOURCES = {
    voice: { ic: '🎤', t: 'صدای خودم', d: 'همین‌جا ضبط کنید؛ بچه‌ها صدای خودِ شما را می‌شنوند.' },
    tts: { ic: '🤖', t: 'متن ← صدای سیستم', d: 'متن را بنویسید؛ سیستم جمله‌به‌جمله و شمرده می‌خواند.' },
    upload: { ic: '📁', t: 'فایلِ صوتی', d: 'فایلِ آماده (mp3، m4a، wav…) را بارگذاری کنید.' },
};

/** معلم: «🎧 املا و روخوانی» — ساختن، فرستادن و رفتن به تصحیح. */
export default function AudioTasks() {
    const { tasks = [], classrooms = [], tts = false, ready = true, errors = {} } = usePage().props;
    const [open, setOpen] = useState(tasks.length === 0);
    const form = useForm({
        kind: classrooms.some((c) => c.dictation) ? 'dictation' : 'reading',
        classroom_id: classrooms[0]?.id ?? '', title: '', source: tts ? 'tts' : 'voice', text: '', audio: null,
        score_type: 'descriptive', penalty: 0.5, due_at: '', publish: true,
    });
    const d = form.data;
    const room = classrooms.find((c) => String(c.id) === String(d.classroom_id));
    const dictBlocked = d.kind === 'dictation' && room && !room.dictation;
    const submit = (e) => {
        e.preventDefault();
        form.post(route('teacher.audio.store'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <DashLayout title="املا و روخوانی" roleLabel="معلم" menu={teacherMenu} active="audio">
            <div className="at-hero">
                <div className="at-hero-ic">🎧</div>
                <div>
                    <h2>املا و روخوانیِ صوتی</h2>
                    <p>صدای خودتان را بگذارید یا متن بدهید تا سیستم شمرده بخواند. بچه‌ها گوش می‌دهند، روی برگه می‌نویسند و عکسش را می‌فرستند (املا) یا صدای خودشان را ضبط می‌کنند (روخوانی). شما روی برگه تصحیح می‌کنید و نمره مستقیم در <b>دفترِ نمره</b> و سوابقِ دانش‌آموز می‌نشیند.</p>
                </div>
                <button type="button" className="btn" onClick={() => setOpen(!open)}>{open ? '✕ بستنِ فرم' : '➕ املا / روخوانیِ تازه'}</button>
            </div>

            {!ready && <div className="panel" style={{ background: '#fff4f4' }}>جدول‌های این بخش هنوز روی سرور ساخته نشده‌اند؛ مدیرِ کل از «🩺 سلامتِ سیستم» مایگریشن‌ها را اجرا کند.</div>}

            {open && (
                <form className="panel at-form" onSubmit={submit}>
                    <div className="at-kinds">
                        {[['dictation', '📝', 'املای صوتی', 'گوش بده و بنویس'], ['reading', '🎙️', 'روخوانی', 'بخوان و صدایت را بفرست']].map(([k, ic, t, sub]) => (
                            <button key={k} type="button" className={`at-kind ${d.kind === k ? 'on' : ''}`} onClick={() => form.setData('kind', k)}>
                                <span>{ic}</span><b>{t}</b><small>{sub}</small>
                            </button>
                        ))}
                    </div>

                    <div className="at-grid">
                        <label>کلاس
                            <select className="input" value={d.classroom_id} onChange={(e) => form.setData('classroom_id', e.target.value)}>
                                {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}{c.grade ? ` (${c.grade})` : ''}{!c.dictation ? ' — بدونِ املا' : ''}</option>)}
                            </select>
                        </label>
                        <label>عنوان
                            <input className="input" value={d.title} onChange={(e) => form.setData('title', e.target.value)} placeholder={d.kind === 'dictation' ? 'مثلاً املای درسِ ۵ — «نوروز»' : 'مثلاً روخوانیِ درسِ «کوچه‌ی ما»'} />
                        </label>
                        <label>مهلت (اختیاری)
                            <JalaliDatePicker value={d.due_at} onChange={(v) => form.setData('due_at', v)} withTime placeholder="بدونِ مهلت" />
                        </label>
                    </div>
                    {dictBlocked && <div className="rm-err">پایه‌ی «{room.grade}» درسِ املا ندارد؛ برای این کلاس «روخوانی» بسازید.</div>}
                    {errors.classroom_id && <div className="rm-err">{errors.classroom_id}</div>}
                    {errors.title && <div className="rm-err">{errors.title}</div>}

                    <h4 className="at-h">صدا از کجا بیاید؟</h4>
                    <div className="at-sources">
                        {Object.entries(SOURCES).map(([k, s]) => (
                            <button key={k} type="button" className={`at-src ${d.source === k ? 'on' : ''}`} onClick={() => form.setData('source', k)}>
                                <span>{s.ic}</span><b>{s.t}</b><small>{s.d}</small>
                                {k === 'tts' && !tts && <em>صدای سرور خاموش است؛ صدای گوشیِ بچه‌ها پخش می‌شود</em>}
                            </button>
                        ))}
                    </div>

                    {d.source === 'voice' && (
                        <div style={{ marginTop: 10 }}>
                            <AudioRecorder label={d.kind === 'dictation' ? 'املا را آهسته و شمرده بخوانید' : 'متن را یک بار برای الگو بخوانید'} onDone={(f) => form.setData('audio', f)} />
                            <p className="at-tip">💡 برای املا بعد از هر جمله ۵ تا ۱۰ ثانیه مکث کنید تا بچه‌ها بنویسند.</p>
                        </div>
                    )}
                    {d.source === 'upload' && (
                        <input className="input" type="file" accept="audio/*" style={{ marginTop: 10 }} onChange={(e) => form.setData('audio', e.target.files[0] || null)} />
                    )}
                    {errors.audio && <div className="rm-err">{errors.audio}</div>}

                    <label className="at-text">
                        {d.kind === 'reading' ? 'متنِ روخوانی (بچه‌ها همین را می‌بینند و می‌خوانند)'
                            : d.source === 'tts' ? 'متنِ املا (به دانش‌آموز نشان داده نمی‌شود؛ هر جمله جدا خوانده می‌شود)'
                                : 'متنِ املا برای مقایسه هنگامِ تصحیح (اختیاری؛ به دانش‌آموز نشان داده نمی‌شود)'}
                        <textarea className="input" rows={6} value={d.text} onChange={(e) => form.setData('text', e.target.value)}
                            placeholder={d.kind === 'dictation' ? 'نوروز جشنِ آغازِ بهار است. مردم خانه‌هایشان را تمیز می‌کنند…' : 'متنِ درس را اینجا بنویسید یا بچسبانید…'} />
                    </label>
                    {errors.text && <div className="rm-err">{errors.text}</div>}

                    <div className="at-grid">
                        <label>نوعِ نمره
                            <select className="input" value={d.score_type} onChange={(e) => form.setData('score_type', e.target.value)}>
                                <option value="descriptive">توصیفی (خیلی خوب … نیاز به تلاش)</option>
                                <option value="numeric">عددی از ۲۰</option>
                            </select>
                        </label>
                        {d.kind === 'dictation' && d.score_type === 'numeric' && (
                            <label>کسرِ هر غلط
                                <select className="input" value={d.penalty} onChange={(e) => form.setData('penalty', e.target.value)}>
                                    {[0.25, 0.5, 0.75, 1].map((p) => <option key={p} value={p}>{fa(p)} نمره</option>)}
                                </select>
                            </label>
                        )}
                        <label className="at-check"><input type="checkbox" checked={d.publish} onChange={(e) => form.setData('publish', e.target.checked)} /> همین حالا برای کلاس بفرست (اعلان برای بچه‌ها)</label>
                    </div>

                    <button type="submit" className="btn at-go" disabled={form.processing || dictBlocked}>
                        {form.processing ? (d.source === 'tts' ? 'در حالِ ساختنِ صدا… (چند ثانیه)' : 'در حالِ ذخیره…') : d.kind === 'dictation' ? '📝 ساختنِ املا' : '🎙️ ساختنِ روخوانی'}
                    </button>
                </form>
            )}

            <div className="at-list">
                {tasks.length === 0 ? (
                    <div className="panel" style={{ color: 'var(--muted)' }}>هنوز املا یا روخوانی‌ای نساخته‌اید.</div>
                ) : tasks.map((t) => (
                    <Link key={t.id} href={route('teacher.audio.show', t.id)} className={`at-card ${t.kind}`}>
                        <div className="at-card-ic">{t.kind === 'dictation' ? '📝' : '🎙️'}</div>
                        <div className="at-card-b">
                            <b>{t.title}</b>
                            <span>{t.classroom} · {t.date}{t.due ? ` · ⏰ ${t.due}` : ''}</span>
                            <div className="at-prog"><i style={{ width: `${t.students ? Math.round((t.submitted / t.students) * 100) : 0}%` }} /></div>
                            <span>📥 {fa(t.submitted)}{t.students ? ` از ${fa(t.students)}` : ''} فرستاده · ✅ {fa(t.graded)} تصحیح‌شده {t.submitted - t.graded > 0 && <em className="at-badge">⏳ {fa(t.submitted - t.graded)} منتظر</em>}</span>
                        </div>
                        <span className={`at-pub ${t.published ? 'on' : ''}`}>{t.published ? 'فرستاده‌شده' : 'پیش‌نویس'}</span>
                    </Link>
                ))}
            </div>
        </DashLayout>
    );
}
