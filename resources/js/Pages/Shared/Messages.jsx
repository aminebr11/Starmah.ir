import { usePage, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import DashLayout, { teacherMenu, schoolMenu } from '@/Layouts/DashLayout';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** صندوقِ پیامِ دوسویه — ارتباط با معلم/مدیر/والدین. سوابق + ویرایش + تاریخ شمسی. */
export default function Messages() {
    const { auth } = usePage().props;
    const roles = auth?.roles ?? [];
    const inner = <Mailbox />;
    if (roles.includes('teacher')) return <DashLayout title="ارتباط با والدین/مدیر" roleLabel="معلم" menu={teacherMenu} active="messages">{inner}</DashLayout>;
    if (roles.includes('school_admin')) return <DashLayout title="ارتباط با والدین/معلم" roleLabel="مدیر مدرسه" menu={schoolMenu} active="messages">{inner}</DashLayout>;
    return <ThemedDash title="ارتباط با معلم" active="messages">{inner}</ThemedDash>;
}

function Mailbox() {
    const { contacts = [], thread = [], activeId, activeName, auth } = usePage().props;
    const dark = !(auth?.roles ?? []).some((r) => r === 'teacher' || r === 'school_admin');

    const open = (id) => router.get(route('messages.index'), { with: id }, { preserveState: false, preserveScroll: true });

    const form = useForm({ recipient_id: activeId || '', body: '' });
    const send = (e) => {
        e.preventDefault();
        if (!form.data.body.trim() || !activeId) return;
        form.transform((d) => ({ ...d, recipient_id: activeId }));
        form.post(route('messages.store'), { preserveScroll: true, onSuccess: () => form.reset('body') });
    };

    const [editId, setEditId] = useState(null);
    const [editText, setEditText] = useState('');
    const startEdit = (m) => { setEditId(m.id); setEditText(m.body); };
    const saveEdit = (id) => router.put(route('messages.update', id), { body: editText }, { preserveScroll: true, onSuccess: () => setEditId(null) });
    const delMsg = (id) => { if (confirm('این پیام حذف شود؟')) router.delete(route('messages.destroy', id), { preserveScroll: true }); };

    const C = dark ? {
        card: 'rgba(255,255,255,.06)', border: 'rgba(255,255,255,.12)', text: '#fff', sub: 'rgba(255,255,255,.65)',
        inputBg: 'rgba(255,255,255,.1)', mine: 'linear-gradient(135deg,var(--p1,#3d7bf0),var(--p2,#2555c0))',
        theirs: 'rgba(255,255,255,.08)',
    } : {
        card: '#fff', border: 'var(--line)', text: 'var(--ink)', sub: 'var(--muted)',
        inputBg: '#fff', mine: 'linear-gradient(135deg,#3d7bf0,#2555c0)', theirs: '#f1f5f9',
    };

    return (
        <div style={{ display: 'grid', gridTemplateColumns: 'minmax(200px,270px) 1fr', gap: 14, alignItems: 'start' }} className="themes-grid">
            {/* فهرست مخاطبان */}
            <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 16, padding: 12, color: C.text }}>
                <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 10 }}>💬 مخاطبان</div>
                {contacts.length === 0 && <div style={{ fontSize: 12.5, color: C.sub }}>مخاطبی برای گفت‌وگو نیست.</div>}
                <div style={{ display: 'grid', gap: 6 }}>
                    {contacts.map((c) => (
                        <button key={c.id} onClick={() => open(c.id)}
                            style={{ display: 'flex', alignItems: 'center', gap: 8, textAlign: 'start', cursor: 'pointer', fontFamily: 'inherit',
                                border: activeId === c.id ? '2px solid var(--acc,#f5b53f)' : `1px solid ${C.border}`,
                                background: activeId === c.id ? 'rgba(245,181,63,.12)' : 'transparent', color: C.text, borderRadius: 12, padding: '9px 11px' }}>
                            <span style={{ width: 34, height: 34, borderRadius: '50%', display: 'grid', placeItems: 'center', flex: 'none', background: 'linear-gradient(135deg,#3d7bf0,#2555c0)', color: '#fff', fontWeight: 800 }}>{(c.name || '?').slice(0, 1)}</span>
                            <span style={{ flex: 1, minWidth: 0 }}>
                                <span style={{ fontWeight: 800, fontSize: 13.5, display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.name}</span>
                                <span style={{ fontSize: 11, color: C.sub }}>{c.role}</span>
                            </span>
                            {c.unread > 0 && <span style={{ background: '#e8505b', color: '#fff', borderRadius: 20, minWidth: 20, height: 20, fontSize: 11, fontWeight: 800, display: 'grid', placeItems: 'center', padding: '0 5px' }}>{fa(c.unread)}</span>}
                        </button>
                    ))}
                </div>
            </div>

            {/* گفت‌وگو */}
            <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 16, padding: 14, color: C.text, display: 'flex', flexDirection: 'column', minHeight: 420 }}>
                {!activeId ? (
                    <div style={{ margin: 'auto', textAlign: 'center', color: C.sub, padding: 30 }}>
                        <div style={{ fontSize: 40 }}>💬</div>
                        <p>یک مخاطب را از سمت راست انتخاب کن تا گفت‌وگو را ببینی یا پیام بفرستی.</p>
                    </div>
                ) : (
                    <>
                        <div style={{ fontWeight: 900, fontSize: 15, paddingBottom: 10, borderBottom: `1px solid ${C.border}`, marginBottom: 10 }}>{activeName}</div>
                        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 8, overflowY: 'auto', maxHeight: '52vh', paddingInlineEnd: 4 }}>
                            {thread.length === 0 && <div style={{ margin: 'auto', color: C.sub, fontSize: 13 }}>هنوز پیامی نیست. اولین پیام را بفرست 👋</div>}
                            {thread.map((m) => (
                                <div key={m.id} style={{ maxWidth: '82%', alignSelf: m.mine ? 'flex-end' : 'flex-start' }}>
                                    <div style={{ padding: '9px 13px', borderRadius: 16, fontSize: 13.5, lineHeight: 1.9,
                                        background: m.mine ? C.mine : C.theirs, color: m.mine ? '#fff' : C.text, border: m.mine ? 0 : `1px solid ${C.border}` }}>
                                        {editId === m.id ? (
                                            <div style={{ display: 'flex', gap: 6, flexDirection: 'column' }}>
                                                <textarea value={editText} onChange={(e) => setEditText(e.target.value)} rows={2}
                                                    style={{ width: 220, maxWidth: '60vw', borderRadius: 8, border: 0, padding: 6, fontFamily: 'inherit', color: '#1b2742' }} />
                                                <div style={{ display: 'flex', gap: 6 }}>
                                                    <button onClick={() => saveEdit(m.id)} style={btn}>ذخیره</button>
                                                    <button onClick={() => setEditId(null)} style={{ ...btn, background: 'rgba(0,0,0,.25)' }}>لغو</button>
                                                </div>
                                            </div>
                                        ) : m.body}
                                    </div>
                                    <div style={{ fontSize: 10, color: C.sub, marginTop: 3, textAlign: m.mine ? 'left' : 'right', display: 'flex', gap: 8, justifyContent: m.mine ? 'flex-end' : 'flex-start' }}>
                                        <span>{m.date}{m.edited ? ' · ویرایش‌شده' : ''}</span>
                                        {m.mine && editId !== m.id && (
                                            <>
                                                <button onClick={() => startEdit(m)} style={linkBtn(C.sub)}>ویرایش</button>
                                                <button onClick={() => delMsg(m.id)} style={linkBtn('#e8505b')}>حذف</button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <form onSubmit={send} style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                            <input value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} placeholder="پیامت را بنویس…"
                                style={{ flex: 1, borderRadius: 12, border: `1px solid ${C.border}`, background: C.inputBg, color: C.text, padding: '11px 14px', fontFamily: 'inherit' }} />
                            <button type="submit" disabled={form.processing || !form.data.body.trim()} style={{ ...btn, padding: '0 22px', fontSize: 14 }}>ارسال</button>
                        </form>
                    </>
                )}
            </div>
        </div>
    );
}

const btn = { background: 'linear-gradient(135deg,#3d7bf0,#2555c0)', color: '#fff', border: 0, borderRadius: 10, padding: '8px 14px', fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit' };
const linkBtn = (c) => ({ background: 'none', border: 0, color: c, cursor: 'pointer', fontFamily: 'inherit', fontSize: 10, fontWeight: 700, padding: 0 });
