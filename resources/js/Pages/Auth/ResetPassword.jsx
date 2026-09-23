import { Link, useForm } from '@inertiajs/react';
import AuthCard, { PassInput } from '@/Components/AuthCard';

/** تعیینِ رمزِ جدید از لینکِ ایمیل. */
export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({ token, email, password: '', password_confirmation: '' });
    const submit = (e) => { e.preventDefault(); post(route('password.store'), { onFinish: () => reset('password', 'password_confirmation') }); };

    return (
        <AuthCard icon="🔑" title="رمزِ جدید بساز" subtitle="یک رمزِ تازه انتخاب کن؛ حداقل ۸ کاراکتر و ترجیحاً ترکیبی از حرف و عدد."
            footer={<Link href={route('login')} className="link-gold" style={{ fontWeight: 700 }}>بازگشت به ورود ←</Link>}>
            <form onSubmit={submit}>
                <div className="field">
                    <label>ایمیل</label>
                    <input type="email" className="input" value={data.email} dir="ltr" autoComplete="username" onChange={(e) => setData('email', e.target.value)} />
                    {errors.email && <div className="err-msg">{errors.email}</div>}
                </div>
                <div className="field">
                    <label>رمزِ جدید</label>
                    <PassInput value={data.password} autoFocus onChange={(e) => setData('password', e.target.value)} placeholder="حداقل ۸ کاراکتر" />
                    {errors.password && <div className="err-msg">{errors.password}</div>}
                </div>
                <div className="field">
                    <label>تکرارِ رمزِ جدید</label>
                    <PassInput value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} placeholder="رمز را دوباره وارد کن" />
                    {errors.password_confirmation && <div className="err-msg">{errors.password_confirmation}</div>}
                </div>
                <button type="submit" disabled={processing} className="btn" style={{ width: '100%', marginTop: 6 }}>
                    {processing ? 'در حالِ ثبت…' : '✅ ثبتِ رمزِ جدید'}
                </button>
            </form>
        </AuthCard>
    );
}
