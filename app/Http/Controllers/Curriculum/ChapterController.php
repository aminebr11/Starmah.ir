<?php

namespace App\Http\Controllers\Curriculum;

use App\Http\Controllers\Controller;
use App\Models\CurriculumBook;
use App\Models\CurriculumChapter;
use App\Support\Curriculum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * فصل‌های کتاب‌های درسی.
 *  - معلم/مدیر: دیدنِ فصل‌ها و افزودنِ فصلِ مخصوصِ مدرسه از داخلِ فرم.
 *  - ادمین کل: مدیریتِ فصل‌های سراسری، با امکانِ چسباندنِ کلِ فهرست.
 */
class ChapterController extends Controller
{
    /** کلاس‌ها/درس‌های معلم + (اختیاری) فصل‌های یک درس. */
    public function context(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'classes' => Curriculum::teacherClasses($user),
            'chapters' => Curriculum::chapters($request->grade, $request->subject, $user->school_id),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'chapters' => Curriculum::chapters($request->grade, $request->subject, $request->user()->school_id),
        ]);
    }

    /** افزودنِ فصلِ مخصوصِ مدرسه (وقتی فصلِ موردِ نظر در فهرستِ سراسری نیست). */
    public function storeForSchool(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'grade' => ['required', 'string', 'max:40'],
            'subject' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:160'],
            'number' => ['nullable', 'integer', 'min:1', 'max:60'],
            'lessons' => ['nullable', 'array', 'max:40'],
            'lessons.*' => ['string', 'max:120'],
        ]);
        abort_unless($user->school_id, 422);

        $title = trim($data['title']);
        $existing = CurriculumChapter::visibleTo($user->school_id)
            ->where('grade', $data['grade'])->where('subject', $data['subject'])->get()
            ->first(fn ($c) => Curriculum::fingerprint($c->title) === Curriculum::fingerprint($title));
        if ($existing) {
            return response()->json(['chapter' => Curriculum::chapterRow($existing), 'existed' => true]);
        }

        $next = (int) CurriculumChapter::visibleTo($user->school_id)
            ->where('grade', $data['grade'])->where('subject', $data['subject'])->max('number') + 1;
        $level = Curriculum::levelOf($data['grade']);
        $chapter = CurriculumChapter::create([
            'school_id' => $user->school_id, 'created_by' => $user->id,
            'level' => $level, 'grade' => $data['grade'], 'subject' => $data['subject'],
            'number' => $data['number'] ?? $next, 'title' => $title,
            'lessons' => $data['lessons'] ?? null,
            'curriculum_book_id' => CurriculumBook::where('level', $level)->where('grade', $data['grade'])
                ->where('name', $data['subject'])->value('id'),
        ]);
        return response()->json(['chapter' => Curriculum::chapterRow($chapter), 'existed' => false]);
    }

    // ── ادمین کل ──

    /** افزودنِ یک یا چند فصل به یک کتاب؛ هر خط یک فصل: «۳. کسر | کسرِ مساوی، مقایسه‌ی کسرها» */
    public function adminStore(Request $request, CurriculumBook $curriculumBook): RedirectResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:8000'], 'replace' => ['nullable', 'boolean']]);
        $rows = self::parse($data['text']);
        abort_if(! $rows, 422, 'هیچ فصلی در متن پیدا نشد.');

        $base = CurriculumChapter::whereNull('school_id')->where('grade', $curriculumBook->grade)->where('subject', $curriculumBook->name);
        if (! empty($data['replace'])) {
            (clone $base)->delete();
        }
        $next = (int) (clone $base)->max('number');
        $n = 0;
        foreach ($rows as $r) {
            $number = $r['number'] ?? ++$next;
            $next = max($next, $number);
            CurriculumChapter::updateOrCreate(
                ['school_id' => null, 'grade' => $curriculumBook->grade, 'subject' => $curriculumBook->name, 'number' => $number],
                ['title' => $r['title'], 'lessons' => $r['lessons'] ?: null, 'level' => $curriculumBook->level,
                    'curriculum_book_id' => $curriculumBook->id, 'is_active' => true]
            );
            $n++;
        }
        return back()->with('flash', \App\Support\Jalali::fa((string) $n) . " فصل برای «{$curriculumBook->name} {$curriculumBook->grade}» ثبت شد ✅");
    }

    public function adminUpdate(Request $request, CurriculumChapter $chapter): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:60'],
            'title' => ['required', 'string', 'max:160'],
            'lessons' => ['nullable', 'string', 'max:2000'],
        ]);
        $chapter->update([
            'number' => $data['number'], 'title' => trim($data['title']),
            'lessons' => self::splitLessons($data['lessons'] ?? '') ?: null,
        ]);
        return back()->with('flash', 'فصل به‌روزرسانی شد ✅');
    }

    public function adminDestroy(CurriculumChapter $chapter): RedirectResponse
    {
        $chapter->delete();
        return back()->with('flash', 'فصل حذف شد. (سؤال‌های قبلی عنوانِ فصل را نگه می‌دارند.)');
    }

    /** تجزیه‌ی متنِ چسبانده‌شده — هر خط یک فصل، با شماره و درس‌های اختیاری. */
    public static function parse(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$head, $lessons] = array_pad(explode('|', $line, 2), 2, '');
            $head = trim($head);
            $number = null;
            $digits = strtr($head, array_combine(mb_str_split('۰۱۲۳۴۵۶۷۸۹'), mb_str_split('0123456789')));
            if (preg_match('/^(?:فصل\s*)?(\d{1,2})\s*[\.\-:–—)،,]?\s*(.+)$/u', $digits, $m)) {
                $number = (int) $m[1];
                $head = trim(mb_substr($head, mb_strlen($digits) - mb_strlen($m[2])));
            } else {
                $head = trim(preg_replace('/^فصل\s*/u', '', $head));
            }
            $head = preg_replace('/^[\s\-–—:\.،]+|[\s\-–—:\.،]+$/u', '', $head);
            if ($head === '') {
                continue;
            }
            $out[] = ['number' => $number, 'title' => mb_substr($head, 0, 160), 'lessons' => self::splitLessons($lessons)];
        }
        return $out;
    }

    private static function splitLessons(string $s): array
    {
        return array_values(array_filter(array_map(fn ($x) => mb_substr(trim($x), 0, 120), preg_split('/[،,؛;]/u', $s))));
    }
}
