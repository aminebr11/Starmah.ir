import { useEffect } from 'react';

/**
 * وقتی دانش‌آموز از اعلان وارد می‌شود (مثلاً ?game=12)، همان کارت پیدا،
 * به وسطِ صفحه آورده و چند ثانیه درخشان می‌شود تا بداند کدام مورد تازه است.
 */
export function useHighlightFromQuery(param) {
    useEffect(() => {
        let id = null;
        try { id = new URLSearchParams(window.location.search).get(param); } catch { /* نادیده */ }
        if (!id) return;
        const t = setTimeout(() => {
            const el = document.querySelector(`[data-hl="${CSS.escape(id)}"]`);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('hl-pulse');
            setTimeout(() => el.classList.remove('hl-pulse'), 4200);
        }, 250);
        return () => clearTimeout(t);
    }, [param]);
}
