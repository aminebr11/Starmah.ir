<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Worksheet;
use App\Services\SmartExamAiService;
use App\Services\WorksheetImageService;
use App\Support\BankAccess;
use App\Support\Jalali;
use App\Support\Roles;
use App\Support\WorksheetAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * کاربرگ‌سازِ هوشمند + بانک کاربرگ‌ها (دقیقاً مثل بانک سؤالات: مقطع→کلاس→درس→شماره درس).
 * معلم مشخصات + تم می‌دهد → AI سؤال پیشنهاد و (در صورت تنظیمِ ادمین) تصویر تولید می‌کند →
 * کاربرگ در بانک ذخیره و پس از انتشار برای کلاس، به دانش‌آموز اعلان می‌شود.
 */
class WorksheetController extends Controller
{
    private const THEMES = [
        'stars'   => ['label' => 'ستاره ماه 🌙', 'flavor' => 'ماجراجویی فضایی و ستاره‌ها', 'p1' => '#3d7bf0', 'p2' => '#7a5cf0', 'bg' => '#0d1b3e', 'ic' => ['⭐', '🌙', '✨', '☄️', '🚀']],
        'pitch'   => ['label' => 'شلیک آتشین ⚽', 'flavor' => 'فوتبال و ورزش', 'p1' => '#2bb673', 'p2' => '#0f9d58', 'bg' => '#0b3d2e', 'ic' => ['⚽', '🥅', '🏆', '👟', '🔥']],
        'blocks'  => ['label' => 'کریپر ماینکرفت 🟩', 'flavor' => 'دنیای مکعبی و ساخت‌وساز', 'p1' => '#4caf50', 'p2' => '#2e7d32', 'bg' => '#1b3a1e', 'ic' => ['🟩', '⛏️', '💎', '🧱', '🌳']],
        'speed'   => ['label' => 'سوپر اسپید 🏎️', 'flavor' => 'مسابقه و سرعت', 'p1' => '#e8862e', 'p2' => '#d64545', 'bg' => '#3a1c0b', 'ic' => ['🏎️', '🏁', '💨', '🔥', '🏆']],
        'classic' => ['label' => 'کلاسیک 📘', 'flavor' => '', 'p1' => '#3d7bf0', 'p2' => '#2555c0', 'bg' => '#16264f', 'ic' => ['📘', '✏️', '📐', '🍎', '⭐']],
    ];

    private function themeList(): array
    {
        return collect(self::THEMES)->map(fn ($t, $k) => ['key' => $k, 'label' => $t['label']])->values()->all();
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $rows = WorksheetAccess::visibleQuery($user)->withCount('submissions')->latest()->get();

        $items = $rows->map(fn (Worksheet $w) => [
            'id' => $w->id, 'title' => $w->title,
            'level' => BankAccess::levelOf($w->level, $w->grade),
            'subject' => $w->subject, 'grade' => $w->grade, 'lesson_no' => $w->lesson_no,
            'theme' => $w->theme, 'scope' => $w->scope,
            'count' => is_array($w->questions) ? count($w->questions) : 0,
            'published' => (bool) $w->is_published,
            'submissions' => $w->submissions_count,
            'has_image' => (bool) $w->image_path,
            'can_edit' => WorksheetAccess::canEdit($user, $w),
            'date' => Jalali::format($w->created_at),
        ]);

        // گروه‌بندیِ مقطع→کلاس→درس→شماره درس
        $grouped = $items->groupBy(fn ($w) => $w['level'] ?: 'دسته‌بندی‌نشده')
            ->map(fn ($byL, $level) => [
                'level' => $level, 'count' => $byL->count(),
                'grades' => $byL->groupBy(fn ($w) => $w['grade'] ?: '—')
                    ->map(fn ($byG, $grade) => [
                        'grade' => $grade,
                        'subjects' => $byG->groupBy(fn ($w) => $w['subject'] ?: 'عمومی')
                            ->map(fn ($byS, $subj) => [
                                'subject' => $subj,
                                'lessons' => $byS->groupBy(fn ($w) => $w['lesson_no'] ?: '—')
                                    ->map(fn ($its, $ln) => ['lesson_no' => $ln, 'items' => $its->values()])->values(),
                            ])->values(),
                    ])->values(),
            ])->values();

        return Inertia::render('Teacher/Worksheets', [
            'grouped' => $grouped,
            'themes'  => $this->themeList(),
        ]);
    }

    public function create(Request $request): Response
    {
        $teacher = $request->user();
        return Inertia::render('Teacher/WorksheetCreate', [
            'themes' => $this->themeList(),
            'curriculum' => BankAccess::curriculumTree(),
            'classrooms' => Classroom::where('teacher_id', $teacher->id)->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'imageAi' => app(WorksheetImageService::class)->enabled(),
        ]);
    }

    /** پیشنهادِ سؤال با هوش مصنوعی. */
    public function ai(Request $request, SmartExamAiService $ai): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:120'], 'grade' => ['nullable', 'string', 'max:60'],
            'topic' => ['nullable', 'string', 'max:160'], 'goal' => ['nullable', 'string', 'max:300'],
            'theme' => ['nullable', 'string', 'max:30'], 'count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'], 'type' => ['nullable', 'in:mc,tf,desc,blank'],
            'sample' => ['nullable', 'boolean'],
        ]);
        $theme = self::THEMES[$data['theme'] ?? 'classic'] ?? self::THEMES['classic'];

        return response()->json($ai->generate([
            'subject' => $data['subject'] ?? '', 'topic' => $data['topic'] ?? ($data['subject'] ?? ''),
            'grade' => $data['grade'] ?? 'چهارم', 'goal' => $data['goal'] ?? '',
            'count' => $data['count'] ?? 6, 'difficulty' => $data['difficulty'] ?? 'medium',
            'type' => $data['type'] ?? 'mc', 'flavor' => $theme['flavor'],
            'sample' => (bool) ($data['sample'] ?? false),
            'school_id' => $request->user()->school_id, 'teacher_id' => $request->user()->id,
        ]));
    }

    public function store(Request $request, WorksheetImageService $imageAi): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'level' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:120'],
            'lesson_no' => ['nullable', 'string', 'max:40'],
            'theme' => ['required', 'string', 'max:30'],
            'spec' => ['nullable', 'string', 'max:2000'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'publish' => ['nullable', 'boolean'],
            'gen_image' => ['nullable', 'boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.prompt' => ['required', 'string'],
        ]);

        $user = $request->user();
        $themeKey = array_key_exists($data['theme'], self::THEMES) ? $data['theme'] : 'classic';
        $scope = $user->hasRole(Roles::SUPER_ADMIN) ? 'global' : 'school';

        $html = $this->renderWorksheet($data['title'], $data['subject'] ?? '', $data['grade'] ?? '', $themeKey, $data['questions']);

        // تولیدِ تصویر با AI (اختیاری — اگر ادمین فعال کرده و معلم خواسته)
        $imagePath = null;
        if (! empty($data['gen_image'])) {
            $imagePath = $imageAi->generate($data['title'], $data['subject'] ?? '', self::THEMES[$themeKey]['label'] ?? '', $data['spec'] ?? '');
        }

        $publish = (bool) ($data['publish'] ?? false);
        $worksheet = Worksheet::create([
            'school_id' => $user->school_id, 'teacher_id' => $user->id,
            'classroom_id' => $data['classroom_id'] ?? null,
            'scope' => $scope, 'level' => $data['level'] ?? null,
            'title' => $data['title'], 'subject' => $data['subject'] ?? null,
            'lesson_no' => $data['lesson_no'] ?? null, 'grade' => $data['grade'] ?? null,
            'theme' => $themeKey, 'spec' => $data['spec'] ?? null,
            'questions' => array_map(fn ($q) => [
                'prompt' => (string) ($q['prompt'] ?? ''), 'type' => $q['type'] ?? 'mc',
                'choices' => $q['choices'] ?? [], 'answer' => $q['answer'] ?? null,
            ], $data['questions']),
            'render_html' => $html, 'image_path' => $imagePath,
            'is_published' => $publish, 'published_at' => $publish ? now() : null,
        ]);

        if ($publish) {
            $this->notifyStudents($worksheet);
        }

        return redirect()->route('teacher.worksheets')->with('flash',
            'کاربرگ ساخته و در بانک ذخیره شد ✅' . ($publish ? ' و برای دانش‌آموزان منتشر شد.' : ''));
    }

    /** انتشارِ کاربرگ برای کلاس + اعلان به دانش‌آموزان. */
    public function publish(Request $request, Worksheet $worksheet): RedirectResponse
    {
        abort_unless(WorksheetAccess::canEdit($request->user(), $worksheet), 403);
        $data = $request->validate(['classroom_id' => ['nullable', 'integer', 'exists:classrooms,id']]);

        $worksheet->update([
            'classroom_id' => $data['classroom_id'] ?? $worksheet->classroom_id,
            'is_published' => true, 'published_at' => now(),
        ]);
        $this->notifyStudents($worksheet);

        return back()->with('flash', 'کاربرگ برای دانش‌آموزان منتشر شد ✅');
    }

    private function notifyStudents(Worksheet $worksheet): void
    {
        $classroom = $worksheet->classroom_id ? Classroom::find($worksheet->classroom_id) : null;
        $ids = $classroom ? $classroom->students()->pluck('users.id')->all() : [];
        if (! $ids) {
            return;
        }
        $ann = Announcement::create([
            'school_id' => $worksheet->school_id, 'sender_id' => $worksheet->teacher_id,
            'title' => '🎨 کاربرگ جدید — ' . $worksheet->title,
            'audience' => 'personal',
            'body' => "یک کاربرگ جدید برای شما منتشر شد: «{$worksheet->title}».\nآن را از بخشِ «تکالیف» ببینید، چاپ کنید، و پس از پر کردن برای معلم بفرستید.",
        ]);
        $ann->recipients()->sync($ids);
    }

    public function show(Request $request, Worksheet $worksheet): Response
    {
        $user = $request->user();
        abort_unless(WorksheetAccess::visibleQuery($user)->whereKey($worksheet->id)->exists(), 403);

        $submissions = [];
        if (WorksheetAccess::canEdit($user, $worksheet)) {
            $submissions = $worksheet->submissions()->with('student:id,name')->latest('submitted_at')->get()
                ->map(fn ($s) => [
                    'id' => $s->id, 'student' => $s->student?->name,
                    'url' => Storage::disk('public')->url($s->file_path),
                    'note' => $s->note, 'date' => Jalali::format($s->submitted_at ?? $s->created_at, true),
                ]);
        }

        return Inertia::render('Teacher/WorksheetView', [
            'worksheet' => [
                'id' => $worksheet->id, 'title' => $worksheet->title,
                'html' => $worksheet->render_html,
                'image' => $worksheet->image_path ? Storage::disk('public')->url($worksheet->image_path) : null,
                'published' => (bool) $worksheet->is_published,
                'classroom_id' => $worksheet->classroom_id,
                'date' => Jalali::format($worksheet->created_at),
            ],
            'classrooms' => Classroom::where('teacher_id', $user->id)->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'canEdit' => WorksheetAccess::canEdit($user, $worksheet),
            'submissions' => $submissions,
        ]);
    }

    public function destroy(Request $request, Worksheet $worksheet): RedirectResponse
    {
        abort_unless(WorksheetAccess::canEdit($request->user(), $worksheet), 403);
        if ($worksheet->image_path) {
            Storage::disk('public')->delete($worksheet->image_path);
        }
        $worksheet->delete();

        return back()->with('flash', 'کاربرگ حذف شد ✅');
    }

    /** ساختِ تصویرِ جذابِ HTML/SVG از کاربرگ (پیش‌فرض، وقتی تصویرِ AI نداریم). قابل چاپ. */
    private function renderWorksheet(string $title, string $subject, string $grade, string $themeKey, array $questions): string
    {
        $t = self::THEMES[$themeKey] ?? self::THEMES['classic'];
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $fa = fn ($n) => strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        $floats = '';
        $positions = [[6, 8], [90, 12], [12, 88], [88, 86], [50, 5], [50, 94], [4, 50], [95, 48]];
        foreach ($positions as $i => [$x, $y]) {
            $ic = $t['ic'][$i % count($t['ic'])];
            $floats .= "<span style=\"position:absolute;left:{$x}%;top:{$y}%;font-size:26px;opacity:.28;transform:translate(-50%,-50%)\">{$ic}</span>";
        }

        $rows = '';
        foreach (array_values($questions) as $idx => $q) {
            $n = $fa($idx + 1);
            $prompt = $e($q['prompt'] ?? '');
            $type = $q['type'] ?? 'mc';
            $body = '';
            if (in_array($type, ['mc', 'tf'], true) && ! empty($q['choices'])) {
                $body .= '<div style="display:flex;flex-wrap:wrap;gap:10px 22px;margin-top:8px">';
                foreach ($q['choices'] as $ci => $c) {
                    $lbl = $e($c['value'] ?? '');
                    $letters = ['الف', 'ب', 'ج', 'د'];
                    $L = $letters[$ci] ?? ($ci + 1);
                    $body .= "<span style=\"display:inline-flex;align-items:center;gap:7px;font-size:14px\"><span style=\"display:inline-flex;width:22px;height:22px;border:2px solid {$t['p1']};border-radius:50%\"></span><b style=\"color:{$t['p2']}\">{$L})</b> {$lbl}</span>";
                }
                $body .= '</div>';
            } else {
                $body .= '<div style="margin-top:10px">'
                    . '<div style="border-bottom:2px dotted #c9d3e6;height:22px"></div>'
                    . '<div style="border-bottom:2px dotted #c9d3e6;height:22px;margin-top:6px"></div>'
                    . '</div>';
            }
            $rows .= "<div style=\"background:#fff;border:2px solid #eef2fa;border-inline-start:6px solid {$t['p1']};border-radius:14px;padding:14px 16px;margin-bottom:14px;box-shadow:0 4px 14px -10px rgba(0,0,0,.3)\">"
                . "<div style=\"display:flex;gap:10px;align-items:flex-start\">"
                . "<span style=\"flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:10px;background:linear-gradient(135deg,{$t['p1']},{$t['p2']});color:#fff;font-weight:900\">{$n}</span>"
                . "<div style=\"flex:1\"><div style=\"font-weight:800;font-size:15px;color:#1b2742;line-height:1.9\">{$prompt}</div>{$body}</div></div></div>";
        }

        $subjectLine = trim($subject . ($grade ? ' — پایه‌ی ' . $grade : ''));

        return '<div style="font-family:Vazirmatn,Tahoma,sans-serif;direction:rtl;max-width:820px;margin:0 auto;background:#f4f7fd;border-radius:22px;overflow:hidden;box-shadow:0 20px 50px -24px rgba(0,0,0,.5)">'
            . "<div style=\"position:relative;background:linear-gradient(135deg,{$t['p1']},{$t['p2']});color:#fff;padding:26px 24px;overflow:hidden\">"
            . $floats
            . "<div style=\"position:relative\"><div style=\"font-size:12px;opacity:.85;letter-spacing:1px\">کاربرگ آموزشی · ستاره ماه</div>"
            . "<div style=\"font-size:24px;font-weight:900;margin-top:4px\">" . $e($title) . '</div>'
            . ($subjectLine ? "<div style=\"font-size:14px;opacity:.92;margin-top:4px\">" . $e($subjectLine) . '</div>' : '')
            . '<div style="display:flex;gap:20px;margin-top:14px;font-size:13px;opacity:.95">'
            . '<span>👤 نام: ....................</span><span>🏫 کلاس: ..............</span><span>📅 تاریخ: ..............</span>'
            . '</div></div></div>'
            . '<div style="padding:22px 20px">' . $rows . '</div>'
            . "<div style=\"text-align:center;padding:14px;color:{$t['p2']};font-weight:800;font-size:13px\">🌟 آفرین! تو می‌تونی 🌟</div>"
            . '</div>';
    }
}
