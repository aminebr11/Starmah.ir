import { useRef, useEffect } from 'react';

/**
 * کانفتیِ سبک روی Canvas — بدون کتابخانه.
 * هر بار که prop `fire` (یک timestamp/عدد) تغییر کند یک انفجارِ ذرات پخش می‌شود.
 * `big` انفجارِ بزرگ‌ترِ برد. با prefers-reduced-motion غیرفعال می‌شود.
 */
const COLORS = ['#f5b53f', '#2bb673', '#3d7bf0', '#e8505b', '#a855f7', '#facc15', '#22d3ee'];

export default function Confetti({ fire, big = false, disabled = false }) {
    const ref = useRef(null);
    const raf = useRef(null);
    const parts = useRef([]);

    useEffect(() => {
        if (!fire || disabled) return;
        const canvas = ref.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const dpr = Math.min(2, window.devicePixelRatio || 1);
        const rect = canvas.parentElement.getBoundingClientRect();
        const W = rect.width, H = Math.max(220, rect.height);
        canvas.width = W * dpr; canvas.height = H * dpr;
        canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        const count = big ? 90 : 42;
        const cx = W / 2, cy = big ? H * 0.35 : H * 0.28;
        for (let i = 0; i < count; i++) {
            const a = Math.random() * Math.PI * 2;
            const sp = (big ? 4 : 3) + Math.random() * (big ? 7 : 4);
            parts.current.push({
                x: cx + (Math.random() - 0.5) * 40, y: cy,
                vx: Math.cos(a) * sp, vy: Math.sin(a) * sp - (big ? 5 : 3),
                g: 0.14 + Math.random() * 0.1,
                size: 4 + Math.random() * 5,
                color: COLORS[(Math.random() * COLORS.length) | 0],
                rot: Math.random() * 6.28, vr: (Math.random() - 0.5) * 0.3,
                life: 0, max: (big ? 80 : 55) + Math.random() * 30,
            });
        }

        cancelAnimationFrame(raf.current);
        const tick = () => {
            ctx.clearRect(0, 0, W, H);
            parts.current = parts.current.filter((p) => p.life < p.max);
            parts.current.forEach((p) => {
                p.life++; p.vy += p.g; p.x += p.vx; p.y += p.vy; p.rot += p.vr;
                const alpha = Math.max(0, 1 - p.life / p.max);
                ctx.save();
                ctx.globalAlpha = alpha;
                ctx.translate(p.x, p.y); ctx.rotate(p.rot);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                ctx.restore();
            });
            if (parts.current.length) raf.current = requestAnimationFrame(tick);
            else ctx.clearRect(0, 0, W, H);
        };
        raf.current = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf.current);
    }, [fire, big, disabled]);

    if (disabled) return null;
    return <canvas ref={ref} style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', pointerEvents: 'none', zIndex: 5 }} />;
}
