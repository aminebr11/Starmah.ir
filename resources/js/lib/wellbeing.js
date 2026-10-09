import { router } from '@inertiajs/react';
import { toast } from './toast';

/**
 * «🌿 سلامتِ دیجیتال» سمتِ مرورگر:
 * - یادآوریِ استراحت (پنجره‌ی تمام‌صفحه با شمارشِ معکوس و حرکت‌های کششی)؛
 * - هشدارِ «۵ دقیقه‌ی دیگر»؛
 * - رفتن به صفحه‌ی «وقتِ استراحت» وقتی سقف پر شد.
 */
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const MOVES = ['👀 به دورترین نقطه‌ی اتاق نگاه کن (۲۰ ثانیه)', '🙆 دست‌هایت را بالا ببر و کش بیاور', '💧 یک لیوان آب بنوش', '🔄 شانه‌هایت را ۵ بار بچرخان', '🦒 گردنت را آرام به چپ و راست بچرخان', '🚶 کمی راه برو و برگرد'];
let warned = false;
let open = false;

function breakOverlay(minutes) {
    if (open || typeof document === 'undefined') return;
    open = true;
    let left = Math.max(1, minutes) * 60;
    const el = document.createElement('div');
    el.className = 'wb-break';
    el.setAttribute('dir', 'rtl');
    el.setAttribute('role', 'dialog');
    const moves = [...MOVES].sort(() => Math.random() - 0.5).slice(0, 3);
    el.innerHTML = `<div class="wb-card">
        <div class="wb-emoji">🧘</div>
        <h2>وقتِ یک استراحتِ کوتاه!</h2>
        <p>چشم‌ها و بدنت هم به استراحت نیاز دارند. این کارها را انجام بده:</p>
        <ul>${moves.map((m) => `<li>${m}</li>`).join('')}</ul>
        <div class="wb-timer"></div>
        <button type="button" class="wb-btn" disabled>صبر کن…</button>
    </div>`;
    document.body.appendChild(el);
    const t = el.querySelector('.wb-timer');
    const b = el.querySelector('.wb-btn');
    const tick = () => {
        t.textContent = left > 0 ? `${fa(Math.floor(left / 60))}:${fa(String(left % 60).padStart(2, '0'))}` : '✅';
        if (left <= 0) { b.disabled = false; b.textContent = 'استراحت کردم، برگردیم! 🚀'; }
    };
    tick();
    const id = setInterval(() => { left -= 1; tick(); if (left <= 0) clearInterval(id); }, 1000);
    b.addEventListener('click', () => { clearInterval(id); el.remove(); open = false; });
}

export function handleWellbeing(s) {
    if (!s) return;
    if (s.locked) {
        if (!location.pathname.startsWith('/time-up') && !location.pathname.startsWith('/live') && !location.pathname.startsWith('/family')) router.visit('/time-up');
        return;
    }
    if (s.break_due) breakOverlay(s.break_minutes || 3);
    if (s.left != null && s.left <= 300 && s.left > 0 && !warned) {
        warned = true;
        toast(`⏳ ${fa(Math.ceil(s.left / 60))} دقیقه‌ی دیگر از زمانِ امروزت مانده است.`, 'info');
    }
    if (s.left != null && s.left > 300) warned = false;
}
