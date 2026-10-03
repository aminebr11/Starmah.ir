import { useState, useEffect } from 'react';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * گالری بر اساسِ تاریخِ آپلود: جدیدترین روز بالا، و یک منوی کشویی
 * برای جدا کردنِ عکس‌های یک روزِ خاص.
 */
export default function GalleryByDate({ list, render, dark = false }) {
    const [day, setDay] = useState('');
    const groups = [];
    const idx = {};
    [...list].sort((a, b) => (b.day || '').localeCompare(a.day || '') || b.id - a.id).forEach((i) => {
        const k = i.day || 'nd';
        if (!(k in idx)) { idx[k] = groups.length; groups.push({ day: k, label: i.date, items: [] }); }
        groups[idx[k]].items.push(i);
    });
    useEffect(() => { if (day && !groups.some((g) => g.day === day)) setDay(''); }, [list.length]); // eslint-disable-line
    const shown = day ? groups.filter((g) => g.day === day) : groups;
    if (!list.length) return null;
    return (
        <div className={`gl-wrap ${dark ? 'dark' : ''}`}>
            <div className="gl-bar">
                <label>📅 تاریخِ آپلود</label>
                <select className="input gl-select" value={day} onChange={(e) => setDay(e.target.value)}>
                    <option value="">همه‌ی تاریخ‌ها ({fa(list.length)} عکس)</option>
                    {groups.map((g) => <option key={g.day} value={g.day}>{fa(g.label)} — {fa(g.items.length)} عکس</option>)}
                </select>
            </div>
            {shown.map((g) => (
                <section key={g.day} className="gl-day">
                    <h4><span>📅 {fa(g.label)}</span><small>{fa(g.items.length)} عکس</small></h4>
                    <div className="gl-grid">{g.items.map(render)}</div>
                </section>
            ))}
        </div>
    );
}

