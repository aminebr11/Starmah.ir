import { useState } from 'react';

/** فهرست کارتابل اعلان‌ها/پیام‌ها — با کلیک روی هر مورد، متن کامل باز می‌شود. */
export default function NoticeList({ notices = [] }) {
    const [open, setOpen] = useState(notices[0]?.id ?? null);

    return (
        <div className="panel">
            <h3>📢 اعلان‌ها و پیام‌های من</h3>
            {notices.length === 0 && <p style={{ color: 'var(--muted)' }}>فعلاً اعلانی نداری.</p>}
            {notices.map((n) => {
                const isOpen = open === n.id;
                return (
                    <div key={n.id} style={{ border: '1px solid var(--line)', borderRadius: 14, marginBottom: 10, overflow: 'hidden' }}>
                        <button onClick={() => setOpen(isOpen ? null : n.id)}
                            style={{ width: '100%', textAlign: 'right', fontFamily: 'inherit', cursor: 'pointer', background: isOpen ? '#fff8e8' : '#fff', border: 0, padding: '14px 16px', display: 'flex', alignItems: 'center', gap: 10 }}>
                            <span style={{ fontSize: 20 }}>{n.personal ? '✉️' : '📢'}</span>
                            <span style={{ flex: 1 }}>
                                <span style={{ fontWeight: 800, display: 'block' }}>
                                    {n.title}
                                    {n.personal && <span className="tag" style={{ marginInlineStart: 8, background: '#efe9ff', color: '#4c2fb0', fontSize: 11 }}>شخصی</span>}
                                </span>
                                <span style={{ color: 'var(--muted-2)', fontSize: 12 }}>از {n.sender || 'مدرسه'} · {n.date}</span>
                            </span>
                            <span style={{ color: 'var(--muted)', fontSize: 18 }}>{isOpen ? '▲' : '▼'}</span>
                        </button>
                        {isOpen && (
                            <div style={{ padding: '0 16px 16px', color: 'var(--ink)', fontSize: 14.5, lineHeight: 1.9, whiteSpace: 'pre-wrap' }}>{n.body}</div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
