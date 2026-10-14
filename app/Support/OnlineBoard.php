<?php

namespace App\Support;

use App\Models\OnlineBoardPage;
use App\Models\OnlineBook;
use App\Models\OnlineBookPage;
use App\Models\OnlineRoom;
use Illuminate\Database\Eloquent\Model;

/**
 * صفحه‌های تخته‌ی کلاس: ۰ تا ۱۹۹ صفحه‌های خودِ کلاس‌اند؛ از ۱۰۰۰ به بعد صفحه‌های کتابِ بازِ کلاس
 * (۱۰۰۰ = صفحه‌ی ۱ کتاب). نوشته‌های روی صفحه‌ی کتاب روی خودِ کتاب ذخیره می‌شود، نه روی کلاس؛
 * پس دفعه‌ی بعد که کتاب باز شود، همان‌جا هست.
 */
class OnlineBoard
{
    public const BOOK_BASE = 1000;

    public static function isBook(int $page): bool
    {
        return $page >= self::BOOK_BASE;
    }

    /** شماره‌ی صفحه‌ی کتاب (از ۱) برای شماره‌ی صفحه‌ی تخته. */
    public static function bookPageNo(int $page): int
    {
        return $page - self::BOOK_BASE + 1;
    }

    public static function boardIndex(int $bookPage): int
    {
        return self::BOOK_BASE + $bookPage - 1;
    }

    /** کتابِ بازِ کلاس (اگر هست). */
    public static function book(OnlineRoom $room): ?OnlineBook
    {
        $id = (int) ($room->opt('book')['id'] ?? 0);

        return $id && OnlineBook::ready() ? OnlineBook::find($id) : null;
    }

    /** آیا این شماره‌ی صفحه در این کلاس معتبر است؟ */
    public static function valid(OnlineRoom $room, int $page): bool
    {
        if (! self::isBook($page)) {
            return $page >= 0 && $page <= 199;
        }
        $book = $room->opt('book');
        if (! $book) {
            return false;
        }
        $n = self::bookPageNo($page);
        $max = (int) ($book['pages'] ?? 0);

        return $n >= 1 && ($max === 0 || $n <= $max) && $n <= 3000;
    }

    /** ردیفِ ذخیره‌ی همین صفحه (صفحه‌ی کلاس یا صفحه‌ی کتاب). */
    public static function row(OnlineRoom $room, int $page): ?Model
    {
        if (! self::isBook($page)) {
            return OnlineBoardPage::firstOrNew(['room_id' => $room->id, 'page' => $page]);
        }
        $bookId = (int) ($room->opt('book')['id'] ?? 0);

        return $bookId ? OnlineBookPage::firstOrNew(['book_id' => $bookId, 'page' => self::bookPageNo($page)]) : null;
    }

    /** زمینه‌ای که مرورگر نشان می‌دهد: برای صفحه‌ی کتاب، نشانیِ تصویرِ ساخته‌شده‌ی همان صفحه. */
    public static function bg(OnlineRoom $room, int $page, ?Model $row): string
    {
        if (! self::isBook($page)) {
            return $row?->bg ?: 'blank';
        }
        if (! $row || ! $row->bg) {
            return 'blank';
        }

        return route('online.book.img', [$room->id, $row->book_id, $row->page], false) . '?v=' . substr(md5($row->bg), 0, 8);
    }
}
