import { usePage } from '@inertiajs/react';

/**
 * هدر تیمی. اگر تصویر هدر آپلود شده باشد، عیناً همان تصویر نمایش داده می‌شود؛
 * در غیر این صورت نسخه‌ی گرادیانی با CSS اختصاصی تیم.
 */
const SOCIAL = {
    'fire-strikers': ['📸', '✈️', '🎮'],
    'blue-thunders': ['📸', '✈️', '🎮'],
    'creeper-warriors': ['🎮', '▶️', '📸'],
    'super-speed': ['📸', '▶️', '✈️'],
};

export default function TeamHeader() {
    const { theme } = usePage().props;
    const skin = theme?.skin ?? {};
    const key = theme?.key ?? '';
    const nav = skin.nav ?? ['HOME', 'ABOUT', 'PLAYERS', 'MATCHES', 'GALLERY', 'CONTACT'];

    // اگر تصویر هدر اختصاصی هست → همان را نشان بده
    if (theme?.header) {
        return (
            <div className={`team-header ${key}`} style={{ padding: 0 }}>
                <img src={theme.header} alt={theme?.name} style={{ width: '100%', display: 'block' }} />
                <nav style={{ background: `linear-gradient(90deg, ${skin.hdr1 ?? '#16264f'}, ${skin.hdr2 ?? '#0e1c3d'})` }}>
                    {nav.map((n, i) => <a key={i}>{n}</a>)}
                    <span className="th-social" style={{ padding: 0, marginInlineStart: 'auto' }}>
                        {(SOCIAL[key] ?? ['📸', '✈️', '🎮']).map((s, i) => <span key={i}>{s}</span>)}
                    </span>
                </nav>
            </div>
        );
    }

    // نسخه‌ی گرادیانی (وقتی تصویری آپلود نشده)
    return (
        <div className={`team-header ${key}`}>
            <div className="th-glow" />
            <div className="th-inner">
                <span className="th-shield">{skin.mascot}🛡️</span>
                <div>
                    <h1 className="th-title" style={{ fontFamily: skin.font }}>{skin.subtitle ?? theme?.name}</h1>
                    <div className="th-sub">{skin.sub_en}</div>
                </div>
                <span className="th-char">{skin.character ?? skin.hero}</span>
            </div>
            <nav>
                {nav.map((n, i) => <a key={i}>{n}</a>)}
            </nav>
            <div className="th-social">
                {(SOCIAL[key] ?? ['📸', '✈️', '🎮']).map((s, i) => <span key={i}>{s}</span>)}
            </div>
        </div>
    );
}
