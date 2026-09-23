import { useForm } from '@inertiajs/react';
import AuthCard, { PassInput } from '@/Components/AuthCard';

/** تأییدِ دوباره‌ی رمز پیش از بخش‌های حساس. */
export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });
    const submit = (e) => { e.preventDefault(); post(route('password.confirm'), { onFinish: () => reset('password') }); };

    return (
        <AuthCard icon="🛡️" title="تأییدِ رمزِ عبور" subtitle="این بخش حساس است؛ برای ادامه یک بار دیگر رمزت را وارد کن.">
            <form onSubmit={submit}>
                <div className="field">
                    <label>رمزِ عبور</label>
                    <PassInput value={data.password} autoFocus autoComplete="current-password" onChange={(e) => setData('password', e.target.value)} />
                    {errors.password && <div className="err-msg">{errors.password}</div>}
                </div>
                <button type="submit" disabled={processing} className="btn" style={{ width: '100%', marginTop: 6 }}>
                    {processing ? 'در حالِ بررسی…' : 'تأیید و ادامه ←'}
                </button>
            </form>
        </AuthCard>
    );
}
