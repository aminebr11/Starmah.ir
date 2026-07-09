import { useState } from 'react';

/**
 * ورودی رمز با دکمه‌ی چشم برای نمایش/مخفی‌کردن متن رمز.
 * سازگار با کلاس‌های input سایت. RTL؛ دکمه در سمت چپِ فیلد قرار می‌گیرد.
 */
export default function PasswordInput({ value, onChange, placeholder, autoFocus, name, autoComplete, className = 'input' }) {
    const [show, setShow] = useState(false);
    return (
        <div style={{ position: 'relative' }}>
            <input
                type={show ? 'text' : 'password'}
                className={className}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                autoFocus={autoFocus}
                name={name}
                autoComplete={autoComplete}
                style={{ paddingInlineStart: 44 }}
            />
            <button
                type="button"
                onClick={() => setShow((s) => !s)}
                aria-label={show ? 'مخفی‌کردن رمز' : 'نمایش رمز'}
                title={show ? 'مخفی‌کردن رمز' : 'نمایش رمز'}
                style={{
                    position: 'absolute', insetInlineStart: 8, top: '50%', transform: 'translateY(-50%)',
                    background: 'none', border: 0, cursor: 'pointer', fontSize: 18, lineHeight: 1,
                    padding: 4, color: 'var(--muted)',
                }}
            >
                {show ? '🙈' : '👁️'}
            </button>
        </div>
    );
}
