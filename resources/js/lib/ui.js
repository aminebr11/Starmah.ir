/**
 * طرحِ ظاهری — classic (طرحِ فعلی) یا clay («خمیرماه»).
 * انتخاب در کوکیِ sm_ui می‌ماند تا سرور هم از همان اولِ صفحه بداند
 * (data-ui روی <html>) و صفحه با طرحِ اشتباه چشمک نزند.
 */
export const UI_SKINS = ['classic', 'clay'];

function writeCookie(v) {
    try {
        document.cookie = `sm_ui=${v}; path=/; max-age=${60 * 60 * 24 * 365}; samesite=lax`;
    } catch { /* کوکی بسته است — فقط همین صفحه عوض می‌شود */ }
}

export function currentUi() {
    return document.documentElement.dataset.ui === 'clay' ? 'clay' : 'classic';
}

export function setUi(v) {
    if (!UI_SKINS.includes(v)) return;
    writeCookie(v);
    document.documentElement.dataset.ui = v;
    // چیدمانِ منو هم عوض می‌شود، پس صفحه یک‌بار کامل بارگذاری شود
    const url = new URL(window.location.href);
    url.searchParams.delete('ui');
    window.location.replace(url.toString());
}

/** ?ui=clay در نشانی → همان انتخاب برای صفحه‌های بعدی هم بماند. */
export function initUi() {
    try {
        const q = new URLSearchParams(window.location.search).get('ui');
        if (UI_SKINS.includes(q)) writeCookie(q);
    } catch { /* بی‌خیال */ }
}
