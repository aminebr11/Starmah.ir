import { usePage, useForm } from '@inertiajs/react';
import Themed, { studentNav } from '@/Layouts/Themed';
import { ui } from '@/theme';

/** پل والدین — گفت‌وگوی دوسویه‌ی خانه و مدرسه. */
export default function Messages() {
    const { messages, contacts, auth } = usePage().props;
    const isStudent = (auth?.roles ?? []).includes('student');

    const { data, setData, post, processing, reset } = useForm({ recipient_id: contacts?.[0]?.id ?? '', body: '' });
    const send = (e) => { e.preventDefault(); if (!data.body.trim()) return; post(route('messages.store'), { onSuccess: () => reset('body'), preserveScroll: true }); };

    return (
        <Themed title="پیام‌ها" nav={isStudent ? studentNav('messages') : null} active="messages">
            <div style={{ textAlign: 'center' }}>
                <div style={ui.h}>پل والدین 💌</div>
                <p style={ui.muted}>ارتباط دوسویه‌ی خانه و مدرسه</p>
            </div>

            <div style={{ ...ui.card, marginTop: 14, display: 'flex', flexDirection: 'column', gap: 8, maxHeight: '52vh', overflowY: 'auto' }}>
                {messages.length ? messages.map((m) => (
                    <div key={m.id} style={bubble(m.mine)}>
                        {!m.mine && <div style={{ fontSize: 10, opacity: .7, marginBottom: 2 }}>{m.from}</div>}
                        {m.body}
                        <div style={{ fontSize: 9, opacity: .6, marginTop: 3 }}>{m.time}</div>
                    </div>
                )) : <div style={{ ...ui.muted, textAlign: 'center' }}>هنوز پیامی نیست. اولین پیام را بفرست 👋</div>}
            </div>

            <form onSubmit={send} style={{ marginTop: 12 }}>
                {contacts?.length > 1 && (
                    <select value={data.recipient_id} onChange={(e) => setData('recipient_id', e.target.value)} style={{ ...ui.input, marginBottom: 8 }}>
                        {contacts.map((c) => <option key={c.id} value={c.id} style={{ color: '#000' }}>{c.name}</option>)}
                    </select>
                )}
                <div style={{ display: 'flex', gap: 8 }}>
                    <input value={data.body} onChange={(e) => setData('body', e.target.value)} placeholder="پیامت را بنویس..." style={{ ...ui.input, flex: 1 }} />
                    <button type="submit" disabled={processing} style={{ ...ui.btn, width: 'auto', padding: '0 22px' }}>ارسال</button>
                </div>
            </form>
        </Themed>
    );
}

const bubble = (mine) => ({
    maxWidth: '80%', padding: '10px 14px', borderRadius: 16, fontSize: 13, lineHeight: 1.9,
    alignSelf: mine ? 'flex-start' : 'flex-end',
    background: mine ? 'linear-gradient(135deg,var(--p1),var(--p2))' : 'rgba(255,255,255,.07)',
    color: mine ? '#0e1c3d' : '#fff',
    border: mine ? 0 : '1px solid rgba(255,255,255,.12)',
});
