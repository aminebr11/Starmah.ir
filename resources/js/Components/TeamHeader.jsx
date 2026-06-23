import { usePage } from '@inertiajs/react';

/**
 * هدر تیمی. اگر تصویر هدر آپلود شده باشد، عیناً همان تصویر نمایش داده می‌شود؛
 * در غیر این صورت نسخه‌ی گرادیانی با CSS اختصاصی تیم. (بدون منوی انگلیسی)
 */
export default function TeamHeader() {
    const { theme } = usePage().props;
    const skin = theme?.skin ?? {};
    const key = theme?.key ?? '';

    // اگر تصویر هدر اختصاصی هست → فقط همان تصویر
    if (theme?.header) {
        return (
            <div className={`team-header ${key}`} style={{ padding: 0, borderRadius: 18, overflow: 'hidden' }}>
                <img src={theme.header} alt={theme?.name} style={{ width: '100%', display: 'block' }} />
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
        </div>
    );
}
