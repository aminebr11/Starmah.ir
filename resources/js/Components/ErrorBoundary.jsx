import { Component } from 'react';
import { router } from '@inertiajs/react';

/**
 * تورِ ایمنی برای خطای نمایش.
 *
 * بدونِ آن، یک خطا در هر جزءِ کوچک (مثلاً دستیار) کلِ برنامه را از صفحه
 * پاک می‌کرد و کاربر فقط یک صفحه‌ی سفید می‌دید. حالا:
 * - با `fallback={null}` فقط همان بخش پنهان می‌شود و بقیه‌ی سایت کار می‌کند؛
 * - بدونِ fallback، یک صفحه‌ی آبرومند با دکمه‌ی «تازه‌کردن» و متنِ خطا
 *   (برای فرستادن به پشتیبانی) نشان داده می‌شود.
 * با رفتن به صفحه‌ی دیگر، خطا پاک و دوباره تلاش می‌شود.
 */
export default class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { error: null };
    }

    static getDerivedStateFromError(error) {
        return { error };
    }

    componentDidCatch(error, info) {
        console.error('[ui]', this.props.name ?? 'app', error, info?.componentStack);
    }

    componentDidMount() {
        this.off = router.on('navigate', () => { if (this.state.error) this.setState({ error: null }); });
    }

    componentWillUnmount() {
        this.off?.();
    }

    render() {
        const { error } = this.state;
        if (!error) return this.props.children;
        if (this.props.fallback !== undefined) return this.props.fallback;

        return (
            <div dir="rtl" style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 24, fontFamily: 'inherit', color: '#231a45' }}>
                <div style={{ maxWidth: 420, width: '100%', background: '#fff', borderRadius: 24, padding: '28px 22px', textAlign: 'center',
                    boxShadow: '0 6px 0 #e6ddfb, 0 30px 60px -30px rgba(42,27,94,.45)' }}>
                    <div style={{ fontSize: 46 }}>🌙</div>
                    <h2 style={{ margin: '8px 0 6px', fontSize: 20, fontWeight: 900 }}>این صفحه درست باز نشد</h2>
                    <p style={{ margin: '0 0 18px', fontSize: 14, lineHeight: 1.9, color: '#5b5675' }}>
                        یک‌بار صفحه را تازه کنید. اگر دوباره دیدید، متنِ پایین را برای پشتیبانی بفرستید.
                    </p>
                    <button type="button" onClick={() => window.location.reload()}
                        style={{ border: 0, borderRadius: 16, padding: '12px 26px', fontFamily: 'inherit', fontSize: 15, fontWeight: 900, color: '#fff', cursor: 'pointer',
                            background: 'linear-gradient(135deg,#a78bfa,#7c3aed)', boxShadow: '0 4px 0 #5b21b6' }}>
                        ↻ تازه‌کردنِ صفحه
                    </button>
                    <a href="/" style={{ display: 'block', marginTop: 14, fontSize: 13, color: '#6d28d9' }}>رفتن به صفحه‌ی اول</a>
                    <code dir="ltr" style={{ display: 'block', marginTop: 16, padding: 10, borderRadius: 12, background: '#f5f2fd', color: '#7a7398', fontSize: 11, wordBreak: 'break-word' }}>
                        {String(error?.message ?? error).slice(0, 300)}
                    </code>
                </div>
            </div>
        );
    }
}
