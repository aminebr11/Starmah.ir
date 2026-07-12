import { usePage, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const TYPE = { mc: 'چهارگزینه‌ای', tf: 'درست/نادرست', desc: 'تشریحی', blank: 'جای خالی' };
const DIFF = { easy: 'آسان', medium: 'متوسط', hard: 'دشوار' };

export default function SmartQuestionBank() {
    const { questions = [], filters = {} } = usePage().props;
    const [f, setF] = useState({ subject: filters.subject || '', difficulty: filters.difficulty || '', type: filters.type || '', search: filters.search || '' });
    const apply = () => router.get(route('teacher.smart.bank'), f, { preserveState: true });

    return (
        <DashLayout title="بانک سؤال هوشمند" roleLabel="معلم" menu={teacherMenu} active="smart">
            <div className="smart-scope">
                <div className="smart-panel">
                    <div className="smart-h">🗄️ بانک سؤال هوشمند <span className="smart-badge">آزمایشی</span>
                        <Link href={route('teacher.smart.lab')} className="smart-btn ghost sm" style={{ marginInlineStart: 'auto' }}>← بازگشت به آزمایشگاه</Link>
                    </div>
                    <div className="smart-grid" style={{ marginTop: 12 }}>
                        <input className="smart-input" value={f.search} onChange={(e) => setF({ ...f, search: e.target.value })} placeholder="🔍 جست‌وجو در متن سؤال" />
                        <input className="smart-input" value={f.subject} onChange={(e) => setF({ ...f, subject: e.target.value })} placeholder="درس" />
                        <select className="smart-input" value={f.type} onChange={(e) => setF({ ...f, type: e.target.value })}><option value="">همه‌ی انواع</option>{Object.entries(TYPE).map(([v, t]) => <option key={v} value={v}>{t}</option>)}</select>
                        <select className="smart-input" value={f.difficulty} onChange={(e) => setF({ ...f, difficulty: e.target.value })}><option value="">همه‌ی سطوح</option>{Object.entries(DIFF).map(([v, t]) => <option key={v} value={v}>{t}</option>)}</select>
                    </div>
                    <button onClick={apply} className="smart-btn sm" style={{ marginTop: 10 }}>اعمال فیلتر</button>
                </div>

                <div className="smart-panel">
                    <div className="smart-h" style={{ fontSize: 15 }}>{fa(questions.length)} سؤال</div>
                    {questions.length === 0 && <p className="smart-muted">سؤالی یافت نشد. از استودیوی آزمون، سؤال‌ها را «به بانک اضافه کن».</p>}
                    <div style={{ display: 'grid', gap: 10, marginTop: 10 }}>
                        {questions.map((q) => (
                            <div key={q.id} className="smart-qcard">
                                <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap', marginBottom: 6 }}>
                                    <span className={`smart-tag ${q.source === 'ai' ? 'ai' : q.source === 'sample' ? 'sample' : 'man'}`}>{q.source === 'ai' ? 'AI' : q.source === 'sample' ? 'نمونه' : 'دستی'}</span>
                                    <span className="smart-muted" style={{ fontSize: 12 }}>{TYPE[q.type]} · {DIFF[q.difficulty]}{q.subject ? ` · ${q.subject}` : ''}{q.topic ? ` · ${q.topic}` : ''} · استفاده: {fa(q.used)}</span>
                                    {q.mine && <button onClick={() => confirm('این سؤال از بانک حذف شود؟') && router.delete(route('teacher.smart.bank.destroy', q.id), { preserveScroll: true })} className="smart-btn ghost sm" style={{ marginInlineStart: 'auto', color: '#e8505b' }}>🗑️</button>}
                                </div>
                                <b style={{ fontSize: 14 }}>{q.prompt}</b>
                                {(q.choices || []).length > 0 && <div className="smart-muted" style={{ fontSize: 12.5, marginTop: 4 }}>{(q.choices || []).map((c) => (c.correct ? '✅ ' : '▫️ ') + c.value).join('   ')}</div>}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </DashLayout>
    );
}
