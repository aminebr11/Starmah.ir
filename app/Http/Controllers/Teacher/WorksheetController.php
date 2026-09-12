<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Worksheet;
use App\Services\SmartExamAiService;
use App\Services\WorksheetArtService;
use App\Services\WorksheetImageService;
use App\Services\WorksheetSheetService;
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
    use \App\Http\Controllers\Concerns\StoresUploads;

    public function __construct(private WorksheetSheetService $sheets)
    {
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
            'image' => $w->image_path ? Storage::disk('public')->url($w->image_path) : null,
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
            'themes'  => $this->sheets->themeList(),
        ]);
    }

    public function create(Request $request): Response
    {
        $teacher = $request->user();
        $img = app(WorksheetImageService::class);

        return Inertia::render('Teacher/WorksheetCreate', [
            'themes' => $this->sheets->themeList(),
            'curriculum' => BankAccess::curriculumTree(),
            'classrooms' => Classroom::where('teacher_id', $teacher->id)->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            // وضعیتِ صادقانه‌ی تصویرساز: کدام موتور، و اگر AI نیست چرا
            'image' => $img->status(),
            'imageAi' => $img->isAi(),   // سازگاری با نسخه‌های قدیمی‌ترِ صفحه
        ]);
    }

    /**
     * پیش‌نمایشِ زنده‌ی تصویرِ تمِ کاربرگ — با موتورِ محلی، پس بی‌هزینه و آنی.
     * معلم پیش از ذخیره می‌بیند تصویر چه شکلی می‌شود.
     */
    public function artPreview(Request $request, WorksheetArtService $art): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:180'],
            'subject' => ['nullable', 'string', 'max:120'],
            'theme' => ['nullable', 'string', 'max:30'],
            'spec' => ['nullable', 'string', 'max:2000'],
        ]);
        $themeKey = $this->sheets->themeKey($data['theme'] ?? null);
        $svg = $art->svg($data['title'] ?? 'کاربرگ', $data['subject'] ?? '', $themeKey, $data['spec'] ?? '');

        return response()->json([
            'ok' => true,
            'svg' => 'data:image/svg+xml;base64,' . base64_encode($svg),
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
        $theme = $this->sheets->theme($data['theme'] ?? null);

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
            'mode' => ['nullable', 'in:manual,upload,ai'],
            'level' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:120'],
            'lesson_no' => ['nullable', 'string', 'max:40'],
            'theme' => ['required', 'string', 'max:30'],
            'spec' => ['nullable', 'string', 'max:2000'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'publish' => ['nullable', 'boolean'],
            'gen_image' => ['nullable', 'boolean'],
            'image_mode' => ['nullable', 'in:ai,local,none'],
            'save_to_bank' => ['nullable', 'boolean'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'file' => ['nullable', 'file', 'max:20480'],
            'questions' => ['nullable', 'array'],
            // هر زیرکلید باید قاعده داشته باشد، وگرنه validate() آن را دور می‌ریزد
            // و گزینه‌های سؤالِ چهارگزینه‌ای خاموش گم می‌شدند.
            'questions.*.prompt' => ['nullable', 'string'],
            'questions.*.type' => ['nullable', 'in:mc,tf,desc,blank'],
            'questions.*.answer' => ['nullable'],
            'questions.*.explanation' => ['nullable', 'string', 'max:600'],
            'questions.*.difficulty' => ['nullable', 'in:easy,medium,hard'],
            'questions.*.topic' => ['nullable', 'string', 'max:160'],
            'questions.*.goal' => ['nullable', 'string', 'max:300'],
            'questions.*.choices' => ['nullable', 'array', 'max:8'],
            'questions.*.choices.*.value' => ['nullable', 'string', 'max:400'],
            'questions.*.choices.*.correct' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $mode = $data['mode'] ?? 'manual';
        $themeKey = $this->sheets->themeKey($data['theme']);
        $scope = $user->hasRole(Roles::SUPER_ADMIN) ? 'global' : 'school';
        $questions = array_values(array_filter($data['questions'] ?? [], fn ($q) => trim((string) ($q['prompt'] ?? '')) !== ''));

        // اعتبارسنجیِ وابسته به حالت
        if ($mode === 'upload') {
            if (! $request->hasFile('file')) {
                return back()->withErrors(['file' => 'در حالتِ «بارگذاری» باید فایلِ کاربرگ را انتخاب کنید.']);
            }
            if (! $this->extensionAllowed($request->file('file'), ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'])) {
                return back()->withErrors(['file' => 'فرمتِ مجاز: PDF، Word یا تصویر (jpg/png/webp).']);
            }
        } elseif (empty($questions)) {
            return back()->withErrors(['questions' => 'حداقل یک سؤال لازم است (یا از حالتِ «بارگذاری فایل» استفاده کنید).']);
        }

        // فایلِ بارگذاری‌شده (حالتِ upload)
        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $this->storeUpload($request->file('file'), 'worksheets');
        }

        // ── تصویرِ کاربرگ ───────────────────────────────────────────────
        // ai    → موتورِ هوش مصنوعی (و در صورتِ خطا، برگشت به موتورِ محلی)
        // local → موتورِ برداریِ محلی؛ آنی، رایگان، بدونِ اینترنت
        // none  → بدونِ تصویر
        $imageMode = $data['image_mode'] ?? (! empty($data['gen_image']) ? 'ai' : 'none');
        $imagePath = null;
        $imageNote = null;
        if ($imageMode !== 'none') {
            if ($imageMode === 'local') {
                $imagePath = app(WorksheetArtService::class)
                    ->generate($data['title'], $data['subject'] ?? '', $themeKey, $data['spec'] ?? '');
                $imageNote = $imagePath ? 'تصویرِ تمِ کاربرگ ساخته شد 🎨' : 'ساختِ تصویر ناموفق بود.';
            } else {
                $r = $imageAi->make(
                    $data['title'], $data['subject'] ?? '',
                    $this->sheets->theme($themeKey)['label'] ?? '', $data['spec'] ?? '', $themeKey
                );
                $imagePath = $r['path'];
                // match(true) با === می‌سنجد، پس شرط‌ها باید واقعاً bool باشند
                $imageNote = match (true) {
                    $r['path'] !== null && $r['ai'] => 'تصویر با هوش مصنوعی ساخته شد 🤖🎨',
                    $r['path'] !== null => 'تصویرِ تمِ کاربرگ ساخته شد 🎨' . ($r['error'] ? ' — ' . $r['error'] : ''),
                    default => 'تصویر ساخته نشد' . ($r['error'] ? ' — ' . $r['error'] : '') . '.',
                };
            }
        }

        // حالا که تصویر آماده است، کاربرگ را با همان تصویر به‌عنوانِ سرلوحه
        // رندر می‌کنیم تا سؤال‌ها **داخلِ خودِ کاربرگ** باشند، نه زیرِ یک عکسِ جدا.
        $html = $mode === 'upload' ? null : $this->sheets->renderWorksheet(
            $data['title'], $data['subject'] ?? '', $data['grade'] ?? '', $themeKey, $questions,
            $imagePath ? Storage::disk('public')->url($imagePath) : null
        );

        $publish = (bool) ($data['publish'] ?? false);
        $worksheet = Worksheet::create([
            'school_id' => $user->school_id, 'teacher_id' => $user->id,
            'classroom_id' => $data['classroom_id'] ?? null,
            'scope' => $scope, 'level' => $data['level'] ?? null,
            'title' => $data['title'], 'subject' => $data['subject'] ?? null,
            'lesson_no' => $data['lesson_no'] ?? null, 'grade' => $data['grade'] ?? null,
            'theme' => $themeKey, 'mode' => $mode, 'spec' => $data['spec'] ?? null,
            'questions' => array_map(fn ($q) => [
                'prompt' => (string) ($q['prompt'] ?? ''), 'type' => $q['type'] ?? 'mc',
                'choices' => array_values($q['choices'] ?? []), 'answer' => $q['answer'] ?? null,
                'explanation' => $q['explanation'] ?? null,
                'difficulty' => $q['difficulty'] ?? ($data['difficulty'] ?? 'medium'),
            ], $questions),
            'render_html' => $html, 'image_path' => $imagePath, 'file_path' => $filePath,
            'is_published' => $publish, 'published_at' => $publish ? now() : null,
        ]);

        // ── ثبتِ سؤال‌ها در بانکِ سؤال ───────────────────────────────────
        // تا پیش از این، سؤالی که در کاربرگ‌ساز نوشته یا با AI تولید می‌شد
        // فقط داخلِ همان کاربرگ می‌ماند و در بانک پیدا نمی‌شد — برخلافِ
        // آزمون‌ساز و بازی‌ساز که همین کار را انجام می‌دهند.
        $banked = 0;
        if ($mode !== 'upload' && ($data['save_to_bank'] ?? true)) {
            $banked = $this->saveQuestionsToBank($user, $questions, [
                'level' => $data['level'] ?? null, 'grade' => $data['grade'] ?? null,
                'subject' => $data['subject'] ?? null, 'lesson_no' => $data['lesson_no'] ?? null,
                'topic' => $data['spec'] ?? null, 'source' => 'worksheet',
                'difficulty' => $data['difficulty'] ?? 'medium',
            ]);
        }

        if ($publish) {
            $this->notifyStudents($worksheet);
        }

        $flash = 'کاربرگ ساخته و در بانکِ کاربرگ‌ها ذخیره شد ✅';
        if ($banked > 0) {
            $flash .= ' · ' . $banked . ' سؤال به بانکِ سؤال هم اضافه شد 🗂️';
        }
        if ($imageNote) {
            $flash .= ' · ' . $imageNote;
        }
        if ($publish) {
            $flash .= ' · برای دانش‌آموزان منتشر شد 📣';
        }

        return redirect()->route('teacher.worksheets')->with('flash', $flash);
    }

    /**
     * ثبتِ سؤال‌های کاربرگ در بانکِ سؤالِ معلم.
     * BankAccess::autosave خودش سؤالِ تکراری را رد می‌کند، پس شمارش را
     * از تفاضلِ تعدادِ بانک برمی‌داریم تا عددِ گزارش‌شده واقعی باشد.
     */
    private function saveQuestionsToBank($teacher, array $questions, array $meta): int
    {
        $added = 0;
        foreach ($questions as $q) {
            $type = $q['type'] ?? 'mc';
            $choices = $q['choices'] ?? [];
            // سؤالِ چندگزینه‌ای بدونِ پاسخِ درست به دردِ بانک نمی‌خورد
            if (in_array($type, ['mc', 'tf'], true) && ! collect($choices)->contains(fn ($c) => ! empty($c['correct']))) {
                continue;
            }
            $before = \App\Models\SmartQuestionBank::withoutGlobalScopes()
                ->where('teacher_id', $teacher->id)->where('prompt', trim((string) ($q['prompt'] ?? '')))->exists();
            if ($before) {
                continue;
            }
            BankAccess::autosave($teacher, [
                'prompt' => $q['prompt'] ?? '', 'type' => $type,
                'choices' => $choices, 'answer' => $q['answer'] ?? null,
                'explanation' => $q['explanation'] ?? null,
                'difficulty' => $q['difficulty'] ?? ($meta['difficulty'] ?? 'medium'),
                'source' => 'worksheet',
            ], $meta);
            $added++;
        }

        return $added;
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
        try {
            $classroom = $worksheet->classroom_id ? Classroom::find($worksheet->classroom_id) : null;
            $ids = $classroom ? $classroom->students()->pluck('users.id')->all() : [];
            if (! $ids) {
                return;
            }
            $payload = [
                'school_id' => $worksheet->school_id, 'sender_id' => $worksheet->teacher_id,
                'title' => '🎨 کاربرگ جدید — ' . $worksheet->title,
                'audience' => 'personal',
                'body' => "یک کاربرگ جدید برای شما منتشر شد: «{$worksheet->title}».\nآن را از بخشِ «مطالب و محتوا» ببینید، چاپ/دانلود کنید، و پس از پر کردن برای معلم بفرستید.",
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('announcements', 'link')) {
                $payload['link'] = '/worksheets/' . $worksheet->id;
            }
            $ann = Announcement::create($payload);
            $ann->recipients()->sync($ids);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('worksheet notify failed: ' . $e->getMessage());
        }
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
                'html' => $this->sheets->sheet($worksheet),
                // تصویر داخلِ خودِ برگه است؛ این فقط برای حالتی است که برگه‌ای نداریم
                'image' => $worksheet->image_path ? Storage::disk('public')->url($worksheet->image_path) : null,
                'file' => $worksheet->file_path ? Storage::disk('public')->url($worksheet->file_path) : null,
                'subject' => $worksheet->subject, 'grade' => $worksheet->grade,
                'lesson_no' => $worksheet->lesson_no, 'mode' => $worksheet->mode,
                'theme' => $worksheet->theme,
                'questions' => is_array($worksheet->questions) ? count($worksheet->questions) : 0,
                // داده‌ی خامِ سؤال‌ها برای ویرایشِ درجا در پیش‌نمایش
                'items' => is_array($worksheet->questions) ? array_values($worksheet->questions) : [],
                'published' => (bool) $worksheet->is_published,
                'classroom_id' => $worksheet->classroom_id,
                'date' => Jalali::format($worksheet->created_at),
            ],
            'classrooms' => Classroom::where('teacher_id', $user->id)->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'canEdit' => WorksheetAccess::canEdit($user, $worksheet),
            'themes' => $this->sheets->themeList(),
            'submissions' => $submissions,
        ]);
    }

    /**
     * ویرایشِ کاربرگ از بانک/پیش‌نمایش.
     *
     * تا پیش از این کاربرگ پس از ساخته‌شدن قفل بود و معلم برای اصلاحِ یک
     * غلطِ تایپی مجبور بود کاربرگ را دوباره از صفر بسازد. اینجا مشخصات و
     * سؤال‌ها به‌روز می‌شوند و برگه دوباره رندر می‌شود (تصویرِ موجود حفظ
     * می‌شود تا هزینه‌ی تولیدِ دوباره ندهیم).
     */
    public function update(Request $request, Worksheet $worksheet): RedirectResponse
    {
        abort_unless(WorksheetAccess::canEdit($request->user(), $worksheet), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:60'],
            'lesson_no' => ['nullable', 'string', 'max:30'],
            'theme' => ['nullable', 'string', 'max:40'],
            'questions' => ['nullable', 'array'],
            'questions.*.prompt' => ['nullable', 'string'],
            'questions.*.type' => ['nullable', 'in:mc,tf,desc,blank'],
            'questions.*.answer' => ['nullable'],
            'questions.*.explanation' => ['nullable', 'string', 'max:600'],
            'questions.*.difficulty' => ['nullable', 'in:easy,medium,hard'],
            'questions.*.choices' => ['nullable', 'array', 'max:8'],
            'questions.*.choices.*.value' => ['nullable', 'string', 'max:400'],
            'questions.*.choices.*.correct' => ['nullable', 'boolean'],
        ]);

        $questions = array_values(array_filter($data['questions'] ?? [], fn ($q) => trim((string) ($q['prompt'] ?? '')) !== ''));
        if (($worksheet->mode ?? 'manual') !== 'upload' && empty($questions)) {
            return back()->withErrors(['questions' => 'کاربرگ باید دستِ‌کم یک سؤال داشته باشد.']);
        }

        $themeKey = $this->sheets->themeKey($data['theme'] ?? $worksheet->theme);
        $normalized = array_map(fn ($q) => [
            'prompt' => (string) ($q['prompt'] ?? ''), 'type' => $q['type'] ?? 'mc',
            'choices' => array_values($q['choices'] ?? []), 'answer' => $q['answer'] ?? null,
            'explanation' => $q['explanation'] ?? null,
            'difficulty' => $q['difficulty'] ?? 'medium',
        ], $questions);

        $worksheet->update([
            'title' => $data['title'],
            'subject' => $data['subject'] ?? $worksheet->subject,
            'grade' => $data['grade'] ?? $worksheet->grade,
            'lesson_no' => $data['lesson_no'] ?? $worksheet->lesson_no,
            'theme' => $themeKey,
            'questions' => $normalized,
            'render_html' => $normalized ? $this->sheets->renderWorksheet(
                $data['title'], $data['subject'] ?? '', $data['grade'] ?? '', $themeKey, $normalized,
                $worksheet->image_path ? Storage::disk('public')->url($worksheet->image_path) : null
            ) : $worksheet->render_html,
        ]);

        return back()->with('flash', 'کاربرگ ویرایش شد ✅');
    }

    /** پنهان‌کردنِ کاربرگ از دیدِ دانش‌آموز، بدونِ حذفِ آن از بانک. */
    public function unpublish(Request $request, Worksheet $worksheet): RedirectResponse
    {
        abort_unless(WorksheetAccess::canEdit($request->user(), $worksheet), 403);
        $worksheet->update(['is_published' => false]);

        return back()->with('flash', 'کاربرگ از دیدِ دانش‌آموزان پنهان شد 🙈');
    }

    public function destroy(Request $request, Worksheet $worksheet): RedirectResponse
    {
        abort_unless(WorksheetAccess::canEdit($request->user(), $worksheet), 403);
        foreach ([$worksheet->image_path, $worksheet->file_path] as $f) {
            if ($f) {
                Storage::disk('public')->delete($f);
            }
        }
        $worksheet->delete();

        return back()->with('flash', 'کاربرگ حذف شد ✅');
    }

    /** ساختِ تصویرِ جذابِ HTML/SVG از کاربرگ (پیش‌فرض، وقتی تصویرِ AI نداریم). قابل چاپ. */
}
