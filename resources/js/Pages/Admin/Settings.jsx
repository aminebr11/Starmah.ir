import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const ROWS = [
    ['🤖', 'هوش مصنوعی', 'کلید Claude/OpenAI برای تولید محتوا', 'به‌زودی'],
    ['💬', 'پیامک', 'سرویس پیامک برای ورود و اطلاع‌رسانی', 'به‌زودی'],
    ['💳', 'درگاه پرداخت', 'تنظیم درگاه و پلن‌های اشتراک', 'به‌زودی'],
    ['🏷️', 'پلن‌ها و قیمت‌گذاری', 'تعریف پلن‌های مدارس', 'به‌زودی'],
    ['🌐', 'تنظیمات عمومی', 'نام، لوگو و اطلاعات پلتفرم', 'به‌زودی'],
];

export default function Settings() {
    return (
        <DashLayout title="تنظیمات پلتفرم" roleLabel="ادمین کل" menu={adminMenu} active="settings">
            <div className="panel">
                <h3>⚙️ تنظیمات و دسترسی‌ها</h3>
                {ROWS.map(([ic, t, d, s]) => (
                    <div key={t} style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '14px 0', borderBottom: '1px solid var(--line)' }}>
                        <div style={{ fontSize: 26 }}>{ic}</div>
                        <div style={{ flex: 1 }}><div style={{ fontWeight: 700 }}>{t}</div><div style={{ color: 'var(--muted)', fontSize: 13 }}>{d}</div></div>
                        <span className="tag tag-info">{s}</span>
                    </div>
                ))}
            </div>
        </DashLayout>
    );
}
