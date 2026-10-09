import { useState, useEffect } from 'react';
import { usePage, useForm, Link } from '@inertiajs/react';
import axios from 'axios';
import ThemedDash from '@/Layouts/ThemedDash';
import SentencePlayer from '@/Components/Audio/SentencePlayer';
import AudioRecorder from '@/Components/Audio/AudioRecorder';
import SubmissionViewer from '@/Components/SubmissionViewer';
import shrinkImage from '@/lib/shrinkImage';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** دانش‌آموز: یک املا/روخوانی — گوش بده، بنویس/بخوان، بفرست؛ نمره و برگه‌ی تصحیح‌شده را ببین. */
export default function AudioTaskPlay() {
    const { task, submitted, submitXp = 5, flash, errors = {} } = usePage().props;
    const dictation = task.kind === 'dictation';
    const [step, setStep] = useState(submitted ? 3 : 1);
    const [preview, setPreview] = useState(null);
    const [viewing, setViewing] = useState(false);
    const [banner, setBanner] = useState(null);
    const form = useForm({ file: null, note: '' });
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const played = () => axios.post(route('listen.played', task.id)).catch(() => {});
    const pickPhoto = async (e) => {
        const f = await shrinkImage(e.target.files[0] || null);
        form.setData('file', f);
        setPreview(f && f.type.startsWith('image/') ? URL.createObjectURL(f) : null);
    };
    const send = (e) => {
        e.preventDefault();
        form.post(route('listen.submit', task.id), { forceFormData: true, preserveScroll: true, onSuccess: () => { setStep(3); setPreview(null); form.reset(); } });
    };

    return (
        <ThemedDash title={task.title} active="listen">
            {banner && <div className="k3-card" style={{ background: 'linear-gradient(135deg,#2bb673,#0f9d58)', fontWeight: 800, marginBottom: 12 }}>{banner}</div>}
            <div className={`k3-card lt-hero ${task.kind}`}>
                <div className="lt-hero-ic">{dictation ? '📝' : '🎙️'}</div>
                <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 900, fontSize: 19 }}>{task.title}</div>
                    <div style={{ opacity: .9, fontSize: 13 }}>{dictation ? 'املای صوتی' : 'روخوانی'}{task.due ? ` · ⏰ مهلت: ${task.due}` : ''}</div>
                </div>
                <Link href={route('listen')} className="k3-btn" style={{ background: 'rgba(0,0,0,.25)', fontSize: 13 }}>← همه</Link>
            </div>

            {/* مراحل */}
            <div className="lt-steps">
                {(dictation ? ['🎧 گوش بده و بنویس', '📸 عکسش را بفرست', '✅ نمره'] : ['🎧 گوش بده', '🎙️ بخوان و ضبط کن', '✅ نمره']).map((s, k) => (
                    <button key={k} type="button" className={`${step === k + 1 ? 'on' : ''} ${step > k + 1 ? 'done' : ''}`} onClick={() => setStep(k + 1)}>{fa(k + 1)}. {s}</button>
                ))}
            </div>

            {step === 1 && (
                <div className="k3-card" style={{ marginTop: 12 }}>
                    {dictation ? (
                        <div className="lt-tip">✏️ یک برگه و مداد بردار. بالای برگه اسمت و عنوانِ املا را بنویس. هر جمله را گوش بده، بنویس، بعد برو جمله‌ی بعد. هر جا لازم شد «🔁 دوباره» را بزن.</div>
                    ) : (
                        <div className="lt-tip">👂 اول به خوانشِ معلم خوب گوش بده؛ بعد خودت با صدای بلند و شمرده بخوان.</div>
                    )}
                    <SentencePlayer task={task} onPlayed={played} />
                    {!dictation && task.reading_text && <div className="sv-reading-text" style={{ marginTop: 12 }}>{task.reading_text}</div>}
                    <button type="button" className="k3-btn" style={{ width: '100%', marginTop: 12 }} onClick={() => setStep(2)}>{dictation ? 'نوشتم! بریم عکس بگیریم ←' : 'آماده‌ام بخوانم ←'}</button>
                </div>
            )}

            {step === 2 && (
                <form className="k3-card" style={{ marginTop: 12 }} onSubmit={send}>
                    {dictation ? (
                        <>
                            <div className="lt-tip">📸 برگه را روی میز صاف بگذار، در نورِ خوب از بالا عکس بگیر تا همه‌ی نوشته‌ها خوانا باشد.</div>
                            <label className="lt-photo">
                                {preview ? <img src={preview} alt="پیش‌نمایش" /> : <><span>📷</span><b>عکس بگیر یا انتخاب کن</b></>}
                                <input type="file" accept="image/*,application/pdf" capture="environment" onChange={pickPhoto} />
                            </label>
                        </>
                    ) : (
                        <>
                            {task.reading_text && <div className="sv-reading-text" style={{ marginBottom: 12 }}>{task.reading_text}</div>}
                            <AudioRecorder dark label="روخوانی‌ات را ضبط کن" max={600} onDone={(f) => form.setData('file', f)} />
                        </>
                    )}
                    <input className="input" style={{ marginTop: 10 }} placeholder="یادداشت برای معلم (اختیاری)" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} maxLength={300} />
                    {(errors.file || form.errors.file) && <div className="sp-msg" style={{ marginTop: 8 }}>{errors.file || form.errors.file}</div>}
                    <button type="submit" className="k3-btn" style={{ width: '100%', marginTop: 12, background: 'linear-gradient(180deg,#ffd23f,#e9a400)', color: '#2b1d00' }} disabled={!form.data.file || form.processing}>
                        {form.processing ? 'در حالِ فرستادن…' : `📨 بفرست برای معلم${!submitted ? ` (+${fa(submitXp)} امتیاز)` : ''}`}
                    </button>
                </form>
            )}

            {step === 3 && (
                <div className="k3-card" style={{ marginTop: 12, textAlign: 'center', lineHeight: 2 }}>
                    {!submitted ? (
                        <>هنوز چیزی نفرستاده‌ای. <button type="button" className="k3-btn" onClick={() => setStep(1)}>شروع کن</button></>
                    ) : submitted.graded ? (
                        <>
                            <div style={{ fontSize: 42 }}>{submitted.grade === 'خیلی خوب' || (submitted.score ?? 0) >= 18 ? '🏆' : '⭐'}</div>
                            <div style={{ fontSize: 22, fontWeight: 900 }}>{submitted.grade || `${fa(submitted.score)} از ۲۰`}</div>
                            {submitted.mistakes != null && <div>✏️ تعدادِ غلط: {fa(submitted.mistakes)}</div>}
                            {submitted.feedback && <div style={{ marginTop: 6 }}>💬 {submitted.feedback}</div>}
                            <button type="button" className="k3-btn" style={{ marginTop: 10 }} onClick={() => setViewing(true)}>{submitted.audio ? '🎧 گوش دادن به صدای خودم' : '🖍️ دیدنِ برگه‌ی تصحیح‌شده'}</button>
                        </>
                    ) : (
                        <>
                            <div style={{ fontSize: 42 }}>⏳</div>
                            <div style={{ fontWeight: 900 }}>فرستادی! منتظرِ تصحیحِ معلم باش.</div>
                            <div style={{ opacity: .85, fontSize: 13 }}>{submitted.date}</div>
                            <div style={{ display: 'flex', gap: 8, justifyContent: 'center', marginTop: 10, flexWrap: 'wrap' }}>
                                <button type="button" className="k3-btn" onClick={() => setViewing(true)}>👀 دیدنِ چیزی که فرستادم</button>
                                <button type="button" className="k3-btn" style={{ background: 'rgba(0,0,0,.25)' }} onClick={() => setStep(2)}>🔁 فرستادنِ نسخه‌ی تازه</button>
                            </div>
                        </>
                    )}
                </div>
            )}
            {viewing && submitted && <SubmissionViewer items={[submitted]} onClose={() => setViewing(false)} />}
        </ThemedDash>
    );
}
