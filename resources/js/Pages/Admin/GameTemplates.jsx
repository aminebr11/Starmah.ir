import { usePage, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function GameTemplates() {
    const { templates = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [editId, setEditId] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const form = useForm({ name: '', description: '', icon: '' });
    const startEdit = (t) => { setEditId(t.id); form.setData({ name: t.name, description: t.description || '', icon: t.icon || '' }); };
    const save = (id) => form.put(route('admin.game-templates.update', id), { preserveScroll: true, onSuccess: () => setEditId(null) });

    return (
        <DashLayout title="قالب‌های بازی" roleLabel="ادمین کل" menu={adminMenu} active="game-templates">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}
            <div className="panel">
                <h3>🎲 قالب‌ها و مکانیک‌های بازی</h3>
                <p style={{ color: 'var(--muted)', marginTop: 4 }}>قالب = منطق و مکانیکِ بازی (جدا از تم/ظاهر). قالب‌های فعال برای معلمان در استودیوی ساخت بازی نمایش داده می‌شوند.</p>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(260px,1fr))', gap: 12, marginTop: 12 }}>
                    {templates.map((t) => (
                        <div key={t.id} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 14, borderTop: `4px solid ${t.is_active ? '#2bb673' : '#c4ccda'}` }}>
                            {editId === t.id ? (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    <input className="input" value={form.data.icon} onChange={(e) => form.setData('icon', e.target.value)} placeholder="ایموجی" />
                                    <input className="input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="نام" />
                                    <textarea className="input" rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="توضیح" />
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button onClick={() => save(t.id)} className="btn btn-sm">💾 ذخیره</button>
                                        <button onClick={() => setEditId(null)} className="btn btn-ghost btn-sm">انصراف</button>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                        <span style={{ fontSize: 26 }}>{t.icon}</span>
                                        <b style={{ flex: 1 }}>{t.name}</b>
                                        <span className={`tag ${t.is_active ? 'tag-ok' : 'tag-info'}`} style={{ fontSize: 11 }}>{t.is_active ? 'فعال' : 'غیرفعال'}</span>
                                    </div>
                                    <div style={{ fontSize: 12.5, color: 'var(--muted)', marginTop: 6, minHeight: 34 }}>{t.description}</div>
                                    <div style={{ fontSize: 11.5, color: 'var(--muted-2)', marginTop: 4 }}>{fa(t.games)} بازی از این قالب</div>
                                    <div style={{ display: 'flex', gap: 6, marginTop: 10 }}>
                                        <button onClick={() => startEdit(t)} className="btn btn-ghost btn-sm">✏️ ویرایش</button>
                                        <button onClick={() => router.post(route('admin.game-templates.toggle', t.id), {}, { preserveScroll: true })} className="btn btn-ghost btn-sm">{t.is_active ? '⏸️ غیرفعال' : '▶️ فعال'}</button>
                                    </div>
                                </>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}
