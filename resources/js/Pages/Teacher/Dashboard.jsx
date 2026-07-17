import { usePage, Link, router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const ALARM_C = [['#3d7bf0', '#2555c0'], ['#a24cf0', '#6f2fb0'], ['#2bb673', '#1a8a52'], ['#e8862e', '#c06712'], ['#e8505b', '#b0333f']];

export default function Dashboard() {
    const { auth, classrooms = [], totals = {}, announcements = [], alarms = [], smartLab = false, unreadNotices = 0 } = usePage().props;
    const name = auth?.user?.name || 'معلم عزیز';
    const dismiss = (id) => router.post(route('teacher.dismiss-alarm'), { id }, { preserveScroll: true });

    const cards = [
        { ic: '🏛️', lbl: 'کلاس‌ها', val: totals.classrooms, c: '#fff3d6' },
        { ic: '🎓', lbl: 'دانش‌آموزان', val: totals.students, c: '#dcebff' },
        { ic: '📝', lbl: 'تکالیف', val: totals.assignments, c: '#e9e4ff' },
        { ic: '⭐', lbl: 'ستاره‌های داده‌شده', val: totals.stars, c: '#d4f5ef' },
    ];

    // ابزارهای معلم — مطابق امکانات وبسایت قبلی + امکانات فعلی
    const tools = [
        { href: route('teacher.activities'), ic: '🏅', t: 'امتیازدهی گروهی', d: 'دادن امتیاز به تیم‌ها و دانش‌آموزان', c: '#fff3d6' },
        { href: route('teacher.studio'), ic: '🎮', t: 'استودیوی بازی', d: 'ساخت بازی آموزشی با AI و بانک سؤال', c: '#e9e4ff' },
        { href: route('teacher.gradebook'), ic: '📔', t: 'دفتر نمره', d: 'نمرات و تسلط دانش‌آموزان', c: '#dcebff' },
        { href: route('teacher.discipline'), ic: '⭐', t: 'انضباط', d: 'ثبت ستاره‌ی تشویقی و تذکر', c: '#d4f5ef' },
        { href: route('teacher.schedule'), ic: '🗓️', t: 'برنامه‌ی کلاسی', d: 'تنظیم برنامه‌ی هفتگی درس‌ها', c: '#ffe0ec' },
        { href: route('teacher.materials'), ic: '📚', t: 'محتوای کلاس', d: 'جزوه، پادکست، گالری و تکلیف', c: '#e6f0ff' },
        { href: route('teacher.reports'), ic: '📈', t: 'گزارش‌ها و تحلیل', d: 'عملکرد کلاس و پیشرفت', c: '#e9e4ff' },
        { href: route('teacher.assignments.create'), ic: '➕', t: 'تکلیف جدید', d: 'ایجاد تمرین و تکلیف برای کلاس', c: '#fff3d6' },
    ];

    return (
        <DashLayout title="پیشخوان معلم" roleLabel="معلم" menu={teacherMenu} active="home"
            actions={<Link href={route('teacher.materials')} className="btn btn-sm">➕ مطالب و محتوی</Link>}>

            {/* خوش‌آمد */}
            <div className="panel" style={{ background: 'linear-gradient(135deg,#16264f,#0a1836)', border: 0, color: '#fff' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap' }}>
                    <div style={{ fontSize: 40 }}>👋</div>
                    <div>
                        <div style={{ fontWeight: 800, fontSize: 20 }}>سلام {name}!</div>
                        <div style={{ color: '#c4d2f0', fontSize: 14 }}>به پیشخوان کلاس خوش آمدی — همه‌ی ابزارهای تدریس این‌جاست.</div>
                    </div>
                </div>
            </div>

            {/* آلارمِ پیام‌های ۲۴ ساعت اخیر — با دیدن/حذف از پیشخوان می‌رود */}
            {alarms.length > 0 && (
                <div style={{ marginTop: 20 }}>
                    <div style={{ fontWeight: 800, fontSize: 15, marginBottom: 10, display: 'flex', alignItems: 'center', gap: 8 }}>
                        🔔 پیام‌های جدید (۲۴ ساعت اخیر)
                        <span className="tag" style={{ background: '#e8505b', color: '#fff' }}>{fa(alarms.length)}</span>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(280px,1fr))', gap: 12 }}>
                        {alarms.map((a, i) => {
                            const [c1, c2] = ALARM_C[i % ALARM_C.length];
                            return (
                                <div key={a.id} style={{ position: 'relative', borderRadius: 16, padding: '14px 40px 14px 16px', color: '#fff', background: `linear-gradient(135deg,${c1},${c2})`, boxShadow: '0 8px 20px -12px rgba(0,0,0,.5)' }}>
                                    <button onClick={() => dismiss(a.id)} title="دیدم، حذف کن" style={{ position: 'absolute', top: 8, insetInlineEnd: 8, width: 26, height: 26, borderRadius: 8, border: 0, background: 'rgba(255,255,255,.25)', color: '#fff', cursor: 'pointer', fontWeight: 900 }}>✓</button>
                                    <div style={{ fontWeight: 800, fontSize: 14.5 }}>{a.personal ? '✉️ ' : '📢 '}{a.title}</div>
                                    <div style={{ fontSize: 12.5, opacity: .92, marginTop: 4, lineHeight: 1.9, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>{a.body}</div>
                                    <div style={{ fontSize: 11, opacity: .8, marginTop: 6 }}>از {a.sender || 'مدرسه'} · {a.date}</div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* آمار */}
            <div className="dash-cards" style={{ marginTop: 20 }}>
                {cards.map((c) => (
                    <div key={c.lbl} className="dcard"><div className="ic" style={{ background: c.c }}>{c.ic}</div>
                        <div className="lbl">{c.lbl}</div><div className="val">{fa(c.val ?? 0)}</div></div>
                ))}
            </div>

            {/* اطلاعیه‌های مدرسه */}
            {announcements.length > 0 && (
                <div className={`panel ${unreadNotices > 0 ? 'notice-blink' : ''}`} style={{ marginTop: 20, borderColor: 'var(--gold)' }}>
                    <h3>📢 اعلان‌ها و پیام‌ها
                        {unreadNotices > 0 && <span className="tag" style={{ background: '#e8505b', color: '#fff', marginInlineStart: 8 }}>{fa(unreadNotices)} نخوانده</span>}
                        <Link href="/notices" className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>همه ←</Link>
                    </h3>
                    {announcements.slice(0, 4).map((a) => (
                        <Link key={a.id} href="/notices" style={{ display: 'block', padding: '10px 0', borderBottom: '1px solid var(--line)', color: 'var(--ink)' }}>
                            <div style={{ fontWeight: 800 }}>
                                {a.personal ? '✉️ ' : '📢 '}{a.title}
                                {a.personal && <span className="tag" style={{ marginInlineStart: 6, background: '#efe9ff', color: '#4c2fb0', fontSize: 11 }}>شخصی</span>}
                                <span style={{ color: 'var(--muted-2)', fontWeight: 400, fontSize: 12, marginInlineStart: 6 }}>· {a.date}</span>
                            </div>
                            <div style={{ color: 'var(--muted)', fontSize: 13.5, whiteSpace: 'pre-wrap', marginTop: 2, overflow: 'hidden', textOverflow: 'ellipsis', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical' }}>{a.body}</div>
                        </Link>
                    ))}
                </div>
            )}

            {/* ابزارها */}
            <div className="panel" style={{ marginTop: 20 }}>
                <h3>🧰 ابزارهای من</h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 14 }} className="tools-grid">
                    {tools.map((t) => (
                        <Link key={t.t} href={t.href} className="tool-card">
                            <div className="tool-ic" style={{ background: t.c }}>{t.ic}</div>
                            <div style={{ fontWeight: 800, color: 'var(--navy-800)' }}>{t.t}</div>
                            <div style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 2 }}>{t.d}</div>
                        </Link>
                    ))}
                </div>
            </div>

            {/* کلاس‌های من */}
            <div id="class" className="panel" style={{ marginTop: 20 }}>
                <h3>🏫 کلاس‌های من</h3>
                {classrooms.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز کلاسی نداری.</p>}
                {classrooms.map((c) => (
                    <Link key={c.id} href={route('teacher.classroom', c.id)} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '14px 0', borderBottom: '1px solid var(--line)', color: 'var(--ink)' }}>
                        <div><div style={{ fontWeight: 800 }}>{c.name}</div><div style={{ color: 'var(--muted)', fontSize: 13 }}>کد: {c.join_code} · {fa(c.students)} دانش‌آموز</div></div>
                        <span style={{ fontSize: 20 }}>‹</span>
                    </Link>
                ))}
            </div>
        </DashLayout>
    );
}
