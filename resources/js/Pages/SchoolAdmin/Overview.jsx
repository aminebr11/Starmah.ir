import { usePage, Link, useForm, router } from '@inertiajs/react';
import { useState, useRef } from 'react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Overview() {
    const { auth, school, stats = {}, classes = [], topStudents = [], flash } = usePage().props;
    const name = auth?.user?.name || 'مدیر';
    const fileRef = useRef(null);
    const [preview, setPreview] = useState(null);

    const uploadLogo = (file) => {
        if (!file) return;
        setPreview(URL.createObjectURL(file));
        router.post(route('school.branding'), { logo: file, _method: 'post' }, { forceFormData: true, preserveScroll: true });
    };

    const seats = stats.seats;
    const seatPct = seats ? Math.min(100, Math.round((stats.students / seats) * 100)) : null;

    const cards = [
        { ic: '👩‍🏫', lbl: 'معلم‌ها', val: fa(stats.teachers ?? 0), c: '#d4f5ef' },
        { ic: '🎓', lbl: 'دانش‌آموزان', val: fa(stats.students ?? 0), c: '#dcebff' },
        { ic: '🏛️', lbl: 'کلاس‌ها', val: fa(stats.classes ?? 0), c: '#fff3d6' },
    ];

    const tools = [
        { href: route('school.teachers'), ic: '👩‍🏫', t: 'معلم‌ها و کلاس‌ها', d: 'ساخت معلم و کلاس جدید', c: '#d4f5ef' },
        { href: route('school.students'), ic: '🎓', t: 'دانش‌آموزان', d: 'فهرست و پیشرفت دانش‌آموزان', c: '#dcebff' },
        { href: route('school.announcements'), ic: '📢', t: 'اطلاعیه‌ها', d: 'ارسال پیام به مدرسه', c: '#ffe0ec' },
        { href: route('school.reports'), ic: '📈', t: 'گزارش‌ها', d: 'تحلیل عملکرد مدرسه', c: '#e9e4ff' },
    ];

    return (
        <DashLayout title={`پیشخوان مدرسه${school ? ` — ${school.name}` : ''}`} roleLabel="مدیر مدرسه" menu={schoolMenu} active="home"
            actions={<Link href={route('school.teachers')} className="btn btn-sm">➕ معلم جدید</Link>}>

            {/* خوش‌آمد + پلن */}
            <div className="panel" style={{ background: 'linear-gradient(135deg,#16264f,#0a1836)', border: 0, color: '#fff' }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 14, flexWrap: 'wrap' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                        <button type="button" onClick={() => fileRef.current?.click()} title="تغییرِ لوگوی مدرسه"
                            style={{ position: 'relative', width: 60, height: 60, borderRadius: 16, border: '2px solid rgba(255,255,255,.25)', background: 'rgba(255,255,255,.08)', cursor: 'pointer', display: 'grid', placeItems: 'center', overflow: 'hidden', padding: 0, flex: 'none' }}>
                            {(preview || school?.logo_url)
                                ? <img src={preview || school.logo_url} alt="لوگوی مدرسه" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                                : <span style={{ fontSize: 34 }}>🏫</span>}
                            <span style={{ position: 'absolute', insetInlineEnd: 2, bottom: 2, background: 'var(--gold)', color: '#221503', width: 20, height: 20, borderRadius: '50%', display: 'grid', placeItems: 'center', fontSize: 11, fontWeight: 900 }}>✎</span>
                        </button>
                        <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" hidden
                            onChange={(e) => uploadLogo(e.target.files?.[0])} />
                        <div>
                            <div style={{ fontWeight: 800, fontSize: 20 }}>سلام {name}!</div>
                            <div style={{ color: '#c4d2f0', fontSize: 14 }}>
                                مدیریت {school?.name ?? 'مدرسه'}{school?.city ? ` · ${school.city}` : ''}
                            </div>
                            <div style={{ color: '#8fa4cf', fontSize: 11.5, marginTop: 3 }}>برای افزودن/تغییرِ لوگوی مدرسه روی نشان کلیک کنید 🖼️</div>
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        {school?.plan && <span className="tag" style={{ background: 'rgba(245,181,63,.2)', color: '#ffd87a', border: '1px solid rgba(245,181,63,.4)' }}>پلن: {school.plan}</span>}
                        <span className="tag" style={{ background: school?.status === 'active' ? 'rgba(43,182,115,.2)' : 'rgba(255,255,255,.15)', color: '#fff' }}>
                            {school?.status === 'active' ? '✅ فعال' : school?.status ?? '—'}
                        </span>
                    </div>
                </div>
            </div>

            {/* آمار */}
            <div className="dash-cards" style={{ gridTemplateColumns: 'repeat(3,1fr)', marginTop: 20 }}>
                {cards.map((c) => (
                    <div key={c.lbl} className="dcard"><div className="ic" style={{ background: c.c }}>{c.ic}</div>
                        <div className="lbl">{c.lbl}</div><div className="val">{c.val}</div></div>
                ))}
            </div>

            {/* ظرفیت ثبت‌نام */}
            {seats > 0 && (
                <div className="panel" style={{ marginTop: 20 }}>
                    <h3>🎟️ ظرفیت ثبت‌نام</h3>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14, marginBottom: 8 }}>
                        <span>{fa(stats.students)} از {fa(seats)} دانش‌آموز</span>
                        <b style={{ color: 'var(--gold-2)' }}>{fa(seatPct)}٪</b>
                    </div>
                    <div style={{ height: 12, background: 'var(--cream)', borderRadius: 10, overflow: 'hidden', border: '1px solid var(--line)' }}>
                        <div style={{ height: '100%', width: `${seatPct}%`, background: 'linear-gradient(90deg,var(--gold),var(--sky))', borderRadius: 10 }} />
                    </div>
                </div>
            )}

            {/* ابزارها */}
            <div className="panel" style={{ marginTop: 20 }}>
                <h3>🧰 مدیریت مدرسه</h3>
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

            <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 20, marginTop: 20, alignItems: 'start' }} className="themes-grid">
                {/* کلاس‌ها */}
                <div className="panel" style={{ margin: 0 }}>
                    <h3>🏛️ کلاس‌های مدرسه</h3>
                    {classes.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز کلاسی ساخته نشده. <Link href={route('school.teachers')} className="link-gold">یک معلم بساز ←</Link></p>}
                    {classes.length > 0 && (
                        <table className="tbl">
                            <thead><tr><th>کلاس</th><th>معلم</th><th>دانش‌آموز</th><th>کد ورود</th></tr></thead>
                            <tbody>{classes.map((c, i) => (
                                <tr key={i}><td style={{ fontWeight: 700 }}>{c.name}</td><td>{c.teacher ?? '—'}</td><td>{fa(c.students)}</td>
                                    <td><span className="tag tag-info">{c.code}</span></td></tr>
                            ))}</tbody>
                        </table>
                    )}
                </div>

                {/* برترین دانش‌آموزان */}
                <div className="panel" style={{ margin: 0 }}>
                    <h3>🏆 ستاره‌های مدرسه</h3>
                    {topStudents.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز امتیازی ثبت نشده.</p>}
                    {topStudents.map((s, i) => (
                        <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '11px 0', borderBottom: '1px solid var(--line)' }}>
                            <div className="tool-ic" style={{ width: 34, height: 34, fontSize: 15, margin: 0, background: i === 0 ? 'linear-gradient(135deg,#ffd23f,#e0a400)' : i === 1 ? 'linear-gradient(135deg,#e6ecf6,#aeb7c7)' : i === 2 ? 'linear-gradient(135deg,#f0c79a,#d79a5b)' : '#eef2f9', color: i < 3 ? '#3a2700' : 'var(--muted)', fontWeight: 800 }}>{fa(i + 1)}</div>
                            <div style={{ fontWeight: 700, flex: 1 }}>{i === 0 && '👑 '}{s.name}</div>
                            <span style={{ fontWeight: 800, color: 'var(--gold-2)' }}>⭐ {fa(s.xp)}</span>
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}
