import { usePage, Link, useForm, router } from '@inertiajs/react';
import { useState, useEffect, useMemo, Fragment } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import PersonCell from '@/Components/PersonCell';
import ListSearch from '@/Components/ListSearch';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** نرمال‌سازیِ فارسی برای جست‌وجو: ی/ک عربی، همزه و نیم‌فاصله. */
const norm = (s) => String(s ?? '')
    .replace(/[يی]/g, 'ی').replace(/[كک]/g, 'ک')
    .replace(/[أإآا]/g, 'ا').replace(/‌/g, ' ')
    .toLowerCase().trim();

export default function Classroom() {
    const { classroom, students = [], themes = [], grades = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    // فرمِ «دانش‌آموز جدید» — با ?new=1 (از دکمه‌ی پیشخوان) خودکار باز می‌شود
    const [creating, setCreating] = useState(false);
    useEffect(() => {
        if (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('new') === '1') setCreating(true);
    }, []);

    const [q, setQ] = useState('');
    const [team, setTeam] = useState('all');      // all | none | <theme_id>
    const [view, setView] = useState('groups');   // groups | table
    const [sort, setSort] = useState('xp');       // xp | name | avg

    // تغییر تیم/گروهِ دانش‌آموز — فقط معلم مجاز است
    const changeTeam = (s, themeId) => {
        if (themeId) router.post(route('teacher.students.team', s.id), { theme_id: themeId }, { preserveScroll: true });
    };

    const edit = useForm({ name: '', phone: '', national_id: '', password: '' });
    const [editId, setEditId] = useState(null);
    const startEdit = (s) => {
        setEditId(s.id);
        edit.setData({ name: s.name || '', phone: s.phone || '', national_id: s.national_id || '', password: '' });
        edit.clearErrors();
    };
    const saveEdit = (e) => { e.preventDefault(); edit.put(route('manage.users.update', editId), { preserveScroll: true, onSuccess: () => setEditId(null) }); };
    const del = (s) => { if (confirm(`دانش‌آموز «${s.name}» حذف شود؟`)) router.delete(route('manage.users.destroy', s.id), { preserveScroll: true }); };

    /* ── شمارشِ هر گروه، برای چیپ‌های فیلتر ── */
    const teamCounts = useMemo(() => {
        const m = { all: students.length, none: 0 };
        themes.forEach((t) => { m[t.id] = 0; });
        students.forEach((s) => {
            if (!s.theme_id) m.none += 1;
            else m[s.theme_id] = (m[s.theme_id] ?? 0) + 1;
        });
        return m;
    }, [students, themes]);

    /* ── اعمالِ جست‌وجو + فیلتر + مرتب‌سازی ── */
    const filtered = useMemo(() => {
        const nq = norm(q);
        let out = students.filter((s) => {
            if (team === 'none' && s.theme_id) return false;
            if (team !== 'all' && team !== 'none' && String(s.theme_id) !== String(team)) return false;
            if (!nq) return true;
            return norm(s.name).includes(nq)
                || norm(s.phone).includes(nq)
                || norm(s.national_id).includes(nq)
                || norm(s.team_name).includes(nq);
        });
        out = [...out].sort((a, b) => (
            sort === 'name' ? String(a.name).localeCompare(String(b.name), 'fa')
                : sort === 'avg' ? (b.avg - a.avg)
                    : (b.xp - a.xp)
        ));
        return out;
    }, [students, q, team, sort]);

    /* ── گروه‌بندی برای نمای «گروه‌ها» ── */
    const grouped = useMemo(() => {
        const map = new Map();
        themes.forEach((t) => map.set(String(t.id), { key: String(t.id), name: t.name, emoji: t.emoji, list: [] }));
        map.set('none', { key: 'none', name: 'بدون گروه', emoji: '❔', list: [] });
        filtered.forEach((s) => {
            const k = s.theme_id ? String(s.theme_id) : 'none';
            (map.get(k) ?? map.get('none')).list.push(s);
        });
        return [...map.values()].filter((g) => g.list.length > 0);
    }, [filtered, themes]);

    const totalXp = useMemo(() => students.reduce((a, s) => a + (s.xp || 0), 0), [students]);
    const avgMastery = students.length
        ? Math.round(students.reduce((a, s) => a + (s.avg || 0), 0) / students.length) : 0;

    if (!classroom) {
        return (
            <DashLayout title="دانش‌آموزان من" roleLabel="معلم" menu={teacherMenu} active="class">
                <div className="panel list-empty">
                    <span className="em">🏫</span>
                    هنوز کلاسی به شما اختصاص داده نشده است.
                    <div style={{ marginTop: 10, fontSize: 13 }}>از مدیرِ مدرسه بخواهید کلاسی برایتان بسازد.</div>
                </div>
            </DashLayout>
        );
    }

    return (
        <DashLayout title={`دانش‌آموزان — ${classroom.name}`} roleLabel="معلم" menu={teacherMenu} active="class"
            actions={<>
                <button type="button" onClick={() => setCreating((v) => !v)} className="btn btn-sm">➕ دانش‌آموز جدید</button>
                <Link href={route('teacher.discipline')} className="btn btn-ghost btn-sm">⭐ ثبت انضباط</Link>
            </>}>

            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8', lineHeight: 1.9 }}><b>{banner}</b></div>}

            {creating && (
                <NewStudent classroom={classroom} themes={themes} grades={grades} count={students.length} onClose={() => setCreating(false)} />
            )}

            {/* ── نوارِ خلاصه ── */}
            <div className="cls-summary">
                <div className="cls-sum-item"><b>{fa(students.length)}</b><span>دانش‌آموز</span></div>
                <div className="cls-sum-item"><b>{fa(grouped.length || 0)}</b><span>گروهِ فعال</span></div>
                <div className="cls-sum-item"><b>{fa(totalXp)}</b><span>مجموعِ امتیاز</span></div>
                <div className="cls-sum-item"><b>{fa(avgMastery)}٪</b><span>میانگینِ تسلط</span></div>
                <div className="cls-sum-code">
                    <span>کدِ ورودِ کلاس</span>
                    <b dir="ltr">{classroom.join_code}</b>
                </div>
            </div>

            <div className="panel">
                {/* ── جست‌وجو و نما ── */}
                <div className="list-toolbar">
                    <ListSearch value={q} onChange={setQ} placeholder="جست‌وجوی نام، موبایل، کد ملی یا گروه…" />
                    <span className="list-count">
                        {q || team !== 'all'
                            ? `${fa(filtered.length)} از ${fa(students.length)}`
                            : `${fa(students.length)} دانش‌آموز`}
                    </span>
                    <div className="filter-chips">
                        <button type="button" className={`filter-chip ${view === 'groups' ? 'on' : ''}`} onClick={() => setView('groups')}>🗂️ گروهی</button>
                        <button type="button" className={`filter-chip ${view === 'table' ? 'on' : ''}`} onClick={() => setView('table')}>📋 جدولی</button>
                    </div>
                </div>

                {/* ── فیلترِ گروه ── */}
                <div className="filter-chips" style={{ marginBottom: 14 }}>
                    <button type="button" className={`filter-chip ${team === 'all' ? 'on' : ''}`} onClick={() => setTeam('all')}>
                        همه <span className="n">{fa(teamCounts.all)}</span>
                    </button>
                    {themes.map((t) => (
                        <button key={t.id} type="button" className={`filter-chip ${String(team) === String(t.id) ? 'on' : ''}`}
                            onClick={() => setTeam(t.id)}>
                            {t.emoji} {t.name} <span className="n">{fa(teamCounts[t.id] ?? 0)}</span>
                        </button>
                    ))}
                    {teamCounts.none > 0 && (
                        <button type="button" className={`filter-chip ${team === 'none' ? 'on' : ''}`} onClick={() => setTeam('none')}>
                            ❔ بدون گروه <span className="n">{fa(teamCounts.none)}</span>
                        </button>
                    )}
                </div>

                {/* ── مرتب‌سازی ── */}
                <div className="filter-chips" style={{ marginBottom: 16 }}>
                    <span style={{ fontSize: 12.5, color: 'var(--muted)', alignSelf: 'center', marginInlineEnd: 4 }}>مرتب‌سازی:</span>
                    {[['xp', '⭐ امتیاز'], ['avg', '📈 تسلط'], ['name', '🔤 نام']].map(([k, label]) => (
                        <button key={k} type="button" className={`filter-chip ${sort === k ? 'on' : ''}`} onClick={() => setSort(k)}>{label}</button>
                    ))}
                </div>

                {filtered.length === 0 ? (
                    <div className="list-empty">
                        <span className="em">🔍</span>
                        {students.length === 0
                            ? 'هنوز دانش‌آموزی به این کلاس نپیوسته است.'
                            : 'هیچ دانش‌آموزی با این جست‌وجو پیدا نشد.'}
                    </div>
                ) : view === 'groups' ? (
                    grouped.map((g) => (
                        <section key={g.key} className="cls-group">
                            <header className="cls-group-h">
                                <span className="em">{g.emoji}</span>
                                <b>{g.name}</b>
                                <span className="n">{fa(g.list.length)} نفر</span>
                                <span className="xp">⭐ {fa(g.list.reduce((a, s) => a + (s.xp || 0), 0))}</span>
                            </header>
                            <div className="cls-cards">
                                {g.list.map((s, i) => (
                                    <StudentCard key={s.id} s={s} rank={i + 1} themes={themes}
                                        onTeam={changeTeam} onEdit={startEdit} onDel={del} />
                                ))}
                            </div>
                        </section>
                    ))
                ) : (
                    <div style={{ overflowX: 'auto' }}>
                        <table className="tbl">
                            <thead>
                                <tr>
                                    <th>#</th><th>دانش‌آموز</th><th>موبایل</th>
                                    <th>گروه</th><th>امتیاز</th><th>تسلط</th>
                                    <th style={{ textAlign: 'left' }}>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.map((s, i) => (
                                    <Fragment key={s.id}>
                                        <tr>
                                            <td style={{ width: 30 }}>{fa(i + 1)}</td>
                                            <td><PersonCell name={s.name} avatar={s.avatar} sub={s.national_id || undefined} size={32} /></td>
                                            <td dir="ltr">{s.phone || '—'}</td>
                                            <td>
                                                <select className="input" style={{ width: 'auto', padding: '6px 9px', fontSize: 12.5 }}
                                                    value={s.theme_id ?? ''} onChange={(e) => changeTeam(s, e.target.value)}>
                                                    <option value="" disabled>— بدون گروه —</option>
                                                    {themes.map((t) => <option key={t.id} value={t.id}>{t.emoji} {t.name}</option>)}
                                                </select>
                                            </td>
                                            <td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.xp)}</td>
                                            <td>{fa(s.avg)}٪</td>
                                            <td style={{ textAlign: 'left', whiteSpace: 'nowrap' }}>
                                                <button onClick={() => startEdit(s)} className="btn btn-ghost btn-sm">✏️</button>
                                                <button onClick={() => del(s)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b', marginInlineStart: 4 }}>🗑️</button>
                                            </td>
                                        </tr>
                                        {editId === s.id && <EditRow cols={7} form={edit} onSave={saveEdit} onCancel={() => setEditId(null)} />}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* فرمِ ویرایش در نمای گروهی */}
                {view === 'groups' && editId && (
                    <div className="panel" style={{ marginTop: 16, background: 'var(--cream)' }}>
                        <h4 style={{ marginTop: 0 }}>✏️ ویرایشِ دانش‌آموز</h4>
                        <EditForm form={edit} onSave={saveEdit} onCancel={() => setEditId(null)} />
                    </div>
                )}
            </div>
        </DashLayout>
    );
}

/* ─────────────────────────── کارتِ دانش‌آموز ─────────────────────────── */
function StudentCard({ s, rank, themes, onTeam, onEdit, onDel }) {
    const medal = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : null;
    return (
        <article className="cls-card">
            <div className="cls-card-top">
                <PersonCell name={s.name} avatar={s.avatar} sub={s.phone || undefined} size={44} />
                {medal && <span className="cls-medal" title={`رتبه ${rank} در گروه`}>{medal}</span>}
            </div>

            <div className="cls-card-stats">
                <div><b style={{ color: 'var(--gold-2)' }}>⭐ {fa(s.xp)}</b><span>امتیاز</span></div>
                <div><b>{fa(s.avg)}٪</b><span>تسلط</span></div>
            </div>

            <div className="cls-card-bar" title={`تسلط ${fa(s.avg)} درصد`}>
                <span style={{ width: `${Math.max(0, Math.min(100, s.avg))}%` }} />
            </div>

            <div className="cls-card-actions">
                <select className="input" value={s.theme_id ?? ''} onChange={(e) => onTeam(s, e.target.value)}
                    aria-label="تغییرِ گروه">
                    <option value="" disabled>— بدون گروه —</option>
                    {themes.map((t) => <option key={t.id} value={t.id}>{t.emoji} {t.name}</option>)}
                </select>
                <button onClick={() => onEdit(s)} className="btn btn-ghost btn-sm" title="ویرایش">✏️</button>
                <button onClick={() => onDel(s)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }} title="حذف">🗑️</button>
            </div>
        </article>
    );
}

/* ─────────────────────────── فرمِ ویرایش ─────────────────────────── */
const FIELDS = { name: 'نام', phone: 'موبایل', national_id: 'کد ملی', password: 'رمز جدید' };

function EditForm({ form, onSave, onCancel }) {
    return (
        <form onSubmit={onSave} style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10, alignItems: 'end' }}>
            {Object.entries(FIELDS).map(([f, label]) => (
                <div className="field" key={f} style={{ margin: 0 }}>
                    <label>{label}</label>
                    <input className="input" value={form.data[f]} onChange={(e) => form.setData(f, e.target.value)}
                        dir={['phone', 'national_id'].includes(f) ? 'ltr' : 'rtl'}
                        placeholder={f === 'password' ? 'بدون تغییر' : ''} />
                    {form.errors[f] && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{form.errors[f]}</div>}
                </div>
            ))}
            <div style={{ display: 'flex', gap: 8 }}>
                <button type="submit" disabled={form.processing} className="btn btn-sm">💾 ذخیره</button>
                <button type="button" onClick={onCancel} className="btn btn-ghost btn-sm">انصراف</button>
            </div>
        </form>
    );
}

function EditRow({ cols, form, onSave, onCancel }) {
    return (
        <tr><td colSpan={cols} style={{ background: 'var(--cream)' }}>
            <div style={{ padding: 8 }}><EditForm form={form} onSave={onSave} onCancel={onCancel} /></div>
        </td></tr>
    );
}

/* ═══════════════════ دانش‌آموزِ جدید ═══════════════════ */
/**
 * همان فیلدهای فرمِ ثبت‌نام، تا پرونده از روزِ اول کامل باشد.
 * فقط نام، نام‌خانوادگی و موبایل اجباری‌اند؛ بقیه را می‌شود بعداً
 * از صفحه‌ی مدیرِ مدرسه کامل کرد.
 */
function NewStudent({ classroom, themes = [], grades = [], count = 0, onClose }) {
    const [more, setMore] = useState(false);
    const [photo, setPhoto] = useState(null);
    const form = useForm({
        first_name: '', last_name: '', phone: '', password: '',
        gender: '', national_id: '', birth_date: '', grade: classroom?.grade || '', theme_id: '',
        father_name: '', mother_name: '', parent_relation: '', parent_phone: '', address: '', parent_pin: '',
        avatar: null,
    });

    const full = classroom?.capacity != null && count >= classroom.capacity;

    const pickPhoto = (e) => {
        const f = e.target.files?.[0];
        if (!f) { setPhoto(null); form.setData('avatar', null); return; }
        form.setData('avatar', f);
        const r = new FileReader();
        r.onload = () => setPhoto(r.result);
        r.readAsDataURL(f);
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('teacher.students.store'), {
            preserveScroll: true, forceFormData: true,
            onSuccess: () => { form.reset(); setPhoto(null); onClose(); },
        });
    };

    if (classroom?.expired) {
        return (
            <div className="panel" style={{ borderColor: '#e8505b', background: '#fdecee' }}>
                <b>اشتراکِ مدرسه منقضی شده است</b>
                <div style={{ fontSize: 13, marginTop: 4 }}>فعلاً امکانِ افزودنِ دانش‌آموز نیست. با مدیرِ مدرسه تماس بگیرید.</div>
            </div>
        );
    }

    return (
        <div className="panel" style={{ borderColor: 'var(--gold)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                <h3 style={{ margin: 0 }}>➕ دانش‌آموزِ جدید در «{classroom?.name}»</h3>
                <button type="button" onClick={onClose} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>بستن</button>
            </div>
            <p style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 4, lineHeight: 1.9 }}>
                رمزِ ورود را خالی بگذارید تا خودکار ساخته شود؛ پس از ثبت، موبایل و رمز نمایش داده می‌شود تا به خانواده بدهید.
                {classroom?.capacity != null && <> ظرفیتِ کلاس: {fa(count)} از {fa(classroom.capacity)}.</>}
            </p>

            {full && (
                <div style={{ background: '#fdecee', color: '#b0333f', borderRadius: 12, padding: '9px 12px', fontSize: 13, marginBottom: 10 }}>
                    ظرفیتِ این کلاس تکمیل است. برای افزودنِ دانش‌آموزِ بیشتر، از مدیرِ مدرسه ارتقای طرح بخواهید.
                </div>
            )}

            <form onSubmit={submit}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(170px,1fr))', gap: 12 }}>
                    <F label="نام" err={form.errors.first_name}>
                        <input className="input" value={form.data.first_name} onChange={(e) => form.setData('first_name', e.target.value)} />
                    </F>
                    <F label="نام خانوادگی" err={form.errors.last_name}>
                        <input className="input" value={form.data.last_name} onChange={(e) => form.setData('last_name', e.target.value)} />
                    </F>
                    <F label="موبایلِ دانش‌آموز" err={form.errors.phone}>
                        <input className="input" dir="ltr" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} placeholder="09xxxxxxxxx" />
                    </F>
                    <F label="رمزِ ورود (خالی = خودکار)" err={form.errors.password}>
                        <input className="input" dir="ltr" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                    </F>
                </div>

                <button type="button" onClick={() => setMore(!more)} className="btn btn-ghost btn-sm" style={{ marginTop: 6 }}>
                    {more ? '▴ بستنِ اطلاعاتِ تکمیلی' : '▾ اطلاعاتِ تکمیلی (عکس، کدِ ملی، سرپرست…)'}
                </button>

                {more && (
                    <div style={{ marginTop: 10, borderTop: '1px solid var(--line)', paddingTop: 12 }}>
                        <div style={{ display: 'flex', gap: 14, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                            <div style={{ textAlign: 'center' }}>
                                <div style={{ width: 92, height: 92, borderRadius: 18, overflow: 'hidden', border: '1px solid var(--line)', background: '#f4f7fd', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 34 }}>
                                    {photo ? <img src={photo} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : '👤'}
                                </div>
                                <label className="btn btn-ghost btn-sm" style={{ marginTop: 6, display: 'inline-block', cursor: 'pointer' }}>
                                    📷 عکس
                                    <input type="file" accept="image/*" onChange={pickPhoto} style={{ display: 'none' }} />
                                </label>
                                {form.errors.avatar && <div style={{ color: '#e8505b', fontSize: 11.5 }}>{form.errors.avatar}</div>}
                            </div>

                            <div style={{ flex: 1, minWidth: 260, display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 12 }}>
                                <F label="جنسیت">
                                    <select className="input" value={form.data.gender} onChange={(e) => form.setData('gender', e.target.value)}>
                                        <option value="">— انتخاب —</option><option value="پسر">پسر</option><option value="دختر">دختر</option>
                                    </select>
                                </F>
                                <F label="کدِ ملی" err={form.errors.national_id}>
                                    <input className="input" dir="ltr" maxLength={10} value={form.data.national_id} onChange={(e) => form.setData('national_id', e.target.value)} />
                                </F>
                                <F label="تاریخِ تولد (میلادی)" err={form.errors.birth_date}>
                                    <input className="input" type="date" dir="ltr" value={form.data.birth_date} onChange={(e) => form.setData('birth_date', e.target.value)} />
                                </F>
                                <F label="پایه">
                                    <select className="input" value={form.data.grade} onChange={(e) => form.setData('grade', e.target.value)}>
                                        <option value="">— انتخاب —</option>
                                        {grades.map((g) => <option key={g} value={g}>{g}</option>)}
                                    </select>
                                </F>
                                <F label="تیم/گروه">
                                    <select className="input" value={form.data.theme_id} onChange={(e) => form.setData('theme_id', e.target.value)}>
                                        <option value="">— بعداً —</option>
                                        {themes.map((t) => <option key={t.id} value={t.id}>{t.emoji} {t.name}</option>)}
                                    </select>
                                </F>
                            </div>
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(170px,1fr))', gap: 12, marginTop: 10 }}>
                            <F label="نامِ پدر"><input className="input" value={form.data.father_name} onChange={(e) => form.setData('father_name', e.target.value)} /></F>
                            <F label="نامِ مادر"><input className="input" value={form.data.mother_name} onChange={(e) => form.setData('mother_name', e.target.value)} /></F>
                            <F label="نسبتِ سرپرست">
                                <select className="input" value={form.data.parent_relation} onChange={(e) => form.setData('parent_relation', e.target.value)}>
                                    <option value="">— انتخاب —</option><option value="پدر">پدر</option><option value="مادر">مادر</option><option value="ولی">ولی</option>
                                </select>
                            </F>
                            <F label="موبایلِ سرپرست" err={form.errors.parent_phone}>
                                <input className="input" dir="ltr" value={form.data.parent_phone} onChange={(e) => form.setData('parent_phone', e.target.value)} />
                            </F>
                            <F label="رمزِ بخشِ والدین (خالی = خودکار)" err={form.errors.parent_pin}>
                                <input className="input" dir="ltr" value={form.data.parent_pin} onChange={(e) => form.setData('parent_pin', e.target.value)} />
                            </F>
                        </div>
                        <F label="نشانی">
                            <input className="input" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                        </F>
                    </div>
                )}

                <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                    <button type="submit" disabled={form.processing || full} className="btn">
                        {form.processing ? 'در حال ثبت…' : '➕ ثبتِ دانش‌آموز'}
                    </button>
                    <button type="button" onClick={onClose} className="btn btn-ghost">انصراف</button>
                </div>
            </form>
        </div>
    );
}

const F = ({ label, err, children }) => (
    <div className="field" style={{ margin: 0 }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>
);
