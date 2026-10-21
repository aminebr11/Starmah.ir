import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import JalaliDatePicker from '@/Components/JalaliDatePicker';
import { usePage, Link, router, useForm } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import SentencePlayer from '@/Components/Audio/SentencePlayer';
import AudioRecorder from '@/Components/Audio/AudioRecorder';
import SubmissionsBoard from '@/Components/SubmissionsBoard';
import AudioFilePicker from '@/Components/Audio/AudioFilePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** معلم: یک املا/روخوانی — پیش‌شنیدن، فرستادن و تصحیحِ کارِ بچه‌ها روی برگه با نمره در دفتر. */
export default function AudioTaskView() {
    const { task, submissions = [], waiting = [], grades = [], ttsOn = false } = usePage().props;
    const dictation = task.kind === 'dictation';
    const toggle = () => router.post(route('teacher.audio.publish', task.id), {}, { preserveScroll: true });
    const del = () => confirm('این املا/روخوانی و همه‌ی پاسخ‌هایش حذف شود؟ (نمره‌های ثبت‌شده در دفترِ نمره می‌مانند)') && router.delete(route('teacher.audio.destroy', task.id));

    return (
        <DashLayout title={task.title} roleLabel="معلم" menu={teacherMenu} active="audio"
            actions={<Link href={route('teacher.audio')} className="btn btn-sm btn-ghost">→ همه‌ی املا و روخوانی‌ها</Link>}>
            <div className={`at-hero ${task.kind}`}>
                <div className="at-hero-ic">{dictation ? '📝' : '🎙️'}</div>
                <div>
                    <h2>{task.title}</h2>
                    <p>{task.classroom} · {task.date}{task.due ? ` · ⏰ مهلت: ${task.due}` : ''} · نمره: {task.score_type === 'numeric' ? `عددی از ۲۰${dictation ? ` (هر غلط ${fa(task.penalty)})` : ''}` : 'توصیفی'}</p>
                </div>
                <div className="at-hero-a">
                    {task.published && <button type="button" className="btn" onClick={toggle}>⏸️ پنهان‌کردن از بچه‌ها</button>}
                    <button type="button" className="btn btn-ghost" onClick={del}>🗑️ حذف</button>
                </div>
            </div>

            <div className="at-two">
                <div className="panel">
                    <h3 style={{ marginTop: 0 }}>🎧 همان چیزی که بچه‌ها می‌شنوند</h3>
                    {task.source === 'none' ? (
                        <div className="at-novoice">📄 این روخوانی صدای الگو ندارد؛ بچه‌ها فقط متن را می‌بینند، خودشان می‌خوانند و صدایشان را می‌فرستند.</div>
                    ) : task.needs_build ? <VoiceBuilder task={task} /> : <SentencePlayer key={task.audio || 'tts'} task={task} dark={false} />}
                    <Review task={task} onPublish={toggle} />
                    <div className="at-tools">
                        <ReplaceAudio task={task} />
                        {task.source === 'tts' && !task.needs_build && (
                            <button type="button" className="btn btn-sm btn-ghost" style={{ marginTop: 12 }}
                                onClick={() => confirm('صدا با همین متن دوباره ساخته شود؟ (خواندنِ تازه)') && router.post(route('teacher.audio.rebuild', task.id), {}, { preserveScroll: true })}>🔁 ساختِ دوباره‌ی صدا</button>
                        )}
                    </div>
                    <EditTask task={task} ttsOn={ttsOn} />
                    {task.text && (
                        <details style={{ marginTop: 10 }}>
                            <summary style={{ cursor: 'pointer', fontWeight: 700 }}>📄 متنِ {dictation ? 'املا (فقط برای شما)' : 'روخوانی'}</summary>
                            <div className="sv-reading-text small" style={{ marginTop: 8 }}>{task.text}</div>
                        </details>
                    )}
                </div>
                <div className="panel">
                    <h3 style={{ marginTop: 0 }}>⏳ هنوز نفرستاده‌اند ({fa(waiting.length)})</h3>
                    {waiting.length === 0 ? <p style={{ color: 'var(--muted)' }}>🎉 همه فرستاده‌اند.</p> : (
                        <div className="pill-wrap">{waiting.map((n) => <span key={n} className="rm-pill">{n}</span>)}</div>
                    )}
                </div>
            </div>

            <div className="panel">
                <h3 style={{ marginTop: 0 }}>{dictation ? '📸 برگه‌های املای بچه‌ها' : '🎙️ صدای روخوانیِ بچه‌ها'} ({fa(submissions.length)})</h3>
                <SubmissionsBoard initial={submissions} grades={grades}
                    audio={{ kind: task.kind, score_type: task.score_type, penalty: task.penalty, text: task.text, gradeUrl: (it) => route('audio.grade', it.id) }} />
            </div>
        </DashLayout>
    );
}

/**
 * 🤖 ساختِ قدم‌به‌قدمِ صدای هوش مصنوعی: هر درخواست چند جمله؛ اگر یک جمله نشد، دلیلش و «دوباره» نشان داده می‌شود.
 * (پیش از این همه‌ی جمله‌ها در یک درخواست ساخته می‌شد و با طولانی‌شدن، ارتباط با سرویس قطع می‌شد.)
 */
function VoiceBuilder({ task }) {
    const [st, setSt] = useState({ built: 0, total: task.sentences?.length || 0 });
    const [err, setErr] = useState(null);
    const [run, setRun] = useState(1);
    const alive = useRef(true);
    useEffect(() => () => { alive.current = false; }, []);
    useEffect(() => {
        let fails = 0;
        setErr(null);
        const step = async () => {
            if (!alive.current) return;
            try {
                const { data } = await axios.post(route('teacher.audio.build', task.id), {}, { timeout: 120000 });
                if (!alive.current) return;
                setSt({ built: data.built, total: data.total });
                if (data.done) { router.reload({ preserveScroll: true }); return; }
                if (data.error) { setErr(data.error); return; }
                fails = 0;
                step();
            } catch (e) {
                // قطعیِ لحظه‌ای: خودش چند بار دوباره تلاش می‌کند
                if (++fails <= 3 && alive.current) { setTimeout(step, 2000 * fails); return; }
                setErr(e?.response?.status ? `سرور خطا داد (${fa(e.response.status)}).` : 'اتصال به سرور قطع شد.');
            }
        };
        step();
    }, [run, task.id]);
    const pct = st.total ? Math.round((st.built / st.total) * 100) : 0;
    return (
        <div className={`at-build ${err ? 'err' : ''}`}>
            <b>{err ? '⚠️ ساختِ صدا متوقف شد' : '🤖 در حالِ ساختنِ صدا با هوش مصنوعی…'}</b>
            <div className="at-build-bar"><i style={{ width: `${pct}%` }} /></div>
            <small>{fa(st.built)} از {fa(st.total)} جمله آماده است{!err && ' — صفحه را باز نگه دارید؛ جمله‌های ساخته‌شده ذخیره می‌شوند و از دست نمی‌روند.'}</small>
            {err && <><small style={{ color: '#b3261e', fontWeight: 700 }}>{err}</small>
                <button type="button" className="btn btn-sm" onClick={() => setRun((r) => r + 1)}>🔁 ادامه‌ی ساخت</button></>}
        </div>
    );
}

/** تأیید پیش از انتشار: گوش بدهید → «تأیید و انتشار»، یا زمان‌بندی، یا پیش‌نویس بماند. */
function Review({ task, onPublish }) {
    const { errors = {} } = usePage().props;
    const [open, setOpen] = useState(false);
    const [at, setAt] = useState(task.publish_at || '');
    if (task.published) return null;
    const saveAt = (v) => router.post(route('teacher.audio.schedule', task.id), { publish_at: v || null }, { preserveScroll: true, onSuccess: () => setOpen(false) });
    if (task.publish_at_fa) {
        return (
            <div className="at-review sched">
                <b>🗓️ زمان‌بندی شده: {task.publish_at_fa}</b>
                <p>{task.ready ? 'سرِ همین زمان خودش برای کلاس فرستاده می‌شود.' : '⚠️ صدا هنوز آماده نیست؛ تا آماده نشود منتشر نمی‌شود.'}</p>
                <div className="at-review-a">
                    {task.ready && <button type="button" className="btn btn-sm" onClick={onPublish}>📣 همین حالا منتشر کن</button>}
                    <button type="button" className="btn btn-sm btn-ghost" onClick={() => setOpen((o) => !o)}>✏️ تغییرِ زمان</button>
                    <button type="button" className="btn btn-sm btn-ghost" onClick={() => saveAt(null)}>لغوِ زمان‌بندی</button>
                </div>
                {open && <SchedulePick at={at} setAt={setAt} save={() => saveAt(at)} err={errors.publish_at} />}
            </div>
        );
    }
    return (
        <div className="at-review">
            <b>{task.ready ? '🎧 اول گوش بدهید؛ اگر خوب است تأیید کنید' : '⏳ هنوز منتشر نشده'}</b>
            <p>{task.ready ? 'این املا/روخوانی هنوز پیش‌نویس است و بچه‌ها آن را نمی‌بینند.' : 'وقتی صدا آماده شد، گوش بدهید و تأیید کنید.'}</p>
            <div className="at-review-a">
                <button type="button" className="btn btn-sm" disabled={!task.ready} onClick={onPublish}>✅ تأیید و انتشار برای کلاس</button>
                <button type="button" className="btn btn-sm btn-ghost" onClick={() => setOpen((o) => !o)}>🗓️ زمان‌بندیِ انتشار</button>
                <span style={{ fontSize: 12.5, color: 'var(--muted)' }}>💾 یا همین‌طور پیش‌نویس بماند (ذخیره شده است)</span>
            </div>
            {open && <SchedulePick at={at} setAt={setAt} save={() => saveAt(at)} err={errors.publish_at} />}
        </div>
    );
}
function SchedulePick({ at, setAt, save, err }) {
    return (
        <div className="at-sched">
            <div style={{ minWidth: 240 }}><JalaliDatePicker value={at} onChange={setAt} withTime placeholder="روز و ساعتِ انتشار" /></div>
            <button type="button" className="btn btn-sm" disabled={!at} onClick={save}>💾 ثبتِ زمان</button>
            {err && <div className="rm-err" style={{ width: '100%' }}>{err}</div>}
        </div>
    );
}

/** ✏️ ویرایش: عنوان، متن، مکث، مهلت؛ با عوض‌شدنِ متن، صدای هوش مصنوعی دوباره ساخته می‌شود و دوباره تأیید می‌گیرد. */
function EditTask({ task, ttsOn }) {
    const { errors = {} } = usePage().props;
    const [open, setOpen] = useState(false);
    const f = useForm({ title: task.title, text: task.text || '', pace: task.pace || 'normal', due_at: task.due_at || '', source: task.source, score_type: task.score_type, penalty: task.penalty });
    const save = (e) => { e.preventDefault(); f.put(route('teacher.audio.update', task.id), { preserveScroll: true, onSuccess: () => setOpen(false) }); };
    if (!open) return <button type="button" className="btn btn-sm btn-ghost at-edit-btn" style={{ marginTop: 10 }} onClick={() => setOpen(true)}>✏️ ویرایش (عنوان، متن، مکث، مهلت)</button>;
    return (
        <form className="at-edit" onSubmit={save}>
            <label>عنوان<input className="input" value={f.data.title} onChange={(e) => f.setData('title', e.target.value)} /></label>
            {errors.title && <div className="rm-err">{errors.title}</div>}
            <label>{task.kind === 'reading' ? 'متنِ روخوانی' : 'متنِ املا'}
                <textarea className="input" rows={6} value={f.data.text} onChange={(e) => f.setData('text', e.target.value)} />
            </label>
            {errors.text && <div className="rm-err">{errors.text}</div>}
            {errors.source && <div className="rm-err">{errors.source}</div>}
            {task.source !== 'tts' && ttsOn && f.data.text.trim() && (
                <label style={{ display: 'flex', flexDirection: 'row', gap: 8, alignItems: 'center', fontWeight: 600 }}>
                    <input type="checkbox" checked={f.data.source === 'tts'} onChange={(e) => f.setData('source', e.target.checked ? 'tts' : task.source)} />
                    🤖 صدا را با هوش مصنوعی از همین متن بساز (به‌جای صدای فعلی)
                </label>
            )}
            {task.kind === 'reading' && task.source !== 'none' && (
                <label style={{ display: 'flex', flexDirection: 'row', gap: 8, alignItems: 'center', fontWeight: 600 }}>
                    <input type="checkbox" checked={f.data.source === 'none'} onChange={(e) => f.setData('source', e.target.checked ? 'none' : task.source)} />
                    📄 بدونِ صدای الگو (بچه‌ها فقط متن را ببینند)
                </label>
            )}
            {(f.data.source === 'tts') && (
                <label>مکث بعد از هر نقطه
                    <select className="input" value={f.data.pace} onChange={(e) => f.setData('pace', e.target.value)}>
                        <option value="short">کوتاه</option><option value="normal">متوسط</option><option value="long">بلند</option>
                    </select>
                </label>
            )}
            <label>مهلت (اختیاری)<JalaliDatePicker value={f.data.due_at} onChange={(v) => f.setData('due_at', v)} withTime placeholder="بدونِ مهلت" /></label>
            {f.data.source === 'tts' && <small style={{ color: 'var(--muted)' }}>💡 اگر متن یا مکث را عوض کنید، صدا دوباره ساخته می‌شود و پیش از انتشار دوباره گوش می‌دهید.</small>}
            <div style={{ display: 'flex', gap: 8 }}>
                <button type="submit" className="btn btn-sm" disabled={f.processing}>{f.processing ? 'در حالِ ذخیره…' : '💾 ذخیره'}</button>
                <button type="button" className="btn btn-sm btn-ghost" onClick={() => setOpen(false)}>انصراف</button>
            </div>
        </form>
    );
}

/** عوض‌کردنِ صدای معلم (ضبطِ دوباره یا فایل) — پاسخ‌ها و نمره‌های بچه‌ها می‌مانند. */
function ReplaceAudio({ task }) {
    const { errors = {} } = usePage().props;
    const [open, setOpen] = useState(!!task.audio_legacy);
    const [mode, setMode] = useState('voice');
    const form = useForm({ audio: null });
    const save = (e) => {
        e.preventDefault();
        form.post(route('teacher.audio.replace', task.id), { forceFormData: true, preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } });
    };
    return (
        <div style={{ marginTop: 12 }}>
            {task.audio_legacy && (
                <div className="rm-err" style={{ marginBottom: 8 }}>
                    ⚠️ این صدا با قالبِ قدیمی (webm) ضبط شده و روی آیفون و بعضی گوشی‌ها پخش نمی‌شود. یک بار دوباره ضبط کنید؛ صدای تازه روی همه‌ی گوشی‌ها پخش می‌شود و کارهای فرستاده‌شده‌ی بچه‌ها سرِ جایشان می‌مانند.
                </div>
            )}
            {!open ? (
                <button type="button" className="btn btn-sm btn-ghost" onClick={() => setOpen(true)}>🎤 {task.audio ? 'عوض‌کردنِ صدا (ضبطِ دوباره)' : task.source === 'none' ? 'افزودنِ صدای الگو (اختیاری)' : 'گذاشتنِ صدای خودم'}</button>
            ) : (
                <form onSubmit={save} className="panel" style={{ background: 'var(--soft, #f7f7fb)', margin: 0 }}>
                    <div style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
                        <button type="button" className={`btn btn-sm ${mode === 'voice' ? '' : 'btn-ghost'}`} onClick={() => { setMode('voice'); form.setData('audio', null); }}>🎤 ضبط</button>
                        <button type="button" className={`btn btn-sm ${mode === 'upload' ? '' : 'btn-ghost'}`} onClick={() => { setMode('upload'); form.setData('audio', null); }}>📁 فایل</button>
                    </div>
                    {mode === 'voice'
                        ? <AudioRecorder label="دوباره بخوانید" onDone={(f) => form.setData('audio', f)} />
                        : <AudioFilePicker file={form.data.audio} onPick={(f) => form.setData('audio', f)} />}
                    {errors.audio && <div className="rm-err">{errors.audio}</div>}
                    <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                        <button type="submit" className="btn btn-sm" disabled={!form.data.audio || form.processing}>{form.processing ? 'در حالِ ذخیره…' : '✅ جایگزین کن'}</button>
                        <button type="button" className="btn btn-sm btn-ghost" onClick={() => setOpen(false)}>انصراف</button>
                    </div>
                </form>
            )}
        </div>
    );
}
