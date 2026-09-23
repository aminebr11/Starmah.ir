import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';

/**
 * «زمینه‌ی درسی» — کلاس ← درس ← فصل ← مبحث ← هدف.
 *
 * همین زمینه هم به هوش مصنوعی داده می‌شود و هم هنگامِ ذخیره روی سؤال‌ها
 * می‌نشیند، پس سؤالِ ساخته‌شده دقیقاً زیرِ همان کلاس و فصل در بانک پیدا
 * می‌شود. فصل‌ها از فهرستِ رسمیِ کتاب می‌آیند؛ اگر فصلی نبود، معلم همین‌جا
 * برای مدرسه‌اش اضافه می‌کند تا همه‌ی همکاران با همان نام دسته‌بندی کنند.
 *
 * props:
 *   classes   [{id, name, grade, level, subjects:[{name, icon}]}]
 *   value     {classroom_id, grade, level, subject, chapter_id, chapter, topic, goal}
 *   onChange  (patch) => void — فقط کلیدهای تغییرکرده
 *   showGoal  نمایشِ فیلدِ «هدف آموزشی»
 */
export default function CurriculumFields({ classes = [], value = {}, onChange, showGoal = true, errors = {} }) {
    const [chapters, setChapters] = useState([]);
    const [loading, setLoading] = useState(false);
    const [adding, setAdding] = useState(false);
    const [newTitle, setNewTitle] = useState('');
    const [saving, setSaving] = useState(false);
    const [addErr, setAddErr] = useState('');

    const cls = classes.find((c) => String(c.id) === String(value.classroom_id));
    const grade = cls?.grade || value.grade || '';
    const subjects = useMemo(() => {
        const list = (cls?.subjects || []).map((s) => s.name);
        if (value.subject && !list.includes(value.subject)) list.unshift(value.subject);
        return list;
    }, [cls, value.subject]);

    // کلاسِ پیش‌فرض: اولین کلاسِ معلم (یا کلاسی که پایه‌اش با فرم یکی است)
    useEffect(() => {
        if (value.classroom_id || !classes.length) return;
        const match = classes.find((c) => c.grade && c.grade === value.grade) || classes[0];
        onChange({ classroom_id: match.id, grade: match.grade || value.grade || '', level: match.level || '' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [classes.length]);

    // فصل‌های درسِ انتخاب‌شده
    useEffect(() => {
        if (!grade || !value.subject) { setChapters([]); return; }
        let live = true;
        setLoading(true);
        axios.get(route('curriculum.chapters'), { params: { grade, subject: value.subject } })
            .then(({ data }) => { if (live) setChapters(data.chapters || []); })
            .catch(() => live && setChapters([]))
            .finally(() => live && setLoading(false));
        return () => { live = false; };
    }, [grade, value.subject]);

    const chapter = chapters.find((c) => String(c.id) === String(value.chapter_id));
    const lessons = chapter?.lessons || [];

    const pickClass = (id) => {
        const c = classes.find((x) => String(x.id) === String(id));
        const keep = c && (c.subjects || []).some((s) => s.name === value.subject);
        onChange({
            classroom_id: c?.id || '', grade: c?.grade || '', level: c?.level || '',
            ...(keep ? {} : { subject: '', chapter_id: '', chapter: '' }),
        });
    };
    const pickChapter = (v) => {
        if (v === '__new') { setAdding(true); return; }
        const c = chapters.find((x) => String(x.id) === v);
        onChange({ chapter_id: c ? c.id : '', chapter: c ? c.label : '' });
    };
    const addChapter = async () => {
        if (!newTitle.trim()) return;
        setSaving(true); setAddErr('');
        try {
            const { data } = await axios.post(route('curriculum.chapters.store'), { grade, subject: value.subject, title: newTitle.trim() });
            const c = data.chapter;
            setChapters((list) => (list.some((x) => x.id === c.id) ? list : [...list, c].sort((a, b) => a.number - b.number)));
            onChange({ chapter_id: c.id, chapter: c.label });
            setAdding(false); setNewTitle('');
        } catch (e) {
            setAddErr(e.response?.data?.message || 'ثبتِ فصل انجام نشد.');
        }
        setSaving(false);
    };

    const legacyChapter = value.chapter && !value.chapter_id ? value.chapter : null;

    return (
        <div className="qk-ctx">
            <div className="qk-grid">
                <label className="qk-field">
                    <span>کلاس</span>
                    {classes.length ? (
                        <select className="qk-input" value={value.classroom_id || ''} onChange={(e) => pickClass(e.target.value)}>
                            {classes.map((c) => <option key={c.id} value={c.id}>{c.name}{c.grade ? ` — پایه‌ی ${c.grade}` : ''}</option>)}
                        </select>
                    ) : (
                        <input className="qk-input" value={value.grade || ''} onChange={(e) => onChange({ grade: e.target.value })} placeholder="پایه، مثلاً چهارم" />
                    )}
                </label>

                <label className="qk-field">
                    <span>درس <i className="qk-req">*</i></span>
                    {subjects.length ? (
                        <select className="qk-input" value={value.subject || ''} onChange={(e) => onChange({ subject: e.target.value, chapter_id: '', chapter: '' })}>
                            <option value="">— انتخابِ درس —</option>
                            {subjects.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                    ) : (
                        <input className="qk-input" value={value.subject || ''} onChange={(e) => onChange({ subject: e.target.value, chapter_id: '', chapter: '' })} placeholder="مثلاً ریاضی" />
                    )}
                    {errors.subject && <em className="qk-err">{errors.subject}</em>}
                </label>

                <label className="qk-field">
                    <span>فصل {loading && <small>…</small>}</span>
                    {!adding ? (
                        <select className="qk-input" value={value.chapter_id || (legacyChapter ? '__legacy' : '')} onChange={(e) => pickChapter(e.target.value)} disabled={!value.subject}>
                            <option value="">{value.subject ? (chapters.length ? '— همه‌ی فصل‌ها —' : 'فصلی ثبت نشده') : 'اول درس را انتخاب کنید'}</option>
                            {legacyChapter && <option value="__legacy">{legacyChapter}</option>}
                            {chapters.map((c) => <option key={c.id} value={c.id}>{c.label}{c.custom ? ' (مدرسه)' : ''}</option>)}
                            {value.subject && <option value="__new">➕ افزودنِ فصلِ جدید…</option>}
                        </select>
                    ) : (
                        <div className="qk-inline">
                            <input className="qk-input" autoFocus value={newTitle} onChange={(e) => setNewTitle(e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addChapter(); } if (e.key === 'Escape') setAdding(false); }}
                                placeholder="عنوانِ فصل، مثلاً: کسر" />
                            <button type="button" className="qk-btn sm" onClick={addChapter} disabled={saving || !newTitle.trim()}>{saving ? '…' : 'ثبت'}</button>
                            <button type="button" className="qk-btn ghost sm" onClick={() => { setAdding(false); setAddErr(''); }}>✕</button>
                        </div>
                    )}
                    {addErr && <em className="qk-err">{addErr}</em>}
                </label>

                <label className="qk-field">
                    <span>مبحث / درس</span>
                    <input className="qk-input" list="qk-lessons" value={value.topic || ''} onChange={(e) => onChange({ topic: e.target.value })}
                        placeholder={lessons.length ? `مثلاً: ${lessons[0]}` : 'مثلاً: ضربِ عددهای دورقمی'} />
                    <datalist id="qk-lessons">{lessons.map((l) => <option key={l} value={l} />)}</datalist>
                </label>

                {showGoal && (
                    <label className="qk-field qk-wide">
                        <span>هدفِ آموزشی <small>(اختیاری — دقیق‌ترش، سؤال‌های بهتر)</small></span>
                        <input className="qk-input" value={value.goal || ''} onChange={(e) => onChange({ goal: e.target.value })}
                            placeholder="مثلاً: دانش‌آموز بتواند دو عددِ دورقمی را ضرب کند و حاصل را تخمین بزند" />
                    </label>
                )}
            </div>
            {value.subject && !loading && chapters.length === 0 && (
                <div className="qk-note">
                    برای «{value.subject}»ِ پایه‌ی {grade} هنوز فصلی ثبت نشده — با «➕ افزودنِ فصلِ جدید» فصل‌های کتاب را اضافه کنید
                    تا سؤال‌ها دقیق دسته‌بندی شوند و هوش مصنوعی از محتوای همان فصل بپرسد.
                </div>
            )}
            {chapter && lessons.length > 0 && (
                <div className="qk-note soft">درس‌های این فصل: {lessons.map((l, i) => <button type="button" key={i} className="qk-chip" onClick={() => onChange({ topic: l })}>{l}</button>)}</div>
            )}
        </div>
    );
}
