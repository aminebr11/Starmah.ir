import { Link, router, usePage } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import CreatorHub, { BucketTag, subjectIcon } from '@/Components/CreatorHub';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const KIND = { standard: 'استاندارد', diagnostic: 'تشخیصی', practice: 'تمرینی', class: 'کلاسی', formal: 'رسمی', remedial: 'جبرانی', game: 'بازی‌محور' };
const STATUS = { draft: 'پیش‌نویس', review: 'آماده‌ی بررسی', scheduled: 'زمان‌بندی‌شده', closed: 'بسته‌شده', archived: 'آرشیو' };
const setStatus = (id, status) => router.post(route('teacher.smart.status', id), { status }, { preserveScroll: true });

/** صفحه‌ی اصلیِ آزمون‌های هوشمند — دسته‌بندی بر اساسِ وضعیت و درس. */
export default function SmartExamHub() {
    const { items = [], bankCount = 0, aiCount = 0 } = usePage().props;
    const attempts = items.reduce((s, e) => s + (e.attempts || 0), 0);

    return (
        <DashLayout title="آزمون‌های هوشمند" roleLabel="معلم" menu={teacherMenu} active="smart">
            <CreatorHub kind="exam" items={items} emptyIcon="🧠"
                createHref={route('teacher.smart.create')} createLabel="ساختِ آزمونِ جدید"
                createHint="آزمون بسازید، برای کلاس یا تیم زمان‌بندی کنید و کارنامه‌ی هوشمندِ هر دانش‌آموز را ببینید."
                extraStats={[[attempts, 'شرکت'], [bankCount, 'سؤال در بانک']]} accent="#6d4ce0" accent2="#3a67c8"
                renderCard={(e) => (
                    <article key={e.id} className={`ch-card b-${e.bucket}`}>
                        <div className="ch-card-top">
                            <span className="ch-card-ic">{e.adaptive ? '🧬' : '📝'}</span>
                            <div className="ch-card-h">
                                <h4>{e.title}</h4>
                                <div className="ch-card-sub">{subjectIcon(e.subject)} {e.subject || 'بدونِ درس'}{e.grade ? ` · پایه‌ی ${fa(e.grade)}` : ''}{e.topic ? ` · ${e.topic}` : ''}</div>
                            </div>
                            <BucketTag item={e} label={e.bucket !== 'published' ? (e.scheduled ? 'زمان‌بندی‌شده' : STATUS[e.status]) : undefined} />
                        </div>
                        <div className="ch-card-meta">
                            <span>🏷️ {KIND[e.kind] || e.kind}</span>
                            <span>❓ {fa(e.questions)} سؤال</span>
                            <span>👥 {fa(e.attempts)} شرکت</span>
                            {e.adaptive && <span>🧬 تطبیقی</span>}
                            {e.version > 1 && <span>نسخه‌ی {fa(e.version)}</span>}
                        </div>
                        {(e.jopens || e.jcloses) && (
                            <div className="ch-card-when">
                                {e.jopens && <span>🗓️ شروع: {e.jopens}</span>}
                                {e.jcloses && <span>⏹️ پایان: {e.jcloses}</span>}
                            </div>
                        )}
                        <div className="ch-card-actions">
                            <Link href={route('teacher.smart.edit', e.id)} className="btn btn-ghost btn-sm">✏️ ویرایش</Link>
                            <a href={route('teacher.smart.preview', e.id)} className="btn btn-ghost btn-sm">👁️ پیش‌نمایش</a>
                            <Link href={route('teacher.smart.report', e.id)} className="btn btn-ghost btn-sm">📊 نتایج</Link>
                            {e.bucket === 'pending' && e.status !== 'published' && <button type="button" onClick={() => setStatus(e.id, 'published')} className="btn btn-sm">🚀 انتشار</button>}
                            {e.bucket !== 'archived' && <button type="button" onClick={() => setStatus(e.id, 'archived')} className="btn btn-ghost btn-sm">🗄️ آرشیو</button>}
                            {e.bucket === 'archived' && <button type="button" onClick={() => setStatus(e.id, 'draft')} className="btn btn-ghost btn-sm">♻️ بازگرداندن</button>}
                            <button type="button" onClick={() => confirm(`آزمون «${e.title}» حذف شود؟`) && router.delete(route('teacher.smart.destroy', e.id), { preserveScroll: true })} className="btn btn-ghost btn-sm ch-del" title="حذف">🗑️</button>
                        </div>
                    </article>
                )} />
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 14 }}>
                <Link href={route('teacher.mybank')} className="btn btn-ghost btn-sm">🗄️ بانکِ سؤالاتِ من ({fa(bankCount)})</Link>
                {aiCount > 0 && <span className="btn btn-ghost btn-sm" style={{ pointerEvents: 'none' }}>🤖 {fa(aiCount)} سؤال با هوش مصنوعی ساخته شده</span>}
            </div>
        </DashLayout>
    );
}
