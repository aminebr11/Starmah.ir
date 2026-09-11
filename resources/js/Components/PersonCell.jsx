import Avatar from '@/Components/Avatar';

/**
 * سلولِ «عکس + نام» — هرجا نامِ دانش‌آموز، معلم، مدیر یا مدرسه نمایش
 * داده می‌شود از همین استفاده می‌کنیم تا کاربران چهره‌ها را بشناسند.
 *
 * props:
 *   name    نامِ نمایشی
 *   avatar  نشانیِ عکس (اگر نبود، حرفِ اولِ نام روی گرادیان نشان داده می‌شود)
 *   sub     خطِ دومِ کوچک (کلاس، نقش، شماره و…)
 *   size    اندازه‌ی عکس
 *   href    اگر داده شود، کلِ سلول لینک می‌شود
 */
export default function PersonCell({ name, avatar = null, sub = null, size = 34, emoji = null, className = '' }) {
    return (
        <span className={`person-cell ${className}`}>
            {emoji && !avatar
                ? <span className="person-cell-emoji" style={{ width: size, height: size }}>{emoji}</span>
                : <Avatar src={avatar} name={name} size={size} />}
            <span className="person-cell-txt">
                <b>{name || '—'}</b>
                {sub && <small>{sub}</small>}
            </span>
        </span>
    );
}
