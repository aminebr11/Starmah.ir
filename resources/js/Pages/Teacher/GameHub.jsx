import { Link, router, usePage } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import CreatorHub, { BucketTag, subjectIcon } from '@/Components/CreatorHub';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const DIFF = { easy: 'آسان', medium: 'متوسط', hard: 'سخت' };
const setStatus = (id, status) => router.post(route('teacher.studio.status', id), { status }, { preserveScroll: true });

/** صفحه‌ی اصلیِ استودیوی بازی — دسته‌بندی بر اساسِ وضعیت و درس. */
export default function GameHub() {
    const { items = [], hasClass } = usePage().props;
    const plays = items.reduce((s, g) => s + (g.plays || 0), 0);

    return (
        <DashLayout title="استودیوی بازی" roleLabel="معلم" menu={teacherMenu} active="studio">
            {!hasClass && <div className="panel"><b>برای ساختنِ بازی ابتدا باید یک کلاس داشته باشید.</b></div>}
            <CreatorHub kind="game" items={items} emptyIcon="🎮"
                createHref={route('teacher.studio.create')} createLabel="ساختِ بازیِ جدید"
                createHint="بازی‌های آموزشیِ کلاس را بسازید، زمان‌بندی کنید و نتیجه‌ی هر دانش‌آموز را ببینید."
                extraStats={[[plays, 'بار بازی‌شده']]} accent="#f08a24" accent2="#e0487e"
                renderCard={(g) => (
                    <article key={g.id} className={`ch-card b-${g.bucket}`}>
                        <div className="ch-card-top">
                            <span className="ch-card-ic">{g.icon || '🎮'}</span>
                            <div className="ch-card-h">
                                <h4>{g.title}</h4>
                                <div className="ch-card-sub">{subjectIcon(g.subject)} {g.subject || 'بدونِ درس'}{g.grade ? ` · پایه‌ی ${fa(g.grade)}` : ''}</div>
                            </div>
                            <BucketTag item={g} label={g.bucket === 'pending' && g.status === 'published' ? 'زمان‌بندی‌شده' : undefined} />
                        </div>
                        <div className="ch-card-meta">
                            <span>🧩 {g.template}</span>
                            {g.theme && <span>{g.theme_emoji} {g.theme}</span>}
                            <span>❓ {fa(g.questions)} سؤال</span>
                            <span>▶️ {fa(g.plays)} بار</span>
                            {g.difficulty && <span>⚡ {DIFF[g.difficulty] || g.difficulty}</span>}
                        </div>
                        {(g.jpublish || g.jclose) && (
                            <div className="ch-card-when">
                                {g.jpublish && <span>🗓️ انتشار: {g.jpublish}</span>}
                                {g.jclose && <span>⏹️ پایان: {g.jclose}</span>}
                            </div>
                        )}
                        <div className="ch-card-actions">
                            <Link href={route('teacher.studio.edit', g.id)} className="btn btn-ghost btn-sm">✏️ ویرایش</Link>
                            <a href={route('teacher.studio.preview', g.id)} className="btn btn-ghost btn-sm">👁️ پیش‌نمایش</a>
                            <Link href={route('teacher.studio.report', g.id)} className="btn btn-ghost btn-sm">📊 نتایج</Link>
                            {g.bucket === 'pending' && g.status !== 'published' && <button type="button" onClick={() => setStatus(g.id, 'published')} className="btn btn-sm">🚀 انتشار</button>}
                            {g.bucket !== 'archived' && <button type="button" onClick={() => setStatus(g.id, 'archived')} className="btn btn-ghost btn-sm">🗄️ آرشیو</button>}
                            {g.bucket === 'archived' && <button type="button" onClick={() => setStatus(g.id, 'draft')} className="btn btn-ghost btn-sm">♻️ بازگرداندن</button>}
                            <button type="button" onClick={() => router.post(route('teacher.studio.duplicate', g.id), {}, { preserveScroll: true })} className="btn btn-ghost btn-sm" title="کپی">📋</button>
                            <button type="button" onClick={() => confirm(`بازی «${g.title}» حذف شود؟ این کار قابل بازگشت نیست.`) && router.delete(route('teacher.studio.destroy', g.id), { preserveScroll: true })} className="btn btn-ghost btn-sm ch-del" title="حذف">🗑️</button>
                        </div>
                    </article>
                )} />
        </DashLayout>
    );
}
