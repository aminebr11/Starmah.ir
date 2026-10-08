import { useState } from 'react';
import { usePage, useForm, router, Link } from '@inertiajs/react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';
import PersonCell from '@/Components/PersonCell';
import { useSort, SortTh, SortBar, firstName, lastName } from '@/lib/useSort';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** همه‌ی کاربرانِ سامانه — جست‌وجو و ویرایشِ نام و شماره‌ی موبایل (حتی خودِ ادمین). */
export default function Users() {
    const { users = { data: [] }, filters = {}, roles = {}, flash } = usePage().props;
    const [q, setQ] = useState(filters.q || '');
    const [edit, setEdit] = useState(null);
    const banner = typeof flash?.flash === 'string' ? flash.flash : flash?.flash?.message;
    const us = useSort(users.data, { name: (r) => firstName(r.name), family: (r) => lastName(r.name), role: (r) => (r.roles || []).join('، '), school: 'school', phone: 'phone' }, { id: 'admin-users' });
    const go = (patch) => router.get(route('admin.users'), { q, role: filters.role || '', ...patch }, { preserveState: true, replace: true });

    return (
        <DashLayout title="کاربران و شماره‌ها" roleLabel="ادمین کل" menu={adminMenu} active="users">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}
            <div className="panel">
                <form onSubmit={(e) => { e.preventDefault(); go({ page: 1 }); }} style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    <input className="input" style={{ flex: '1 1 220px' }} value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 نام، موبایل یا کدِ ملی…" />
                    <select className="input" style={{ width: 'auto' }} value={filters.role || ''} onChange={(e) => go({ role: e.target.value, page: 1 })}>
                        <option value="">همه‌ی نقش‌ها</option>
                        {Object.entries(roles).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                    <button className="btn btn-sm" type="submit">جست‌وجو</button>
                </form>
                <div style={{ color: 'var(--muted)', fontSize: 13, marginTop: 8 }}>{fa(users.total ?? users.data.length)} کاربر</div>
            </div>

            <div className="panel">
                {users.data.length > 1 && <SortBar s={us} options={[['name', 'نام'], ['family', 'نام خانوادگی'], ['role', 'نقش'], ['school', 'مدرسه']]} />}
                <table className="tbl">
                    <thead><tr><SortTh s={us} k="family">کاربر</SortTh><SortTh s={us} k="role">نقش</SortTh><SortTh s={us} k="school">مدرسه</SortTh><SortTh s={us} k="phone">موبایل</SortTh><th style={{ textAlign: 'left' }}>ویرایش</th></tr></thead>
                    <tbody>
                        {us.sorted.map((u) => (
                            edit === u.id
                                ? <EditRow key={u.id} u={u} onDone={() => setEdit(null)} />
                                : (
                                    <tr key={u.id} style={u.me ? { background: '#fff8e8' } : undefined}>
                                        <td><PersonCell name={u.name + (u.me ? ' (شما)' : '')} avatar={u.avatar} size={30} /></td>
                                        <td>{u.roles.join('، ') || '—'}</td>
                                        <td>{u.school || '—'}</td>
                                        <td dir="ltr">{u.phone || '—'}</td>
                                        <td style={{ textAlign: 'left' }}><button type="button" className="btn btn-ghost btn-sm" onClick={() => setEdit(u.id)}>✏️ ویرایش</button></td>
                                    </tr>
                                )
                        ))}
                        {users.data.length === 0 && <tr><td colSpan={5} style={{ color: 'var(--muted)' }}>کاربری پیدا نشد.</td></tr>}
                    </tbody>
                </table>
                {users.last_page > 1 && (
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 12 }}>
                        {users.links.filter((l) => l.url).map((l, i) => (
                            <Link key={i} href={l.url} preserveState className={`btn btn-sm ${l.active ? '' : 'btn-ghost'}`}
                                dangerouslySetInnerHTML={{ __html: l.label }} />
                        ))}
                    </div>
                )}
            </div>
        </DashLayout>
    );
}

function EditRow({ u, onDone }) {
    const f = useForm({ name: u.name || '', phone: u.phone || '' });
    const save = (e) => { e.preventDefault(); f.put(route('admin.users.update', u.id), { preserveScroll: true, onSuccess: onDone }); };
    return (
        <tr>
            <td colSpan={5} style={{ background: 'var(--cream)' }}>
                <form onSubmit={save} style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 10, alignItems: 'end', padding: 6 }}>
                    <div className="field" style={{ margin: 0 }}><label>نام</label><input className="input" value={f.data.name} onChange={(e) => f.setData('name', e.target.value)} />{f.errors.name && <small style={{ color: '#e8505b' }}>{f.errors.name}</small>}</div>
                    <div className="field" style={{ margin: 0 }}><label>شماره موبایل</label><input className="input" dir="ltr" inputMode="tel" value={f.data.phone} onChange={(e) => f.setData('phone', e.target.value)} />{f.errors.phone && <small style={{ color: '#e8505b' }}>{f.errors.phone}</small>}</div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button type="submit" className="btn btn-sm" disabled={f.processing}>💾 ذخیره</button>
                        <button type="button" className="btn btn-ghost btn-sm" onClick={onDone}>انصراف</button>
                    </div>
                </form>
            </td>
        </tr>
    );
}
