<?php

namespace App\Http\Controllers\Online;

use App\Http\Controllers\Controller;
use App\Models\OnlineBook;
use App\Models\OnlineBookPage;
use App\Models\OnlineParticipant;
use App\Models\OnlineRoom;
use App\Support\OnlineAccess;
use App\Support\OnlineBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * «📚 کتاب‌های درسیِ من» در کلاسِ آنلاین: PDF روی سرور می‌ماند؛ معلم هر بار در کلاس بازش می‌کند،
 * صفحه‌ها یک‌بار به تصویر تبدیل و نگه داشته می‌شوند (بارِ بعد فوری)، نوشته‌ها روی خودِ کتاب ذخیره
 * می‌شود و «تا کجا درس داده‌ام» برای هر کلاس یادش می‌ماند.
 */
class BookController extends Controller
{
    /** حداکثرِ حجمِ کتاب (مگابایت). */
    private const MAX_MB = 300;

    private function host(Request $request, OnlineRoom $room): void
    {
        abort_unless(OnlineAccess::ready() && OnlineBook::ready(), 503, 'جدول‌های کتاب هنوز ساخته نشده‌اند؛ صفحه را تازه کنید.');
        abort_unless(OnlineAccess::roleIn($request->user(), $room) === 'host', 403);
    }

    private function own(Request $request, OnlineBook $book): void
    {
        abort_unless((int) $book->teacher_id === (int) $request->user()->id, 404);
    }

    private function card(OnlineBook $b, OnlineRoom $room): array
    {
        return [
            'id' => $b->id, 'title' => $b->title, 'pages' => (int) $b->pages, 'size_mb' => round($b->size / 1048576, 1),
            'page' => $b->pageFor($room->classroom_id),
            'notes' => OnlineBookPage::where('book_id', $b->id)->whereNotNull('ops')->where('ops', '!=', '[]')->count(),
        ];
    }

    public function index(Request $request, OnlineRoom $room): JsonResponse
    {
        $this->host($request, $room);

        return response()->json(['books' => OnlineBook::where('teacher_id', $request->user()->id)->latest('updated_at')->get()
            ->map(fn ($b) => $this->card($b, $room))->values(), 'max_mb' => self::MAX_MB]);
    }

    /** بارگذاریِ تکه‌تکه (هاست‌ها معمولاً فایلِ بزرگ را یک‌جا نمی‌پذیرند). */
    public function upload(Request $request, OnlineRoom $room): JsonResponse
    {
        $this->host($request, $room);
        $d = $request->validate([
            'upload' => 'required|string|regex:/^[A-Za-z0-9]{8,40}$/', 'seq' => 'required|integer|min:0|max:200',
            'total' => 'required|integer|min:1|max:200', 'title' => 'required|string|max:150', 'chunk' => 'required|file|max:10240',
        ]);
        $u = $request->user();
        $tmp = "online-books/tmp/{$u->id}-{$d['upload']}.part";
        Storage::disk('local')->makeDirectory('online-books/tmp');
        $path = Storage::disk('local')->path($tmp);
        if ((int) $d['seq'] === 0) {
            @unlink($path);
        }
        clearstatcache(true, $path);
        abort_if(is_file($path) && filesize($path) > self::MAX_MB * 1048576, 413, 'کتاب بیش از ' . self::MAX_MB . ' مگابایت است.');
        file_put_contents($path, file_get_contents($request->file('chunk')->getRealPath()), FILE_APPEND | LOCK_EX);
        clearstatcache(true, $path);
        if ((int) $d['seq'] < (int) $d['total'] - 1) {
            return response()->json(['done' => false]);
        }

        // تکه‌ی آخر: باید واقعاً PDF باشد
        $fh = fopen($path, 'rb');
        $head = $fh ? fread($fh, 1024) : '';
        if ($fh) {
            fclose($fh);
        }
        if (! str_contains((string) $head, '%PDF')) {
            @unlink($path);
            abort(422, 'این فایل PDF نیست. کتاب را به‌صورتِ PDF بفرستید.');
        }
        $book = OnlineBook::create(['school_id' => $u->school_id, 'teacher_id' => $u->id, 'title' => trim($d['title']),
            'path' => '', 'size' => filesize($path), 'pages' => 0]);
        $final = "online-books/{$book->id}/book-" . Str::random(10) . '.pdf';
        Storage::disk('local')->makeDirectory("online-books/{$book->id}");
        rename($path, Storage::disk('local')->path($final));
        $book->forceFill(['path' => $final])->save();

        return response()->json(['done' => true, 'book' => $this->card($book, $room)]);
    }

    /** خودِ PDF برای مرورگرِ معلم (فقط صاحبِ کتاب؛ بچه‌ها فقط تصویرِ صفحه‌ها را می‌گیرند). */
    public function file(Request $request, OnlineRoom $room, OnlineBook $book): BinaryFileResponse
    {
        $this->host($request, $room);
        $this->own($request, $book);
        abort_unless($book->path && Storage::disk('local')->exists($book->path), 404);

        return response()->file(Storage::disk('local')->path($book->path), [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, max-age=2592000',
        ]);
    }

    /** باز کردنِ کتاب در کلاس، از همان صفحه‌ای که دفعه‌ی قبل رسیده بودیم. */
    public function open(Request $request, OnlineRoom $room, OnlineBook $book): JsonResponse
    {
        $this->host($request, $room);
        $this->own($request, $book);
        $d = $request->validate(['pages' => 'nullable|integer|min:1|max:3000', 'page' => 'nullable|integer|min:1|max:3000']);
        if (! empty($d['pages']) && (int) $d['pages'] !== (int) $book->pages) {
            $book->forceFill(['pages' => (int) $d['pages']])->save();
        }
        $n = (int) ($d['page'] ?? $book->pageFor($room->classroom_id));
        $n = max(1, $book->pages ? min($n, (int) $book->pages) : $n);
        $cur = (int) $room->opt('page', 0);
        $room->setOpts([
            'book' => ['id' => $book->id, 'title' => $book->title, 'pages' => (int) $book->pages],
            'board_page' => OnlineBoard::isBook($cur) ? (int) $room->opt('board_page', 0) : $cur,
            'page' => OnlineBoard::boardIndex($n),
            'stage' => 'board',
        ]);
        $book->remember($room->classroom_id, $n);
        $book->touch();

        return response()->json(['page' => $n, 'board' => OnlineBoard::boardIndex($n),
            'rendered' => OnlineBookPage::where('book_id', $book->id)->whereNotNull('bg')->pluck('page')]);
    }

    /** تصویرِ یک صفحه که مرورگرِ معلم از PDF ساخته (یک‌بار؛ دفعه‌های بعد همین استفاده می‌شود). */
    public function image(Request $request, OnlineRoom $room, OnlineBook $book): JsonResponse
    {
        $this->host($request, $room);
        $this->own($request, $book);
        $d = $request->validate(['page' => 'required|integer|min:1|max:3000', 'file' => 'required|file|max:6144|mimes:jpg,jpeg,png,webp']);
        $row = OnlineBookPage::firstOrNew(['book_id' => $book->id, 'page' => (int) $d['page']]);
        $old = $row->bg;
        $rel = "online-books/{$book->id}/p{$d['page']}-" . Str::random(6) . '.jpg';
        Storage::disk('local')->putFileAs("online-books/{$book->id}", $request->file('file'), basename($rel));
        $row->bg = $rel;
        $row->version = (int) $row->version + 1;
        $row->save();
        if ($old && $old !== $rel) {
            Storage::disk('local')->delete($old);
        }
        if ((int) ($room->opt('book')['id'] ?? 0) === (int) $book->id) {
            $room->bump();
        }

        return response()->json(['version' => (int) $row->version, 'bg' => OnlineBoard::bg($room, OnlineBoard::boardIndex((int) $d['page']), $row)]);
    }

    /** تصویرِ صفحه برای همه‌ی حاضرانِ کلاس (فقط وقتی همین کتاب در کلاس باز است). */
    public function pageImage(Request $request, OnlineRoom $room, OnlineBook $book, int $page): BinaryFileResponse
    {
        abort_unless(OnlineBook::ready(), 404);
        $role = OnlineAccess::roleIn($request->user(), $room);
        abort_unless($role, 403);
        $isOwner = (int) $book->teacher_id === (int) $request->user()->id;
        abort_unless($isOwner || (int) ($room->opt('book')['id'] ?? 0) === (int) $book->id, 404);
        $row = OnlineBookPage::where('book_id', $book->id)->where('page', $page)->first();
        abort_unless($row?->bg && Storage::disk('local')->exists($row->bg), 404);

        return response()->file(Storage::disk('local')->path($row->bg), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=2592000, immutable']);
    }

    public function destroy(Request $request, OnlineRoom $room, OnlineBook $book): JsonResponse
    {
        $this->host($request, $room);
        $this->own($request, $book);
        if ((int) ($room->opt('book')['id'] ?? 0) === (int) $book->id) {
            $room->setOpts(['book' => null, 'page' => (int) $room->opt('board_page', 0)]);
        }
        Storage::disk('local')->deleteDirectory("online-books/{$book->id}");
        OnlineBookPage::where('book_id', $book->id)->delete();
        $book->delete();

        return response()->json(['ok' => true]);
    }
}
