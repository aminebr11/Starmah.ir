import { Link, usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function GamePlay() {
    const { game = {}, alreadyPlayed, flash } = usePage().props;
    const questions = game.questions || [];
    const [step, setStep] = useState(0);
    const [answers, setAnswers] = useState({});
    const [done, setDone] = useState(false);
    const [result, setResult] = useState(null);

    useEffect(() => { if (flash?.flash) setResult(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const q = questions[step];
    const pick = (ci) => setAnswers({ ...answers, [step]: ci });
    const next = () => { if (step < questions.length - 1) setStep(step + 1); else finish(); };

    const finish = () => {
        setDone(true);
        router.post(route('games.submit', game.id), { answers }, { preserveScroll: true });
    };

    const answered = answers[step] != null;
    const progress = questions.length ? ((step + (done ? 1 : 0)) / questions.length) * 100 : 0;

    return (
        <ThemedDash title={game.title || 'بازی'} active="games">
            <div className="k3-card" style={{ overflow: 'hidden' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    <span style={{ fontSize: 30 }}>🎮</span>
                    <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 900, fontSize: 18 }}>{game.title}</div>
                        <div style={{ opacity: .8, fontSize: 12.5 }}>{fa(questions.length)} سؤال · جایزه: ⚡ {fa(game.points)} امتیاز</div>
                    </div>
                    <Link href={route('games')} className="k3-btn ghost" style={{ fontSize: 12, flex: 'none' }}>خروج</Link>
                </div>
                {game.desc && <div style={{ opacity: .85, fontSize: 13, marginTop: 8 }}>{game.desc}</div>}
                {alreadyPlayed && !done && <div style={{ marginTop: 10, background: 'rgba(255,255,255,.12)', borderRadius: 12, padding: '8px 12px', fontSize: 12.5 }}>ℹ️ این بازی را قبلاً انجام داده‌ای (⚡ {fa(alreadyPlayed.points)} گرفتی). می‌توانی دوباره تمرین کنی ولی امتیاز جدید تعلق نمی‌گیرد.</div>}
            </div>

            {/* نوار پیشرفت */}
            <div style={{ height: 12, borderRadius: 8, background: 'rgba(255,255,255,.14)', overflow: 'hidden', margin: '16px 0' }}>
                <div style={{ width: `${progress}%`, height: '100%', background: 'linear-gradient(90deg,var(--p1),var(--acc))', transition: 'width .4s' }} />
            </div>

            {!done && q && (
                <div className="k3-card">
                    <div style={{ fontSize: 12.5, opacity: .75, marginBottom: 6 }}>سؤال {fa(step + 1)} از {fa(questions.length)}</div>
                    <div style={{ fontWeight: 800, fontSize: 18, lineHeight: 2 }}>{q.prompt}</div>
                    <div style={{ display: 'grid', gap: 10, marginTop: 16, gridTemplateColumns: q.choices.length > 2 ? '1fr 1fr' : '1fr' }}>
                        {q.choices.map((c, ci) => {
                            const sel = answers[step] === ci;
                            return (
                                <button key={ci} onClick={() => pick(ci)}
                                    style={{ padding: 15, borderRadius: 15, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit', fontSize: 16, color: '#fff', transition: '.15s',
                                        background: sel ? 'linear-gradient(180deg,var(--p1),var(--p2))' : 'rgba(255,255,255,.08)',
                                        border: sel ? '2px solid var(--acc)' : '1px solid rgba(255,255,255,.16)',
                                        boxShadow: sel ? '0 4px 0 rgba(0,0,0,.35), inset 0 2px 0 rgba(255,255,255,.25)' : 'inset 0 2px 4px rgba(0,0,0,.25)' }}>
                                    {c.value}
                                </button>
                            );
                        })}
                    </div>
                    <button onClick={next} disabled={!answered} className="k3-btn" style={{ width: '100%', marginTop: 18, fontSize: 16, opacity: answered ? 1 : .5 }}>
                        {step < questions.length - 1 ? 'سؤال بعدی ←' : '🏁 پایان و ثبت نتیجه'}
                    </button>
                </div>
            )}

            {done && (
                <div className="k3-card" style={{ textAlign: 'center' }}>
                    <div style={{ fontSize: 54 }}>🎉</div>
                    <div style={{ fontWeight: 900, fontSize: 20, marginTop: 6 }}>{result || 'نتیجه ثبت شد!'}</div>
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'center', marginTop: 18, flexWrap: 'wrap' }}>
                        <Link href={route('games')} className="k3-btn">🎮 بازی‌های دیگر</Link>
                        <Link href="/dashboard" className="k3-btn ghost">🏠 خانه</Link>
                    </div>
                </div>
            )}
        </ThemedDash>
    );
}
