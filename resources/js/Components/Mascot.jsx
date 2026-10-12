import { useId } from 'react';

/**
 * «ماهو» — شخصیتِ ستاره‌ماه: یک ماهِ کوچولوی طلایی با کلاهِ فارغ‌التحصیلی
 * که منگوله‌اش یک ستاره است (ماه + ستاره + مدرسه).
 *
 * حالت‌ها (mood): idle | think | talk | happy | wave | sleep
 * نگاه: با متغیرهای CSS «--lx/--ly» مردمک‌ها جابه‌جا می‌شوند (دنبال‌کردنِ نشانگر).
 * همه‌ی حرکت‌ها CSS است و با «کاهشِ حرکت»ِ سیستم خاموش می‌شود.
 */
export default function Mascot({ mood = 'idle', size = 56, className = '', style, title = 'ماهو' }) {
    const id = useId().replace(/:/g, '');
    const body = `mhb${id}`;
    const cap = `mhc${id}`;
    const glow = `mhg${id}`;

    return (
        <svg className={`mahoo is-${mood} ${className}`} viewBox="0 0 120 120" width={size} height={size} style={style}
            role="img" aria-label={title}>
            <defs>
                <radialGradient id={body} cx="38%" cy="34%" r="72%">
                    <stop offset="0" stopColor="#fff7d1" />
                    <stop offset=".5" stopColor="#ffd75e" />
                    <stop offset="1" stopColor="#f29a1f" />
                </radialGradient>
                <linearGradient id={cap} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="#9a6bff" />
                    <stop offset="1" stopColor="#5a23c8" />
                </linearGradient>
                <radialGradient id={glow} cx="50%" cy="50%" r="50%">
                    <stop offset="0" stopColor="#ffe28a" stopOpacity=".55" />
                    <stop offset="1" stopColor="#ffe28a" stopOpacity="0" />
                </radialGradient>
            </defs>

            <ellipse className="mh-shadow" cx="60" cy="113" rx="25" ry="4.5" />

            <g className="mh-float">
                <circle className="mh-aura" cx="60" cy="70" r="50" fill={`url(#${glow})`} />

                {/* دست‌ها */}
                <path className="mh-arm mh-arm-l" d="M25 80 q-11 1 -13 -9" />
                <g className="mh-arm-r"><path className="mh-arm" d="M95 80 q11 1 13 -9" /></g>

                {/* بدن: ماه با دهانه‌ها و برق */}
                <circle cx="60" cy="70" r="38" fill={`url(#${body})`} stroke="#e48d17" strokeOpacity=".45" strokeWidth="1.6" />
                <circle cx="85" cy="57" r="5.2" fill="#eda733" opacity=".5" />
                <circle cx="90" cy="76" r="3.2" fill="#eda733" opacity=".45" />
                <circle cx="35" cy="95" r="3.6" fill="#eda733" opacity=".4" />
                <ellipse cx="43" cy="51" rx="10" ry="5.5" fill="#fff" opacity=".5" transform="rotate(-32 43 51)" />

                {/* چشم‌ها */}
                <g className="mh-eyes">
                    <ellipse cx="47" cy="71" rx="8.6" ry="10" fill="#fff" />
                    <ellipse cx="73" cy="71" rx="8.6" ry="10" fill="#fff" />
                    <g className="mh-look">
                        <circle cx="48" cy="73" r="5.3" fill="#2a1b5e" />
                        <circle cx="74" cy="73" r="5.3" fill="#2a1b5e" />
                        <circle cx="50" cy="70.6" r="1.9" fill="#fff" />
                        <circle cx="76" cy="70.6" r="1.9" fill="#fff" />
                    </g>
                </g>
                <g className="mh-happy-eyes mh-line">
                    <path d="M40 73 q7 -9 14 0" />
                    <path d="M66 73 q7 -9 14 0" />
                </g>
                <g className="mh-sleep-eyes mh-line">
                    <path d="M40 71 q7 6 14 0" />
                    <path d="M66 71 q7 6 14 0" />
                </g>

                {/* گونه‌ها و دهان */}
                <ellipse className="mh-cheek" cx="36" cy="85" rx="6.2" ry="3.6" />
                <ellipse className="mh-cheek" cx="84" cy="85" rx="6.2" ry="3.6" />
                <path className="mh-smile" d="M52 86 q8 8 16 0" />
                <g className="mh-talk">
                    <ellipse cx="60" cy="89" rx="6" ry="4.6" fill="#7a2e0e" />
                    <ellipse cx="60" cy="91.6" rx="3.4" ry="1.8" fill="#ff8fa3" />
                </g>
                <circle className="mh-o" cx="62" cy="89" r="2.8" fill="#7a2e0e" />

                {/* کلاهِ فارغ‌التحصیلی با منگوله‌ی ستاره */}
                <g transform="rotate(-9 60 34)">
                    <path d="M42 35 L42 44 Q60 52 78 44 L78 35 Z" fill="#4a1a9e" />
                    <polygon points="60,19 95,31.5 60,44 25,31.5" fill={`url(#${cap})`} />
                    <polygon points="60,19 95,31.5 60,25" fill="#fff" opacity=".14" />
                    <g className="mh-tassel">
                        <path d="M60 31.5 L86 35 L87.5 49" fill="none" stroke="#ffcf3f" strokeWidth="2.2" strokeLinecap="round" />
                        <path className="mh-tstar" d="M87.5 47 l2.6 5.3 5.8 .8 -4.2 4.1 1 5.8 -5.2 -2.7 -5.2 2.7 1 -5.8 -4.2 -4.1 5.8 -.8z"
                            fill="#ffd84d" stroke="#f08c00" strokeWidth="1" strokeLinejoin="round" />
                    </g>
                    <circle cx="60" cy="31.5" r="2.6" fill="#ffcf3f" />
                </g>

                {/* درخشش‌ها، حباب‌های فکر، zzz */}
                <path className="mh-spark s1" d="M106 20 l2 6 6 2 -6 2 -2 6 -2 -6 -6 -2 6 -2z" />
                <path className="mh-spark s2" d="M12 38 l1.5 4.5 4.5 1.5 -4.5 1.5 -1.5 4.5 -1.5 -4.5 -4.5 -1.5 4.5 -1.5z" />
                <g className="mh-dots">
                    <circle cx="98" cy="44" r="3" />
                    <circle cx="105" cy="33" r="4" />
                    <circle cx="112" cy="20" r="5" />
                </g>
                <g className="mh-z">
                    <text x="92" y="36">z</text>
                    <text x="101" y="24">z</text>
                </g>
            </g>
        </svg>
    );
}
