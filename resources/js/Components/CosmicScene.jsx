import { useEffect, useRef } from 'react';

/**
 * صحنه‌ی کیهانی سه‌بعدی (Three.js) — فقط دسکتاپ.
 * Three به‌صورت پویا (lazy) بارگذاری می‌شود تا حجم اولیه‌ی موبایل سبک بماند.
 * روی موبایل/تاچ و حالت prefers-reduced-motion هیچ چیزی رندر نمی‌شود
 * (نسخه‌ی CSS در Welcome جایگزین می‌شود).
 */
export default function CosmicScene() {
    const mountRef = useRef(null);

    useEffect(() => {
        const el = mountRef.current;
        if (!el) return;

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const smallOrTouch = window.matchMedia('(max-width: 900px)').matches
            || window.matchMedia('(hover: none)').matches;
        if (reduce || smallOrTouch) return;

        let disposed = false;
        let cleanup = () => {};

        (async () => {
            const THREE = await import('three');
            if (disposed || !mountRef.current) return;

            const width = el.clientWidth || el.offsetWidth;
            const height = el.clientHeight || el.offsetHeight;

            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(60, width / height, 0.1, 100);
            camera.position.z = 26;

            const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
            renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
            renderer.setSize(width, height);
            renderer.setClearColor(0x000000, 0);
            el.appendChild(renderer.domElement);

            /* ---------- میدان ستاره‌ها با عمق ---------- */
            const COUNT = 1400;
            const positions = new Float32Array(COUNT * 3);
            const colors = new Float32Array(COUNT * 3);
            const palette = [
                new THREE.Color('#ffffff'),
                new THREE.Color('#ffd87a'),
                new THREE.Color('#7fb0ff'),
                new THREE.Color('#3ad0ff'),
            ];
            for (let i = 0; i < COUNT; i++) {
                const r = 18 + Math.random() * 40;
                const theta = Math.random() * Math.PI * 2;
                const phi = Math.acos(2 * Math.random() - 1);
                positions[i * 3] = r * Math.sin(phi) * Math.cos(theta);
                positions[i * 3 + 1] = r * Math.sin(phi) * Math.sin(theta);
                positions[i * 3 + 2] = r * Math.cos(phi) - 20;
                const c = palette[(Math.random() * palette.length) | 0];
                colors[i * 3] = c.r; colors[i * 3 + 1] = c.g; colors[i * 3 + 2] = c.b;
            }
            const starGeo = new THREE.BufferGeometry();
            starGeo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
            starGeo.setAttribute('color', new THREE.BufferAttribute(colors, 3));
            const starMat = new THREE.PointsMaterial({
                size: 0.42, sizeAttenuation: true, vertexColors: true,
                transparent: true, opacity: 0.9, depthWrite: false,
                blending: THREE.AdditiveBlending, map: softDisc(THREE),
            });
            const stars = new THREE.Points(starGeo, starMat);
            scene.add(stars);

            /* ---------- هاله‌های نئونی شناور (سیارات) ---------- */
            const glowTex = softDisc(THREE);
            const makeGlow = (color, size, x, y, z) => {
                const m = new THREE.SpriteMaterial({
                    map: glowTex, color: new THREE.Color(color),
                    transparent: true, opacity: 0.55, depthWrite: false,
                    blending: THREE.AdditiveBlending,
                });
                const s = new THREE.Sprite(m);
                s.scale.set(size, size, 1);
                s.position.set(x, y, z);
                return s;
            };
            const glows = [
                makeGlow('#f5b53f', 16, -14, 6, -6),   // ماه طلایی
                makeGlow('#3ad0ff', 9, 15, -7, -4),    // سیاره‌ی آبی
                makeGlow('#8b7cf6', 7, 12, 9, -10),    // بنفش
                makeGlow('#ff6b9d', 5, -12, -9, -8),   // صورتی
            ];
            glows.forEach((g) => scene.add(g));

            /* ---------- تعامل با اشاره‌گر (پارالاکس) ---------- */
            const target = { x: 0, y: 0 };
            const cur = { x: 0, y: 0 };
            const onPointer = (e) => {
                target.x = (e.clientX / window.innerWidth - 0.5) * 2;
                target.y = (e.clientY / window.innerHeight - 0.5) * 2;
            };
            window.addEventListener('pointermove', onPointer);

            /* ---------- حلقه‌ی انیمیشن ---------- */
            let raf = 0;
            const clock = new THREE.Clock();
            const loop = () => {
                const t = clock.getElapsedTime();
                cur.x += (target.x - cur.x) * 0.04;
                cur.y += (target.y - cur.y) * 0.04;
                stars.rotation.y = t * 0.02 + cur.x * 0.35;
                stars.rotation.x = cur.y * 0.25;
                glows.forEach((g, i) => {
                    g.position.y += Math.sin(t * 0.6 + i) * 0.004;
                    const base = 0.5 + 0.08 * Math.sin(t * 1.3 + i * 1.7);
                    g.material.opacity = base;
                });
                camera.position.x += (cur.x * 3 - camera.position.x) * 0.05;
                camera.position.y += (-cur.y * 2 - camera.position.y) * 0.05;
                camera.lookAt(0, 0, -10);
                renderer.render(scene, camera);
                raf = requestAnimationFrame(loop);
            };
            loop();

            /* ---------- ری‌سایز ---------- */
            const onResize = () => {
                const w = el.clientWidth, h = el.clientHeight;
                camera.aspect = w / h;
                camera.updateProjectionMatrix();
                renderer.setSize(w, h);
            };
            window.addEventListener('resize', onResize);

            cleanup = () => {
                cancelAnimationFrame(raf);
                window.removeEventListener('pointermove', onPointer);
                window.removeEventListener('resize', onResize);
                starGeo.dispose(); starMat.dispose();
                glows.forEach((g) => g.material.dispose());
                glowTex.dispose();
                renderer.dispose();
                if (renderer.domElement.parentNode === el) el.removeChild(renderer.domElement);
            };
        })();

        return () => { disposed = true; cleanup(); };
    }, []);

    return <div ref={mountRef} className="cosmic-canvas" aria-hidden="true" />;
}

/* بافت دایره‌ی نرم برای ستاره/هاله (به‌جای فایل تصویر — سبک و بدون درخواست شبکه) */
function softDisc(THREE) {
    const size = 64;
    const c = document.createElement('canvas');
    c.width = c.height = size;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
    g.addColorStop(0, 'rgba(255,255,255,1)');
    g.addColorStop(0.35, 'rgba(255,255,255,0.55)');
    g.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, size, size);
    const tex = new THREE.CanvasTexture(c);
    tex.needsUpdate = true;
    return tex;
}
