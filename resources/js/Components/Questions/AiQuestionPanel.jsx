import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { aiFailText } from '@/lib/aiErrors';
import CurriculumFields from './CurriculumFields';
import { fa, TYPE_FA, DIFF_FA, BLOOM_FA, DIFF_COLOR, contextLine, contextPayload } from './shared';

/**
 * دستیارِ طراحیِ سؤال با هوش مصنوعی — مشترکِ آزمون‌ساز و استودیوی بازی.
 *
 * هیچ سؤالی بدونِ تأییدِ معلم واردِ آزمون/بازی نمی‌شود: نتیجه با جزئیاتِ
 * کامل (گزینه‌ی درست، توضیح، راهنما، دشواری و سطحِ شناختی) نمایش داده
 * می‌شود و معلم انتخاب می‌کند. سؤال‌های فعلیِ فرم و نتیجه‌های قبلی به
 * سرور فرستاده می‌شوند تا تکراری ساخته نشوند.
 *
 * props:
 *   endpoint      نشانیِ API
 *   context       زمینه‌ی درسی (CurriculumFields)
 *   classes       برای نمایشِ نامِ کلاس
 *   types         نوع‌های مجاز، مثلاً ['mc','tf','blank','desc']
 *   typeLabels    برچسبِ دلخواهِ نوع‌ها (بازی: blank = «پاسخِ کوتاه»)
 *   defaults      {count, types, difficulty, bloom}
 *   maxCount      سقفِ تعداد
 *   kind          نوعِ آزمون (تشخیصی، تمرینی…) — روی سبکِ سؤال اثر دارد
 *   flavors       [{name, emoji}] برای «فضای داستانی»
 *   flavorDefault نامِ تیم/تمِ پیش‌فرض
 *   existing      متنِ سؤال‌های فعلیِ فرم (برای جلوگیری از تکرار)
 *   onAdd         (questions) => void
 */
export default function AiQuestionPanel({
    endpoint, context = {}, classes = [], types = ['mc', 'tf'], typeLabels = {}, defaults = {},
    maxCount = 15, kind = '', flavors = [], flavorDefault = '', existing = [], onAdd, onContext,
}) {
    const labels = { ...TYPE_FA, ...typeLabels };
    const [cfg, setCfg] = useState({
        count: defaults.count ?? 5,
        types: defaults.types ?? [types[0]],
        difficulty: defaults.difficulty ?? 'medium',
        bloom: defaults.bloom ?? 'mixed',
        flavor: '',
        instructions: '',
        sample: false,
    });
    const [more, setMore] = useState(false);
    const [busy, setBusy] = useState(false);
    const [elapsed, setElapsed] = useState(0);
    const [msg, setMsg] = useState(null);
    const [results, setResults] = useState([]);
    const [shown, setShown] = useState([]); // همه‌ی نتیجه‌های این جلسه — برای «تولیدِ دوباره بدونِ تکرار»
    const timer = useRef(null);

    useEffect(() => () => clearInterval(timer.current), []);

    const set = (k, v) => setCfg((c) => ({ ...c, [k]: v }));
    const toggleType = (t) => setCfg((c) => {
        const has = c.types.includes(t);
        const next = has ? c.types.filter((x) => x !== t) : [...c.types, t];
        return { ...c, types: next.length ? next : [t] };
    });

    const ready = !!context.subject;
    const summary = contextLine(context, classes);

    const run = async () => {
        // بدونِ درس، هوش مصنوعی نمی‌داند چه بسازد — پیامِ روشن به‌جای دکمه‌ی بی‌صدا
        if (!ready) {
            setMsg({ ok: false, text: 'برای طراحیِ سؤال اول «درس» را انتخاب کنید' + (onContext ? ' (همین بالا، در همین کادر).' : ' (گامِ «اطلاعاتِ پایه»).') });
            return;
        }
        setBusy(true); setMsg(null); setResults([]); setElapsed(0);
        const t0 = Date.now();
        clearInterval(timer.current);
        timer.current = setInterval(() => setElapsed(Math.round((Date.now() - t0) / 1000)), 500);
        try {
            const { data } = await axios.post(endpoint, {
                ...contextPayload(context),
                kind,
                count: Math.max(1, Math.min(maxCount, +cfg.count || 1)),
                types: cfg.types,
                difficulty: cfg.difficulty,
                bloom: cfg.bloom,
                flavor: cfg.flavor || flavorDefault || '',
                instructions: cfg.instructions,
                sample: cfg.sample,
                avoid: [...existing, ...shown].filter(Boolean).slice(-80),
            });
            // پاسخِ غیرِ JSON یا خالی (مثلاً قطعِ PHP روی هاست، صفحه‌ی خطای فایروال یا ورودِ دوباره)
            if (!data || typeof data !== 'object' || !('ok' in data)) {
                setMsg({ ok: false, text: aiFailText(200, data) });
                clearInterval(timer.current);
                setBusy(false);
                return;
            }
            const stats = data.stats || {};
            setMsg({
                ok: !!data.ok, mode: data.mode,
                text: data.message || (data.ok ? null : 'هوش مصنوعی پاسخِ قابلِ استفاده‌ای برنگرداند؛ دوباره تلاش کنید یا مبحث را دقیق‌تر بنویسید.'),
                dropped: stats.dropped || 0, seconds: stats.seconds,
            });
            if (data.ok) {
                const src = data.mode === 'sample' ? 'sample' : 'ai';
                const list = (data.questions || []).map((q) => ({ ...q, source: src, _pick: true }));
                setResults(list);
                setShown((s) => [...s, ...list.map((q) => q.prompt)]);
            }
        } catch (e) {
            const err = e.response?.data;
            const first = err && typeof err === 'object' && err.errors ? Object.values(err.errors)[0]?.[0] : null;
            setMsg({ ok: false, text: first || (typeof err === 'object' && err?.message) || aiFailText(e.response?.status, err) });
        }
        clearInterval(timer.current);
        setBusy(false);
    };

    const picked = results.filter((q) => q._pick);
    const add = () => {
        onAdd(picked.map(({ _pick, ...q }) => q));
        setResults([]); setMsg(null);
    };
    const pickAll = (v) => setResults((r) => r.map((q) => ({ ...q, _pick: v })));

    return (
        <div className="qk-panel qk-ai">
            <div className="qk-head">
                <b>🤖 طراحیِ سؤال با هوش مصنوعی</b>
                <span className="qk-muted">از روی «اطلاعاتِ پایه»ی همین فرم</span>
            </div>

            <div className={`qk-summary ${ready ? '' : 'warn'}`}>
                {ready ? <>🎯 {summary}{context.goal ? <em> — هدف: {context.goal}</em> : null}</> : (onContext ? '👇 اول کلاس و درس (و ترجیحاً فصل) را همین‌جا انتخاب کنید:' : 'ابتدا در گامِ «اطلاعاتِ پایه» کلاس و درس (و ترجیحاً فصل) را انتخاب کنید.')}
            </div>
            {!ready && onContext && (
                <div className="qk-inline-ctx">
                    <CurriculumFields classes={classes} value={context} onChange={onContext} showGoal={false} />
                </div>
            )}

            <div className="qk-grid">
                <label className="qk-field">
                    <span>تعداد</span>
                    <input type="number" min={1} max={maxCount} className="qk-input" dir="ltr" value={cfg.count}
                        onChange={(e) => set('count', e.target.value)} />
                </label>
                <div className="qk-field qk-wide">
                    <span>نوعِ سؤال <small>(چند نوع = ترکیبی)</small></span>
                    <div className="qk-chips">
                        {types.map((t) => (
                            <button type="button" key={t} onClick={() => toggleType(t)} className={`qk-chip ${cfg.types.includes(t) ? 'on' : ''}`}>{labels[t]}</button>
                        ))}
                    </div>
                </div>
                <div className="qk-field qk-wide">
                    <span>دشواری</span>
                    <div className="qk-chips">
                        {['easy', 'medium', 'hard', 'mixed'].map((d) => (
                            <button type="button" key={d} onClick={() => set('difficulty', d)} className={`qk-chip ${cfg.difficulty === d ? 'on' : ''}`}>{DIFF_FA[d]}</button>
                        ))}
                    </div>
                </div>
            </div>

            <button type="button" className="qk-link" onClick={() => setMore(!more)}>{more ? '▴ تنظیماتِ کمتر' : '▾ تنظیماتِ بیشتر (سطحِ شناختی، فضای داستانی، توضیح برای هوش مصنوعی)'}</button>
            {more && (
                <div className="qk-grid" style={{ marginTop: 8 }}>
                    <div className="qk-field qk-wide">
                        <span>سطحِ شناختی</span>
                        <div className="qk-chips">
                            {['mixed', 'remember', 'understand', 'apply', 'analyze'].map((b) => (
                                <button type="button" key={b} onClick={() => set('bloom', b)} className={`qk-chip ${cfg.bloom === b ? 'on' : ''}`}>{BLOOM_FA[b]}</button>
                            ))}
                        </div>
                    </div>
                    <label className="qk-field">
                        <span>فضای داستانی</span>
                        <select className="qk-input" value={cfg.flavor} onChange={(e) => set('flavor', e.target.value)}>
                            <option value="">{flavorDefault ? `خودکار: ${flavorDefault}` : 'بدونِ فضای داستانی'}</option>
                            {flavors.map((f) => <option key={f.name} value={f.name}>{f.emoji} {f.name}</option>)}
                        </select>
                    </label>
                    <label className="qk-field qk-wide">
                        <span>توضیح برای هوش مصنوعی <small>(اختیاری)</small></span>
                        <textarea className="qk-input" rows={2} maxLength={500} value={cfg.instructions} onChange={(e) => set('instructions', e.target.value)}
                            placeholder="مثلاً: از مثال‌های خرید از بازار استفاده کن؛ عددها کوچک‌تر از ۱۰۰ باشند؛ سؤالِ تصویری نساز." />
                    </label>
                </div>
            )}

            <div className="qk-actions">
                <button type="button" className={`qk-btn ${ready ? '' : 'wait'}`} onClick={run} disabled={busy}>
                    {busy ? `⏳ در حالِ طراحی… ${fa(elapsed)} ثانیه` : '✨ طراحیِ سؤال'}
                </button>
                <label className="qk-check"><input type="checkbox" checked={cfg.sample} onChange={(e) => set('sample', e.target.checked)} /> حالتِ نمونه (بدونِ کلیدِ هوش مصنوعی)</label>
            </div>

            {msg && (
                <div className={`qk-msg ${msg.ok ? 'ok' : 'bad'}`}>
                    {msg.mode === 'sample' && <span className="qk-tag sample">نمونه</span>}
                    {msg.text || (msg.ok ? `${fa(results.length)} سؤال آماده شد${msg.seconds ? ` (${fa(msg.seconds)} ثانیه)` : ''}.` : '')}
                    {msg.ok && msg.dropped > 0 && !msg.text && <span className="qk-muted"> — {fa(msg.dropped)} سؤالِ ضعیف یا تکراری کنار گذاشته شد.</span>}
                </div>
            )}

            {results.length > 0 && (
                <div className="qk-results">
                    <div className="qk-bar">
                        <span className="qk-muted">روی هر سؤال بزنید تا انتخاب/حذف شود — هیچ سؤالی بدونِ تأییدِ شما اضافه نمی‌شود.</span>
                        <button type="button" className="qk-link" onClick={() => pickAll(true)}>انتخابِ همه</button>
                        <button type="button" className="qk-link" onClick={() => pickAll(false)}>هیچ‌کدام</button>
                    </div>
                    {results.map((q, i) => (
                        <QuestionCard key={i} q={q} labels={labels} picked={q._pick}
                            onToggle={() => setResults((r) => r.map((x, j) => (j === i ? { ...x, _pick: !x._pick } : x)))} />
                    ))}
                    <div className="qk-actions">
                        <button type="button" className="qk-btn" onClick={add} disabled={!picked.length}>➕ افزودنِ {fa(picked.length)} سؤال</button>
                        <button type="button" className="qk-btn ghost" onClick={run} disabled={busy}>🔁 طراحیِ دوباره (بدونِ تکرارِ این‌ها)</button>
                    </div>
                </div>
            )}
        </div>
    );
}

/** کارتِ پیش‌نمایشِ یک سؤال — در دستیار و بانک مشترک است. */
export function QuestionCard({ q, labels = TYPE_FA, picked, onToggle, meta, disabled }) {
    const isChoice = q.type === 'mc' || q.type === 'tf';
    return (
        <div className={`qk-q ${picked ? 'on' : ''} ${disabled ? 'off' : ''}`} onClick={disabled ? undefined : onToggle} role="checkbox" aria-checked={!!picked}>
            <div className="qk-q-top">
                <span className="qk-box">{picked ? '✓' : ''}</span>
                <span className="qk-tag">{labels[q.type] || q.type}</span>
                {q.difficulty && <span className="qk-tag" style={{ color: DIFF_COLOR[q.difficulty], borderColor: DIFF_COLOR[q.difficulty] }}>{DIFF_FA[q.difficulty]}</span>}
                {q.bloom && <span className="qk-tag soft">{BLOOM_FA[q.bloom]}</span>}
                {q.topic && <span className="qk-tag soft">{q.topic}</span>}
                {disabled && <span className="qk-tag done">افزوده‌شده</span>}
            </div>
            <div className="qk-q-prompt">{q.prompt}</div>
            {isChoice && (
                <ol className="qk-choices">
                    {(q.choices || []).map((c, j) => <li key={j} className={c.correct ? 'right' : ''}>{c.value}{c.correct ? ' ✓' : ''}</li>)}
                </ol>
            )}
            {!isChoice && q.answer && <div className="qk-answer">پاسخ: <b>{q.answer}</b></div>}
            {(q.explanation || q.hint) && (
                <div className="qk-extra">
                    {q.explanation && <div>💡 {q.explanation}</div>}
                    {q.hint && <div>🧭 راهنما: {q.hint}</div>}
                </div>
            )}
            {meta && <div className="qk-meta">{meta}</div>}
        </div>
    );
}
