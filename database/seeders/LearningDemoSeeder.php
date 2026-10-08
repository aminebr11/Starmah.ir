<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Mission;
use App\Models\ObjectiveReview;
use App\Models\PracticeAnswer;
use App\Models\School;
use App\Models\SmartQuestionBank;
use App\Models\User;
use App\Services\LearningService;
use Illuminate\Database\Seeder;

/**
 * محتوای نمایشیِ «هسته‌ی یادگیری» — فقط برای مدرسه‌ی نمونه (starmah-demo).
 *
 * ۳۰ سؤالِ ریاضیِ چهارم در سه مبحث (با راهنما و توضیح)، سه مأموریتِ روزانه،
 * و ده روز سابقه‌ی تمرینِ شبیه‌سازی‌شده برای دانش‌آموزانِ نمونه تا نقشه‌ی
 * تسلطِ معلم و «مرورِ امروز» از همان اول دیده شوند.
 *
 * اجرا: php artisan db:seed --class=LearningDemoSeeder
 */
class LearningDemoSeeder extends Seeder
{
    private const TOPICS = [
        'کسرهای مساوی' => ['icon' => '🍕', 'lesson' => '2', 'q' => [
            ['کدام کسر با ۱/۲ برابر است؟', ['۳/۶', '۲/۳', '۱/۴', '۲/۵'], 'صورت و مخرجِ ۱/۲ را در یک عدد ضرب کن؛ مثلاً در ۳.', '۱×۳=۳ و ۲×۳=۶؛ پس ۳/۶ همان نصف است.', 'easy'],
            ['کدام کسر با ۲/۳ برابر است؟', ['۴/۶', '۲/۶', '۳/۴', '۴/۹'], 'صورت و مخرج را در ۲ ضرب کن.', '۲×۲=۴ و ۳×۲=۶؛ پس ۴/۶ = ۲/۳.', 'easy'],
            ['۱/۴ با کدام کسر برابر است؟', ['۲/۸', '۱/۸', '۲/۴', '۴/۱'], 'اگر پیتزا را به ۸ قسمت کنی، یک‌چهارمش چند قسمت می‌شود؟', '۱/۴ یعنی یکی از چهار قسمت؛ در ۸ قسمت، ۲ قسمت می‌شود: ۲/۸.', 'easy'],
            ['کسرِ ساده‌شده‌ی ۶/۸ کدام است؟', ['۳/۴', '۲/۳', '۶/۴', '۱/۲'], 'صورت و مخرج را بر ۲ تقسیم کن.', '۶÷۲=۳ و ۸÷۲=۴؛ پس ۶/۸ = ۳/۴.', 'medium'],
            ['در کسرِ ۳/۵ = ؟/۱۰، جای خالی چند است؟', ['۶', '۵', '۳', '۸'], 'مخرج ۵ چند برابر شده تا ۱۰ شود؟ صورت هم همان‌قدر بزرگ می‌شود.', 'مخرج دو برابر شده (۵×۲=۱۰)، پس صورت هم دو برابر می‌شود: ۳×۲=۶.', 'medium'],
            ['کدام دو کسر با هم مساوی‌اند؟', ['۲/۴ و ۵/۱۰', '۱/۳ و ۲/۴', '۳/۴ و ۴/۳', '۲/۵ و ۵/۲'], 'هر دو کسر را ساده کن؛ ببین به یک کسر می‌رسند یا نه.', '۲/۴ و ۵/۱۰ هر دو برابرِ ۱/۲ هستند.', 'medium'],
            ['۸/۱۲ ساده‌شده برابر است با:', ['۲/۳', '۴/۳', '۳/۴', '۱/۲'], 'بزرگ‌ترین عددی که هم ۸ و هم ۱۲ بر آن بخش‌پذیرند، ۴ است.', '۸÷۴=۲ و ۱۲÷۴=۳؛ پس ۸/۱۲ = ۲/۳.', 'hard'],
            ['اگر نصفِ یک کیک را خورده باشیم، چند تکه از یک کیکِ ۱۰ تکه‌ای خورده‌ایم؟', ['۵', '۲', '۱۰', '۴'], 'نصفِ ۱۰ چند است؟', 'نصف یعنی ۱/۲؛ و ۱/۲ = ۵/۱۰، پس ۵ تکه.', 'easy'],
            ['۳/۹ با کدام کسر برابر است؟', ['۱/۳', '۱/۹', '۳/۳', '۹/۳'], 'صورت و مخرج را بر ۳ تقسیم کن.', '۳÷۳=۱ و ۹÷۳=۳؛ پس ۳/۹ = ۱/۳.', 'medium'],
            ['کدام کسر با ۴/۵ برابر نیست؟', ['۵/۶', '۸/۱۰', '۱۲/۱۵', '۱۶/۲۰'], 'هر گزینه را ساده کن و با ۴/۵ مقایسه کن.', '۸/۱۰، ۱۲/۱۵ و ۱۶/۲۰ همه برابرِ ۴/۵‌اند؛ ولی ۵/۶ نه.', 'hard'],
        ]],
        'ضرب دورقمی' => ['icon' => '✖️', 'lesson' => '3', 'q' => [
            ['حاصلِ ۱۲ × ۳ چند است؟', ['۳۶', '۳۲', '۱۵', '۳۹'], '۱۲ را به ۱۰ و ۲ بشکن: ۱۰×۳ و ۲×۳.', '۱۰×۳=۳۰ و ۲×۳=۶؛ جمع: ۳۶.', 'easy'],
            ['حاصلِ ۲۵ × ۴ چند است؟', ['۱۰۰', '۸۰', '۹۰', '۱۲۰'], 'چهار تا ۲۵ تومانی چقدر می‌شود؟', '۲۵+۲۵+۲۵+۲۵ = ۱۰۰.', 'easy'],
            ['حاصلِ ۱۴ × ۱۰ چند است؟', ['۱۴۰', '۱۰۴', '۴۱', '۱۴'], 'ضرب در ۱۰ یعنی یک صفر کنارِ عدد.', '۱۴ × ۱۰ = ۱۴۰.', 'easy'],
            ['حاصلِ ۲۳ × ۱۲ چند است؟', ['۲۷۶', '۲۴۶', '۲۳۶', '۳۷۶'], '۲۳×۱۰ را حساب کن، بعد ۲۳×۲ را به آن اضافه کن.', '۲۳۰ + ۴۶ = ۲۷۶.', 'medium'],
            ['حاصلِ ۱۵ × ۱۵ چند است؟', ['۲۲۵', '۲۰۰', '۲۵۵', '۱۵۰'], '۱۵×۱۰ و ۱۵×۵ را جدا حساب کن.', '۱۵۰ + ۷۵ = ۲۲۵.', 'medium'],
            ['یک جعبه ۱۸ مداد دارد. ۵ جعبه چند مداد دارد؟', ['۹۰', '۸۰', '۲۳', '۱۰۰'], '۱۸ را ۵ بار جمع کن، یا ۲۰×۵ منهای ۲×۵.', '۱۸ × ۵ = ۹۰.', 'medium'],
            ['حاصلِ ۳۲ × ۲۱ چند است؟', ['۶۷۲', '۶۴۲', '۵۷۲', '۶۹۲'], '۳۲×۲۰ و ۳۲×۱ را جمع کن.', '۶۴۰ + ۳۲ = ۶۷۲.', 'hard'],
            ['حاصلِ ۴۵ × ۲ چند است؟', ['۹۰', '۸۰', '۴۷', '۸۵'], 'دو برابرِ ۴۵ یعنی ۴۵+۴۵.', '۴۵ + ۴۵ = ۹۰.', 'easy'],
            ['کلاسی ۲۴ دانش‌آموز دارد و هر کدام ۳ کتاب. روی هم چند کتاب؟', ['۷۲', '۶۲', '۲۷', '۸۲'], '۲۰×۳ و ۴×۳ را جمع کن.', '۶۰ + ۱۲ = ۷۲.', 'medium'],
            ['حاصلِ ۱۹ × ۱۱ چند است؟', ['۲۰۹', '۱۹۹', '۲۱۹', '۱۹۰'], '۱۹×۱۰ را حساب کن و یک ۱۹ دیگر اضافه کن.', '۱۹۰ + ۱۹ = ۲۰۹.', 'hard'],
        ]],
        'محیط شکل‌ها' => ['icon' => '📐', 'lesson' => '5', 'q' => [
            ['محیطِ مربعی با ضلعِ ۵ سانتی‌متر چند سانتی‌متر است؟', ['۲۰', '۲۵', '۱۰', '۱۵'], 'مربع ۴ ضلعِ مساوی دارد؛ همه را جمع کن.', '۵+۵+۵+۵ = ۲۰ سانتی‌متر.', 'easy'],
            ['محیطِ مستطیلی به طولِ ۶ و عرضِ ۴ چند است؟', ['۲۰', '۲۴', '۱۰', '۱۴'], 'دو طول و دو عرض را جمع کن.', '۶+۴+۶+۴ = ۲۰.', 'easy'],
            ['محیطِ مثلثی با ضلع‌های ۳، ۴ و ۵ چند است؟', ['۱۲', '۶۰', '۹', '۱۵'], 'محیط یعنی دورِ شکل؛ هر سه ضلع را جمع کن.', '۳+۴+۵ = ۱۲.', 'easy'],
            ['محیطِ یک مربع ۳۶ سانتی‌متر است. هر ضلعش چند است؟', ['۹', '۶', '۱۸', '۱۲'], 'محیط = ۴ × ضلع؛ پس ضلع = محیط ÷ ۴.', '۳۶ ÷ ۴ = ۹.', 'medium'],
            ['دورِ باغچه‌ای مستطیلی به ابعادِ ۸ و ۳ متر را نرده می‌کشیم. چند متر نرده لازم است؟', ['۲۲', '۲۴', '۱۱', '۱۶'], 'نرده دورتادورِ باغچه است؛ یعنی محیط.', '۸+۳+۸+۳ = ۲۲ متر.', 'medium'],
            ['محیط یعنی:', ['اندازه‌ی دورتادورِ شکل', 'اندازه‌ی سطحِ داخلِ شکل', 'بلندترین ضلع', 'تعدادِ گوشه‌ها'], 'اگر مورچه‌ای دورِ شکل راه برود، چه چیزی را اندازه گرفته؟', 'محیط طولِ مسیرِ دورتادورِ شکل است.', 'easy'],
            ['محیطِ شش‌ضلعیِ منتظمی با ضلعِ ۴ چند است؟', ['۲۴', '۱۶', '۲۰', '۱۰'], 'شش ضلعِ مساوی دارد.', '۶ × ۴ = ۲۴.', 'medium'],
            ['مستطیلی محیطِ ۳۰ و طولِ ۱۰ دارد. عرضش چند است؟', ['۵', '۱۰', '۲۰', '۳'], 'دو طول روی هم ۲۰ است؛ باقیِ محیط مالِ دو عرض است.', '۳۰ − ۲۰ = ۱۰ برای دو عرض؛ پس هر عرض ۵.', 'hard'],
            ['کدام شکل محیطِ بیشتری دارد؟', ['مستطیلِ ۷ در ۲', 'مربعِ ضلعِ ۴', 'مثلثِ ضلع‌های ۴، ۴، ۴', 'مربعِ ضلعِ ۳'], 'محیطِ هر گزینه را جدا حساب کن.', 'مستطیل: ۱۸، مربعِ ۴: ۱۶، مثلث: ۱۲، مربعِ ۳: ۱۲.', 'hard'],
            ['محیطِ مربعی با ضلعِ ۱۰ متر چند متر است؟', ['۴۰', '۱۰۰', '۲۰', '۱۴'], 'ضلع را در ۴ ضرب کن.', '۱۰ × ۴ = ۴۰ متر.', 'easy'],
        ]],
    ];

    /** احتمالِ پاسخِ درست برای هر دانش‌آموزِ نمونه در هر مبحث (برای سابقه‌ی شبیه‌سازی‌شده). */
    private const PROFILES = [
        '09120000010' => [0.62, 0.82, 0.30],
        '09120000011' => [0.75, 0.62, 0.82],
        '09120000012' => [0.28, 0.56, 0.32],
        '09120000013' => [0.92, 0.95, 0.88],
        '09120000014' => [0.60, 0.72, 0.66],
        '09120000015' => [0.86, 0.80, 0.90],
        '09120000016' => [0.25, 0.30, 0.62],
        '09120000017' => [0.55, 0.76, 0.50],
    ];

    public function run(): void
    {
        $school = School::where('slug', 'starmah-demo')->first();
        $teacher = User::where('phone', '09120000002')->first();
        $classroom = $school ? Classroom::where('school_id', $school->id)->where('teacher_id', $teacher?->id)->first() : null;
        if (! $school || ! $teacher || ! $classroom) {
            $this->command?->warn('مدرسه‌ی نمونه پیدا نشد — اول DemoSeeder را اجرا کنید.');

            return;
        }

        $byTopic = [];
        foreach (self::TOPICS as $topic => $def) {
            foreach ($def['q'] as [$prompt, $choices, $hint, $explanation, $difficulty]) {
                $q = SmartQuestionBank::withoutGlobalScopes()->firstOrNew(['school_id' => $school->id, 'teacher_id' => $teacher->id, 'prompt' => $prompt]);
                $q->fill([
                    'scope' => 'school', 'type' => 'mc', 'level' => 'دبستان', 'grade' => 'چهارم',
                    'subject' => 'ریاضی', 'book' => 'ریاضی', 'lesson_no' => $def['lesson'], 'topic' => $topic,
                    'choices' => collect($choices)->map(fn ($c, $k) => ['value' => $c, 'correct' => $k === 0])->all(),
                    'hint' => $hint, 'explanation' => $explanation, 'difficulty' => $difficulty,
                    'source' => 'manual', 'approval' => 'approved',
                    'fingerprint' => \App\Support\Curriculum::fingerprint($prompt),
                ])->save();
                $byTopic[$topic][] = $q;
            }

            Mission::updateOrCreate(['teacher_id' => $teacher->id, 'title' => $topic], [
                'school_id' => $school->id, 'classroom_id' => $classroom->id,
                'description' => 'تمرینِ روزانه‌ی «' . $topic . '» — هر سؤال دو فرصت دارد.',
                'type' => 'quiz', 'subject' => 'ریاضی', 'lesson_no' => $def['lesson'],
                'question_ids' => collect($byTopic[$topic])->pluck('id')->all(), 'question_count' => 6,
                'pass_percent' => 60, 'xp_reward' => 20, 'badge_icon' => $def['icon'],
                'is_active' => true, 'repeat_mode' => 'daily',
            ]);
        }

        $this->history($school, $classroom, $byTopic);
        $this->command?->info('محتوای نمایشیِ هسته‌ی یادگیری آماده شد: ۳۰ سؤال، ۳ مأموریت و سابقه‌ی تمرین.');
    }

    /** ده روز سابقه‌ی تمرینِ شبیه‌سازی‌شده (قطعی، با بذرِ ثابت). */
    private function history(School $school, Classroom $classroom, array $byTopic): void
    {
        mt_srand(1404);
        $topics = array_keys($byTopic);
        $students = $classroom->students()->get(['users.id', 'users.phone', 'users.school_id']);

        foreach ($students as $st) {
            $profile = self::PROFILES[$st->phone] ?? null;
            if (! $profile || PracticeAnswer::withoutGlobalScopes()->where('student_id', $st->id)->exists()) {
                continue;
            }
            foreach ($topics as $ti => $topic) {
                $p = $profile[$ti];
                $rows = [];
                $correct = 0;
                $n = mt_rand(6, 9);
                $lastDay = 0;
                for ($k = 0; $k < $n; $k++) {
                    $q = $byTopic[$topic][mt_rand(0, count($byTopic[$topic]) - 1)];
                    $ok = mt_rand(0, 1000) / 1000 < $p;
                    $firstTry = $ok ? mt_rand(0, 100) > 20 : true;
                    $day = 10 - intdiv($k * 10, $n);
                    $lastDay = $day;
                    $correct += $ok ? 1 : 0;
                    $rows[] = [
                        'school_id' => $school->id, 'student_id' => $st->id, 'objective_id' => $q->objective_id,
                        'bank_id' => $q->id, 'source' => 'review', 'source_id' => null,
                        'correct' => $ok, 'first_try' => $firstTry, 'hinted' => false,
                        'created_at' => now()->subDays($day)->setTime(mt_rand(8, 18), mt_rand(0, 59)),
                    ];
                }
                PracticeAnswer::insert($rows);

                $ratio = $correct / $n;
                $box = $ratio >= 0.8 ? 3 : ($ratio >= 0.6 ? 2 : 1);
                $seen = now()->subDays($lastDay);
                ObjectiveReview::withoutGlobalScopes()->updateOrCreate(
                    ['student_id' => $st->id, 'objective_id' => $rows[0]['objective_id']],
                    ['school_id' => $school->id, 'box' => $box, 'attempts' => $n, 'correct' => $correct,
                        'last_seen_at' => $seen,
                        'due_at' => $seen->copy()->startOfDay()->addDays(LearningService::INTERVALS[$box])],
                );
            }
        }
    }
}
