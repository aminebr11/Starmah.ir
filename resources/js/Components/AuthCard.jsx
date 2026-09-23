import { Head, Link } from '@inertiajs/react';

/**
 * قابِ مشترکِ صفحه‌های کوچکِ احرازِ هویت (رمزِ جدید، تأییدِ رمز، تأییدِ ایمیل…).
 * پیش از این، چند صفحه هنوز قالبِ پیش‌فرضِ انگلیسیِ لاراول را داشتند.
 */
export default function AuthCard({ title, subtitle, icon = '🔐', children, footer, headTitle }) {
    return (
        <div dir="rtl" className="auth-solo">
            <Head title={headTitle || title} />
            <div className="auth-solo-card">
                <Link href="/" className="auth-solo-brand"><img src="/brand/logo-emblem.png" alt="" /> ستاره ماه</Link>
                <div className="auth-solo-ic">{icon}</div>
                <h1 className="auth-h" style={{ margin: '0 0 6px', textAlign: 'center' }}>{title}</h1>
                {subtitle && <p className="auth-sub" style={{ margin: '0 0 20px', textAlign: 'center' }}>{subtitle}</p>}
                {children}
                {footer && <div className="auth-solo-foot">{footer}</div>}
            </div>
        </div>
    );
}

export function PassInput({ value, onChange, placeholder, autoFocus, name, autoComplete = 'new-password' }) {
    return (
        <div className="pass-wrap">
            <input type="password" className="input" name={name} value={value} onChange={onChange} placeholder={placeholder}
                autoFocus={autoFocus} autoComplete={autoComplete} dir="ltr"
                onFocus={(e) => e.target.parentElement.classList.add('focus')} onBlur={(e) => e.target.parentElement.classList.remove('focus')} />
            <button type="button" tabIndex={-1} className="pass-eye" title="نمایش/پنهان"
                onClick={(e) => { const i = e.currentTarget.previousSibling; i.type = i.type === 'password' ? 'text' : 'password'; e.currentTarget.textContent = i.type === 'password' ? '👁️' : '🙈'; }}>👁️</button>
        </div>
    );
}
