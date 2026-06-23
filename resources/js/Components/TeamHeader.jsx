import { usePage } from '@inertiajs/react';

/**
 * هدر تیمی — دقیقاً مطابق طرح ۴ هدر تصویر.
 * کلاس تیم (fire-strikers / blue-thunders / ...) از theme.key می‌آید و CSS عینِ پیشنهاد اعمال می‌شود.
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
