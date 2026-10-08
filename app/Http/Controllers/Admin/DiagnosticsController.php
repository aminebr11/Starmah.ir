<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FriendlySaveErrors;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\EduGame;
use App\Models\SmartExam;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «سلامتِ سیستم» برای ادمینِ کل.
 *
 * چون به‌روزرسانی‌ها دستی روی هاست ریخته می‌شوند، رایج‌ترین علتِ «خطای ۵۰۰»
 * یکی از این‌هاست: مایگریشنِ اجرانشده (ستون/جدولِ ناموجود)، charsetِ غیر utf8mb4،
 * یا فایلِ قدیمی در کشِ PHP. این صفحه همه را روی خودِ هاست نشان می‌دهد و
 * «آزمایشِ ذخیره» را واقعاً روی پایگاه‌داده‌ی هاست اجرا و سپس برمی‌گرداند.
 */
class DiagnosticsController extends Controller
{
    /** ستون‌هایی که مسیرهای حساس (آزمون، بازی، بانک، اعلان، گالری) به آن‌ها نیاز دارند. */
    private const REQUIRED = [
        'smart_exams' => ['school_id', 'teacher_id', 'title', 'level', 'grade', 'subject', 'book', 'chapter_id', 'chapter', 'topic', 'goal', 'kind', 'status', 'adaptive', 'rules', 'opens_at', 'closes_at', 'version'],
        'smart_exam_questions' => ['smart_exam_id', 'bank_id', 'type', 'prompt', 'choices', 'answer', 'explanation', 'goal', 'difficulty', 'bloom', 'points', 'topic', 'source', 'sort', 'chapter_id'],
        'smart_exam_targets' => ['smart_exam_id', 'classroom_id', 'theme_id', 'student_id'],
        'smart_exam_attempts' => ['smart_exam_id', 'student_id', 'status', 'score'],
        'smart_question_bank' => ['school_id', 'teacher_id', 'type', 'prompt', 'choices', 'answer', 'level', 'grade', 'subject', 'lesson_no', 'chapter_id', 'chapter', 'topic', 'goal', 'difficulty', 'bloom', 'hint', 'fingerprint', 'source', 'used_count', 'objective_id'],
        'edu_games' => ['school_id', 'teacher_id', 'template_key', 'title', 'level', 'grade', 'subject', 'chapter_id', 'chapter', 'topic', 'goal', 'difficulty', 'status', 'publish_at', 'close_at', 'rules'],
        'edu_game_questions' => ['edu_game_id', 'bank_id', 'type', 'prompt', 'choices', 'hint1', 'explanation', 'points', 'difficulty', 'bloom', 'topic', 'source', 'sort', 'chapter_id'],
        'announcements' => ['school_id', 'sender_id', 'title', 'body', 'audience', 'link'],
        'announcement_recipients' => ['announcement_id', 'user_id', 'read_at'],
        'xp_ledger' => ['student_id', 'amount', 'source_type', 'source_id'],
        'class_contents' => ['type', 'publish_at', 'is_visible', 'notified_at', 'album_id'],
        'remediations' => ['student_id', 'source', 'source_id', 'q_key', 'objective_id', 'question', 'cap_xp', 'recovered_xp', 'step', 'due_on', 'status', 'plan'],
        'remediation_plans' => ['classroom_id', 'teacher_id', 'settings'],
        'learning_objectives' => ['key', 'grade', 'subject', 'chapter_id', 'label'],
        'objective_reviews' => ['student_id', 'objective_id', 'box', 'due_at'],
        'practice_answers' => ['student_id', 'objective_id', 'source', 'correct', 'first_try', 'hinted'],
        'review_completions' => ['student_id', 'play_date', 'xp_awarded'],
        'point_batches' => ['teacher_id', 'amount', 'reason', 'team_mode', 'target_label', 'students_count'],
        'team_points' => ['batch_id'],
        'activity_awards' => ['batch_id'],
        'gallery_albums' => ['teacher_id', 'title', 'cover_id'],
        'ai_usages' => ['provider', 'input_tokens'],
        'visits' => [],
        'curriculum_chapters' => ['id'],
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Diagnostics', [
            'env' => $this->environment(),
            'pending' => $this->pendingMigrations(),
            'schema' => $this->schemaProblems(),
            'charset' => $this->charsetProblems(),
            'errors' => $this->recentErrors(),
            'autoFail' => $this->pendingMigrations() ? \App\Support\AutoMigrate::lastFailure() : null,
        ]);
    }

    private function environment(): array
    {
        $db = DB::connection();
        $version = rescue(fn () => $db->getDriverName() === 'sqlite'
            ? 'SQLite ' . DB::selectOne('select sqlite_version() as v')->v
            : DB::selectOne('select version() as v')->v, '—', false);
        $opcache = function_exists('opcache_get_status') ? rescue(fn () => opcache_get_status(false), null, false) : null;

        return [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'db' => $db->getDriverName() . ' — ' . $version,
            'database' => $db->getDatabaseName(),
            'timezone' => config('app.timezone'),
            'debug' => (bool) config('app.debug'),
            'opcache' => $opcache ? (($opcache['opcache_enabled'] ?? false) ? 'روشن' : 'خاموش') : 'نصب نیست',
            'opcache_revalidate' => ini_get('opcache.validate_timestamps') === '0' ? 'خاموش (فایل‌های تازه بدونِ پاک‌کردنِ کش دیده نمی‌شوند!)' : 'روشن',
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'last_commit' => rescue(fn () => trim((string) @file_get_contents(base_path('.git/HEAD'))), null, false),
        ];
    }

    private function pendingMigrations(): array
    {
        return rescue(function () {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles([database_path('migrations')]);
            $ran = Schema::hasTable('migrations') ? $migrator->getRepository()->getRan() : [];

            return array_values(array_diff(array_keys($files), $ran));
        }, [], false);
    }

    private function schemaProblems(): array
    {
        $out = [];
        foreach (self::REQUIRED as $table => $cols) {
            if (! Schema::hasTable($table)) {
                $out[] = ['table' => $table, 'missing' => ['(کلِ جدول)']];
                continue;
            }
            $have = array_map('strtolower', Schema::getColumnListing($table));
            $missing = array_values(array_filter($cols, fn ($c) => ! in_array(strtolower($c), $have, true)));
            if ($missing) $out[] = ['table' => $table, 'missing' => $missing];
        }

        return $out;
    }

    private function charsetProblems(): array
    {
        if (DB::connection()->getDriverName() !== 'mysql' && DB::connection()->getDriverName() !== 'mariadb') {
            return [];
        }

        return rescue(fn () => collect(DB::select(
            'select TABLE_NAME as t, TABLE_COLLATION as c from information_schema.TABLES where TABLE_SCHEMA = ? and TABLE_COLLATION not like ?',
            [DB::connection()->getDatabaseName(), 'utf8mb4%']
        ))->map(fn ($r) => ['table' => $r->t, 'collation' => $r->c])->all(), [], false);
    }

    /** آخرین خطاهای لاگ (فقط عنوان و پیام؛ بدونِ جزئیاتِ حساس). */
    private function recentErrors(int $limit = 15): array
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $out = [];
        foreach (array_slice($files, 0, 2) as $f) {
            $size = filesize($f);
            $h = @fopen($f, 'r');
            if (! $h) continue;
            fseek($h, max(0, $size - 400000));
            $chunk = stream_get_contents($h);
            fclose($h);
            preg_match_all('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $chunk, $m, PREG_SET_ORDER);
            foreach (array_reverse($m) as $x) {
                $msg = preg_replace('/\{"exception".*$/s', '', $x[3]);
                $msg = preg_replace('/\(Connection: .*$/s', '', $msg);
                $out[] = ['at' => $x[1], 'level' => $x[2], 'message' => Str::limit(trim($msg), 400)];
                if (count($out) >= $limit) break 2;
            }
        }

        return $out;
    }

    /** اجرای مایگریشن‌های باقی‌مانده (همان «php artisan migrate --force»). */
    public function migrate(): JsonResponse
    {
        try {
            @set_time_limit(300);
            Artisan::call('migrate', ['--force' => true]);

            return response()->json(['ok' => true, 'output' => trim(Artisan::output()), 'pending' => $this->pendingMigrations(), 'schema' => $this->schemaProblems()]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'output' => FriendlySaveErrors::explainError($e) . "\n\n" . Str::limit($e->getMessage(), 800)]);
        }
    }

    /** پاک‌کردنِ همه‌ی کش‌ها (معادلِ optimize:clear) و کشِ PHP (opcache). */
    public function clearCaches(): JsonResponse
    {
        $log = [];
        try {
            Artisan::call('optimize:clear');
            $log[] = trim(Artisan::output());
        } catch (\Throwable $e) {
            $log[] = 'optimize:clear: ' . $e->getMessage();
        }
        if (function_exists('opcache_reset')) {
            $log[] = @opcache_reset() ? 'کشِ PHP (opcache) پاک شد.' : 'opcache پاک نشد (روی این هاست مجاز نیست).';
        }

        return response()->json(['ok' => true, 'output' => implode("\n", $log)]);
    }

    /**
     * آزمایشِ واقعیِ ذخیره روی پایگاه‌داده‌ی هاست: یک آزمون و یک بازیِ نمونه با سؤالِ
     * شبیهِ خروجیِ هوش مصنوعی ساخته، در بانک ثبت و اعلان‌سازی شبیه‌سازی می‌شود؛
     * سپس همه‌چیز برگردانده (rollback) می‌شود. هر مرحله جدا گزارش می‌شود.
     */
    public function testSave(Request $request): JsonResponse
    {
        $teacher = User::role(\App\Support\Roles::TEACHER)->whereNotNull('school_id')->first();
        if (! $teacher) {
            return response()->json(['ok' => false, 'steps' => [['name' => 'پیدا کردنِ معلم', 'ok' => false, 'error' => 'هیچ معلمی در سامانه نیست.']]]);
        }
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();
        $steps = [];
        $run = function (string $name, callable $fn) use (&$steps) {
            try {
                $r = $fn();
                $steps[] = ['name' => $name, 'ok' => true, 'note' => is_string($r) ? $r : null];

                return true;
            } catch (\Throwable $e) {
                $steps[] = ['name' => $name, 'ok' => false, 'error' => FriendlySaveErrors::explainError($e), 'raw' => Str::limit($e->getMessage(), 700), 'at' => basename($e->getFile()) . ':' . $e->getLine()];

                return false;
            }
        };

        $ai = [
            'type' => 'mc', 'prompt' => 'آزمایشِ سلامت: کدام عدد بر ۳ بخش‌پذیر است؟ 🍎', 'points' => 1,
            'choices' => [['value' => '۹', 'correct' => true], ['value' => '۱۰', 'correct' => false], ['value' => '۱۱', 'correct' => false]],
            'answer' => null, 'explanation' => 'جمعِ ارقامِ ۹ بر ۳ بخش‌پذیر است.', 'difficulty' => 'medium', 'bloom' => 'apply',
            'topic' => 'بخش‌پذیری', 'goal' => 'آزمایشِ سلامتِ سیستم', 'source' => 'ai', 'hint' => 'جمعِ ارقام را ببین',
        ];

        DB::beginTransaction();
        try {
            $exam = null;
            $run('ساختِ آزمونِ هوشمند', function () use (&$exam, $teacher) {
                $exam = SmartExam::create([
                    'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'title' => 'آزمایشِ سلامتِ سیستم',
                    'level' => 'ابتدایی', 'grade' => 'چهارم', 'subject' => 'ریاضی', 'book' => 'ریاضی', 'chapter_id' => DB::table('curriculum_chapters')->value('id'),
                    'chapter' => 'فصلِ آزمایشی', 'topic' => 'بخش‌پذیری', 'goal' => 'آزمایش', 'kind' => 'practice', 'status' => 'published',
                    'adaptive' => false, 'rules' => ['duration' => 20], 'opens_at' => null, 'closes_at' => now()->addDay(),
                ]);
            });
            if ($exam) {
                $run('ذخیره‌ی سؤالِ آزمون (مثلِ خروجیِ هوش مصنوعی)', fn () => $exam->questions()->create([
                    'type' => 'mc', 'prompt' => $ai['prompt'], 'choices' => $ai['choices'], 'answer' => null, 'explanation' => $ai['explanation'],
                    'difficulty' => 'medium', 'bloom' => 'apply', 'points' => 1, 'topic' => $ai['topic'], 'goal' => $ai['goal'], 'source' => 'ai', 'sort' => 0,
                ]));
                $run('ثبتِ سؤال در بانکِ سؤال', fn () => \App\Support\BankAccess::autosave($teacher, $ai, ['level' => 'ابتدایی', 'grade' => 'چهارم', 'subject' => 'ریاضی', 'topic' => 'بخش‌پذیری']) ? 'ثبت شد' : 'ثبت نشد (بی‌خطر)');
                if ($classroom) {
                    $run('هدف‌گذاری برای کلاس', fn () => $exam->targets()->create(['classroom_id' => $classroom->id]));
                }
                $run('محاسبه‌ی مخاطبان و ساختِ اعلان', function () use ($exam) {
                    $ids = \App\Support\ActivityNotifier::examAudience($exam);
                    $ann = \App\Models\Announcement::create([
                        'school_id' => $exam->school_id, 'sender_id' => $exam->teacher_id, 'title' => 'آزمایش', 'body' => 'آزمایش 🧪',
                        'audience' => 'personal', 'link' => \App\Support\ActivityNotifier::examLink($exam->id),
                    ]);
                    $ann->recipients()->sync(array_slice($ids, 0, 3));

                    return \App\Support\Jalali::fa((string) count($ids)) . ' مخاطب';
                });
                $run('صفحه‌ی فهرستِ آزمون‌های معلم', fn () => SmartExam::where('teacher_id', $teacher->id)->withCount('questions')->with('targets')->latest()->limit(5)->get()->count() . ' آزمون');
            }

            $game = null;
            $run('ساختِ بازی', function () use (&$game, $teacher) {
                $game = EduGame::create([
                    'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'template_key' => DB::table('game_templates')->value('key'),
                    'title' => 'آزمایشِ سلامتِ سیستم', 'level' => 'ابتدایی', 'grade' => 'چهارم', 'subject' => 'ریاضی', 'chapter_id' => DB::table('curriculum_chapters')->value('id'),
                    'chapter' => 'فصل', 'topic' => 'بخش‌پذیری', 'goal' => 'آزمایش', 'difficulty' => 'medium', 'status' => 'published', 'rules' => [],
                ]);
            });
            if ($game) {
                $run('ذخیره‌ی سؤالِ بازی', fn () => \App\Models\EduGameQuestion::create([
                    'edu_game_id' => $game->id, 'type' => 'mc', 'prompt' => $ai['prompt'], 'choices' => $ai['choices'], 'hint1' => $ai['hint'],
                    'explanation' => $ai['explanation'], 'points' => 10, 'difficulty' => 'medium', 'bloom' => 'apply', 'topic' => $ai['topic'], 'source' => 'ai', 'sort' => 0,
                ]));
            }
        } finally {
            DB::rollBack(); // هیچ داده‌ای باقی نمی‌ماند
        }

        return response()->json(['ok' => collect($steps)->every(fn ($s) => $s['ok']), 'steps' => $steps, 'teacher' => $teacher->name]);
    }
}
