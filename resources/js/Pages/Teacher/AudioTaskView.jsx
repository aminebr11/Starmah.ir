import { usePage, Link, router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import SentencePlayer from '@/Components/Audio/SentencePlayer';
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
                    <SentencePlayer task={task} dark={false} />
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
