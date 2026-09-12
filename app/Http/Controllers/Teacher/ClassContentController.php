<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassContent;
use App\Models\Classroom;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * محتوای کلاس معلم — جزوه/فایل، پادکست، گالری، تکلیف (مطابق وبسایت قبلی).
 */
class ClassContentController extends Controller
{
    use \App\Http\Controllers\Concerns\StoresUploads;

    public function index(Request $request): Response
    {
        $teacher = $request->user();

        $contents = ClassContent::where('teacher_id', $teacher->id)->latest()->get();

        // آمار بازدید/گوش‌دادن — چه کسانی دیدند/گوش دادند
        $views = \App\Models\ContentView::with('student:id,name')
            ->whereIn('class_content_id', $contents->pluck('id'))
            ->get()->groupBy('class_content_id');

        $items = $contents->map(function ($c) use ($views) {
            $vs = $views->get($c->id) ?? collect();
            return [
                'id'    => $c->id,
                'type'  => $c->type,
                'title' => $c->title,
                'description' => $c->description,
                'classroom_id' => $c->classroom_id,
                'due_at_raw' => $c->due_at ? $c->due_at->format('Y-m-d H:i') : null,
                'external_url' => $c->external_url,
                'url'   => $c->file_path ? Storage::disk('public')->url($c->file_path) : $c->external_url,
                'is_file' => (bool) $c->file_path,
                'due_at'  => $c->due_at ? Jalali::format($c->due_at) : null,
                'date'    => Jalali::format($c->created_at),
                'duration' => $c->duration_seconds ? (int) $c->duration_seconds : null,
                'xp_value' => app(\App\Services\ContentProgressService::class)->xpFor($c),
                'xp_reward' => $c->xp_reward,
                'completed_count' => $vs->whereNotNull('completed_at')->count(),
                'viewers' => $vs->map(fn ($v) => [
                    'name'    => $v->student?->name ?? '—',
                    'avatar'  => $v->student?->avatar_url,
                    'seconds' => (int) ($v->verified_seconds ?: $v->seconds),
                    'completed' => $v->completed_at !== null,
                    'xp'      => (int) $v->xp_awarded,
                ])->values(),
                'views_count' => $vs->count(),
            ];
        });

        $classrooms = Classroom::where('teacher_id', $teacher->id)
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);

        // بخشِ پنجم: کاربرگ‌ها (خلاصه برای بایگانی)
        $worksheets = \App\Support\WorksheetAccess::visibleQuery($teacher)
            ->withCount('submissions')->latest()->get()
            ->map(fn ($w) => [
                'id' => $w->id, 'title' => $w->title,
                'subject' => $w->subject, 'grade' => $w->grade, 'lesson_no' => $w->lesson_no,
                'count' => is_array($w->questions) ? count($w->questions) : 0,
                'published' => (bool) $w->is_published,
                'submissions' => $w->submissions_count,
                'has_image' => (bool) $w->image_path,
                'date' => Jalali::format($w->created_at),
            ]);

        return Inertia::render('Teacher/Materials', [
            'items'      => $items->values(),
            'classrooms' => $classrooms->values(),
            'worksheets' => $worksheets->values(),
        ]);
    }

    /**
     * پسوندهای مجاز برای هر نوعِ محتوا.
     *
     * پیش از این هیچ محدودیتی نبود: قاعده‌ی اعتبارسنجی فقط «file» و سقفِ حجم
     * بود. چون پوشه‌ی آپلود را وب‌سرور مستقیم سِرو می‌کند، یک فایلِ .php
     * آپلودشده روی سرور **اجرا** می‌شد. حالا هم اینجا و هم در خودِ
     * StoresUploads جلویش گرفته شده است.
     */
    private const ALLOWED_EXT = [
        'material' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'rtf', 'zip', 'jpg', 'jpeg', 'png', 'webp'],
        'homework' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'rtf', 'zip', 'jpg', 'jpeg', 'png', 'webp'],
        // پادکست حالا صوتی و تصویری است
        'podcast'  => ['mp3', 'm4a', 'aac', 'ogg', 'oga', 'wav', 'opus', 'mp4', 'webm', 'm4v', 'mov'],
        'video'    => ['mp4', 'webm', 'ogv', 'm4v', 'mov'],
        'gallery'  => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    ];

    /** پیامِ خطای خوانا برای پسوندِ نامجاز. */
    private function extError(string $type): string
    {
        $list = implode('، ', self::ALLOWED_EXT[$type] ?? []);

        return "فرمتِ این فایل برای «{$type}» پذیرفته نمی‌شود. فرمت‌های مجاز: {$list}.";
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type'         => 'required|in:material,podcast,video,gallery,homework',
            'title'        => 'required|string|max:150',
            'description'  => 'nullable|string|max:2000',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'external_url' => 'nullable|url|max:500',
            // ویدیو سنگین‌تر است؛ سقف تا ۱۵۰ مگابایت (به .user.ini هم توجه کنید)
            'file'         => 'nullable|file|max:153600',
            'due_at'       => 'nullable|date',
            // مدتِ مدیا را مرورگر هنگامِ انتخابِ فایل تشخیص می‌دهد
            'duration_seconds' => 'nullable|integer|min:1|max:86400',
            // امتیازِ دلخواهِ معلم؛ خالی یعنی محاسبه‌ی خودکار از روی مدت
            'xp_reward'    => 'nullable|integer|min:0|max:100',
        ], [
            'file.max' => 'حجمِ فایل بیش از حد است (سقف ۱۵۰ مگابایت).',
        ]);

        $path = null;
        if ($request->hasFile('file')) {
            if (! $this->extensionSafe($request->file('file'), self::ALLOWED_EXT[$data['type']] ?? [])) {
                return back()->withErrors(['file' => $this->extError($data['type'])]);
            }
            $path = $this->storeUpload($request->file('file'), "class-content/{$data['type']}");
            if ($path === false) {
                return back()->withErrors(['file' => 'ذخیره‌ی فایل روی سرور ممکن نشد.']);
            }
        }

        $content = ClassContent::create([
            'teacher_id'   => $request->user()->id,
            'classroom_id' => $data['classroom_id'] ?? null,
            'type'         => $data['type'],
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'file_path'    => $path,
            'external_url' => $data['external_url'] ?? null,
            'due_at'       => $data['due_at'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'xp_reward'    => $data['xp_reward'] ?? null,
        ]);

        $this->notifyStudents($content);

        return back()->with('flash', 'محتوا اضافه شد و به دانش‌آموزان اطلاع داده شد ✅');
    }

    public function update(Request $request, ClassContent $classContent): RedirectResponse
    {
        abort_unless($classContent->teacher_id === $request->user()->id, 403);

        $data = $request->validate([
            'title'        => 'required|string|max:150',
            'description'  => 'nullable|string|max:2000',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'external_url' => 'nullable|url|max:500',
            'file'         => 'nullable|file|max:153600',
            'due_at'       => 'nullable|date',
            'duration_seconds' => 'nullable|integer|min:1|max:86400',
            'xp_reward'    => 'nullable|integer|min:0|max:100',
        ], [
            'file.max' => 'حجمِ فایل بیش از حد است (سقف ۱۵۰ مگابایت).',
        ]);

        // جایگزینیِ فایل (در صورت آپلود فایلِ جدید)
        if ($request->hasFile('file')) {
            if (! $this->extensionSafe($request->file('file'), self::ALLOWED_EXT[$classContent->type] ?? [])) {
                return back()->withErrors(['file' => $this->extError($classContent->type)]);
            }
            $fresh = $this->storeUpload($request->file('file'), "class-content/{$classContent->type}");
            if ($fresh === false) {
                return back()->withErrors(['file' => 'ذخیره‌ی فایل روی سرور ممکن نشد.']);
            }
            // فایلِ قبلی فقط پس از موفقیتِ فایلِ تازه پاک می‌شود
            if ($classContent->file_path) {
                Storage::disk('public')->delete($classContent->file_path);
            }
            $classContent->file_path = $fresh;
        }

        $classContent->fill([
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'classroom_id' => $data['classroom_id'] ?? $classContent->classroom_id,
            'external_url' => $data['external_url'] ?? $classContent->external_url,
            'due_at'       => $data['due_at'] ?? $classContent->due_at,
            // فایلِ تازه یعنی مدتِ تازه؛ وگرنه مقدارِ قبلی می‌ماند
            'duration_seconds' => $data['duration_seconds'] ?? $classContent->duration_seconds,
            'xp_reward'    => array_key_exists('xp_reward', $data) ? $data['xp_reward'] : $classContent->xp_reward,
        ])->save();

        return back()->with('flash', 'محتوا ویرایش شد ✅');
    }

    public function destroy(Request $request, ClassContent $classContent): RedirectResponse
    {
        abort_unless($classContent->teacher_id === $request->user()->id, 403);

        if ($classContent->file_path) {
            Storage::disk('public')->delete($classContent->file_path);
        }
        $classContent->delete();

        return back()->with('flash', 'محتوا حذف شد ✅');
    }

    /** اعلانِ «محتوای جدید» به دانش‌آموزانِ کلاس (یا همه‌ی دانش‌آموزانِ معلم). best-effort — هرگز آپلود را نمی‌شکند. */
    private function notifyStudents(ClassContent $content): void
    {
        try {
            $this->doNotify($content);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('content notify failed: ' . $e->getMessage());
        }
    }

    private function doNotify(ClassContent $content): void
    {
        $teacher = $content->teacher ?: \App\Models\User::find($content->teacher_id);
        if ($content->classroom_id) {
            $ids = Classroom::find($content->classroom_id)?->students()->pluck('users.id')->all() ?? [];
        } else {
            $ids = Classroom::where('teacher_id', $content->teacher_id)
                ->with('students:id')->get()
                ->flatMap(fn ($c) => $c->students->pluck('id'))->unique()->values()->all();
        }
        if (! $ids) {
            return;
        }

        $label = [
            'material' => '📄 جزوه/فایلِ جدید',
            'podcast'  => '🎧 پادکستِ جدید',
            'gallery'  => '🖼️ تصویرِ جدید',
            'homework' => '📝 تکلیفِ جدید',
        ][$content->type] ?? '📚 محتوای جدید';

        $payload = [
            'school_id' => $content->school_id ?? optional($teacher)->school_id,
            'sender_id' => $content->teacher_id,
            'title' => $label . ' — ' . $content->title,
            'audience' => 'personal',
            'body' => "معلمت محتوای جدیدی برایت گذاشت: «{$content->title}». روی همین اعلان بزن تا ببینی"
                . ($content->type === 'podcast' ? ' و با گوش‌دادن امتیاز بگیری ⚡' : '.'),
        ];
        // ستونِ link فقط در نسخه‌هایی که آپگرید v17 را اجرا کرده‌اند وجود دارد
        if (\Illuminate\Support\Facades\Schema::hasColumn('announcements', 'link')) {
            $payload['link'] = '/class-content';
        }
        $ann = \App\Models\Announcement::create($payload);
        $ann->recipients()->sync($ids);
    }
}
