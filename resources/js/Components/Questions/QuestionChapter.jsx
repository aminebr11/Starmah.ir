import { useEffect, useState } from 'react';
import axios from 'axios';

/**
 * فصلِ تک‌تکِ سؤال‌ها.
 *
 * هسته‌ی یادگیری پیشرفتِ دانش‌آموز را «فصل‌به‌فصل» رصد می‌کند؛ اگر آزمونی از
 * چند فصل سؤال دارد، هر سؤال باید در فصلِ خودش ثبت شود. پیش‌فرض همان فصلِ آزمون
 * است و معلم فقط برای سؤال‌هایی که از فصلِ دیگرند تغییرش می‌دهد.
 */

const cache = {};

/** فهرستِ فصل‌های یک درس (یک‌بار برای کلِ فرم، نه برای هر سؤال). */
export function useChapters(grade, subject) {
    const key = `${grade || ''}|${subject || ''}`;
    const [list, setList] = useState(cache[key] || []);
    useEffect(() => {
        if (!grade || !subject) { setList([]); return undefined; }
        if (cache[key]) { setList(cache[key]); return undefined; }
        let live = true;
        axios.get(route('curriculum.chapters'), { params: { grade, subject } })
            .then(({ data }) => { cache[key] = data.chapters || []; if (live) setList(cache[key]); })
            .catch(() => live && setList([]));
        return () => { live = false; };
    }, [key]); // eslint-disable-line react-hooks/exhaustive-deps
    return list;
}

/** فصلِ مؤثرِ یک سؤال: فصلِ خودش، وگرنه فصلِ آزمون/بازی. */
export const effectiveChapter = (q, parentId) => (q?.chapter_id ? Number(q.chapter_id) : (parentId ? Number(parentId) : null));

export default function QuestionChapter({ value, parentId, chapters = [], onChange, compact = false }) {
    const parent = chapters.find((c) => String(c.id) === String(parentId));
    const own = value && String(value) !== String(parentId) ? String(value) : '';
    const missing = !own && !parentId;
    if (!chapters.length && !missing) return null;

    return (
        <label className={`qc-wrap ${missing ? 'qc-missing' : ''}`} title="این سؤال مالِ کدام فصل است؟ پیشرفتِ دانش‌آموز در همان فصل ثبت می‌شود.">
            <span className="qc-ic">📘</span>
            <select className="qc-sel" value={own} onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)} disabled={!chapters.length}>
                <option value="">{parent ? `فصلِ ${compact ? '' : 'آزمون'}: ${parent.label}` : (chapters.length ? '⚠️ فصل انتخاب نشده' : '⚠️ اول درس و فصلِ آزمون را انتخاب کنید')}</option>
                {chapters.filter((c) => String(c.id) !== String(parentId)).map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
            </select>
        </label>
    );
}
