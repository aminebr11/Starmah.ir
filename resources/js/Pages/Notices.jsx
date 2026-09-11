import { usePage, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import DashLayout, { adminMenu, schoolMenu, teacherMenu } from '@/Layouts/DashLayout';
import ListSearch, { normalizeFa } from '@/Components/ListSearch';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const FILTERS = [
    { v: 'all', t: '📋 همه' },
    { v: 'personal', t: '✉️ شخصی' },
    { v: 'public', t: '📢 اطلاعیه‌ها' },
];

const MENUS = { super_admin: adminMenu, school_admin: schoolMenu, teacher: teacherMenu };
const LABELS = { super_admin: 'ادمین کل', school_admin: 'مدیر مدرسه', teacher: 'معلم', parent: 'والد' };

/**
 * کارتابلِ اعلان‌ها برای نقش‌های مدیریتی (ادمین کل، مدیرِ مدرسه، والد).
 *
 * پیش از این همه‌ی نقش‌های غیرِ معلم صفحه‌ی دانش‌آموز را می‌گرفتند و
 * منویِ دانش‌آموز برایشان رندر می‌شد؛ نتیجه ۱۱ لینکی بود که برای مدیر و
 * ادمین خطای ۴۰۳ می‌داد. اینجا منویِ خودِ نقش استفاده می‌شود.
 */
export default function Notices() {
    const { notices = [], role = 'school_admin' } = usePage().props;
    const [open, setOpen] = useState(notices[0]?.id ?? null);
    const [filter, setFilter] = useState('all');
    const [q, setQ] = useState('');

    const list = useMemo(() => {
        const nq = normalizeFa(q);
        return notices.filter((n) => {
            if (filter === 'personal' && !n.personal) return false;
            if (filter === 'public' && n.personal) return false;
            if (!nq) return true;
            return normalizeFa(n.title).includes(nq)
                || normalizeFa(n.body).includes(nq)
                || normalizeFa(n.sender).includes(nq);
        });
    }, [notices, filter, q]);

    const counts = useMemo(() => ({
        all: notices.length,
        personal: notices.filter((n) => n.personal).length,
        public: notices.filter((n) => !n.personal).length,
    }), [notices]);

    const clearAll = () => {
        if (confirm('همه‌ی اعلان‌ها پاک شوند؟')) router.post(route('notices.clear'), {}, { preserveScroll: true });
    };
    const dismiss = (id) => router.post(route('notices.dismiss', id), {}, { preserveScroll: true });

    return (
        <DashLayout title="اعلان‌ها و پیام‌ها" roleLabel={LABELS[role] ?? ''} menu={MENUS[role] ?? schoolMenu} active="notices">
            <div className="panel">
                <div className="list-toolbar">
                    <ListSearch value={q} onChange={setQ} placeholder="جست‌وجو در عنوان، متن یا فرستنده…" />
                    <span className="list-count">
                        {q || filter !== 'all' ? `${fa(list.length)} از ${fa(notices.length)}` : `${fa(notices.length)} اعلان`}
                    </span>
                    {notices.length > 0 && (
                        <button onClick={clearAll} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>
                            🗑️ پاک‌کردنِ همه
                        </button>
                    )}
                </div>

                <div className="filter-chips" style={{ marginBottom: 16 }}>
                    {FILTERS.map((f) => (
                        <button key={f.v} type="button" className={`filter-chip ${filter === f.v ? 'on' : ''}`}
                            onClick={() => setFilter(f.v)}>
                            {f.t} <span className="n">{fa(counts[f.v])}</span>
                        </button>
                    ))}
                </div>

                {list.length === 0 ? (
                    <div className="list-empty">
                        <span className="em">📭</span>
                        {notices.length === 0 ? 'اعلانی وجود ندارد.' : 'با این جست‌وجو چیزی پیدا نشد.'}
                    </div>
                ) : (
                    <div className="notice-list">
                        {list.map((n) => (
                            <article key={n.id} className={`notice-item ${open === n.id ? 'open' : ''}`}>
                                <button type="button" className="notice-head" onClick={() => setOpen(open === n.id ? null : n.id)}>
                                    <span className={`notice-kind ${n.personal ? 'personal' : ''}`}>
                                        {n.personal ? '✉️' : '📢'}
                                    </span>
                                    <span className="notice-title">
                                        <b>{n.title}</b>
                                        <small>{n.sender ? `از ${n.sender} · ` : ''}{n.date}</small>
                                    </span>
                                    <span className="notice-caret">{open === n.id ? '▲' : '▼'}</span>
                                </button>

                                {open === n.id && (
                                    <div className="notice-body">
                                        <p>{n.body}</p>
                                        <div className="notice-actions">
                                            {n.link && <a href={n.link} className="btn btn-sm">مشاهده ←</a>}
                                            <button onClick={() => dismiss(n.id)} className="btn btn-ghost btn-sm">حذفِ این اعلان</button>
                                        </div>
                                    </div>
                                )}
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </DashLayout>
    );
}
