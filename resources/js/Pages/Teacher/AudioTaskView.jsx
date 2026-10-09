import { useState } from 'react';
import { usePage, Link, router, useForm } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import SentencePlayer from '@/Components/Audio/SentencePlayer';
import AudioRecorder from '@/Components/Audio/AudioRecorder';
import SubmissionsBoard from '@/Components/SubmissionsBoard';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** معلم: یک املا/روخوانی — پیش‌شنیدن، فرستادن و تصحیحِ کارِ بچه‌ها روی برگه با نمره در دفتر. */
export default function AudioTaskView() {
    const { task, submissions = [], waiting = [], grades = [] } = usePage().props;
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
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    <button type="button" className="btn" onClick={toggle}>{task.published ? '⏸️ پنهان‌کردن از بچه‌ها' : '📣 فرستادن برای کلاس'}</button>
                    <button type="button" className="btn btn-ghost" onClick={del}>🗑️ حذف</button>
                </div>
            </div>

            <div className="at-two">
                <div className="panel">
                    <h3 style={{ marginTop: 0 }}>🎧 همان چیزی که بچه‌ها می‌شنوند</h3>
                    <SentencePlayer key={task.audio || 'tts'} task={task} dark={false} />
                    <ReplaceAudio task={task} />
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
                <button type="button" className="btn btn-sm btn-ghost" onClick={() => setOpen(true)}>🎤 {task.audio ? 'عوض‌کردنِ صدا (ضبطِ دوباره)' : 'گذاشتنِ صدای خودم'}</button>
            ) : (
                <form onSubmit={save} className="panel" style={{ background: 'var(--soft, #f7f7fb)', margin: 0 }}>
                    <div style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
                        <button type="button" className={`btn btn-sm ${mode === 'voice' ? '' : 'btn-ghost'}`} onClick={() => { setMode('voice'); form.setData('audio', null); }}>🎤 ضبط</button>
                        <button type="button" className={`btn btn-sm ${mode === 'upload' ? '' : 'btn-ghost'}`} onClick={() => { setMode('upload'); form.setData('audio', null); }}>📁 فایل</button>
                    </div>
                    {mode === 'voice'
                        ? <AudioRecorder label="دوباره بخوانید" onDone={(f) => form.setData('audio', f)} />
                        : <input className="input" type="file" accept="audio/*" onChange={(e) => form.setData('audio', e.target.files[0] || null)} />}
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
