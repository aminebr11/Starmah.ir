<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** نظرسنجی/سؤالِ سریعِ وسطِ کلاسِ آنلاین. */
class OnlinePoll extends Model
{
    protected $fillable = ['room_id', 'question', 'options', 'correct', 'xp', 'status'];

    protected $casts = ['options' => 'array'];

    /** شمارِ پاسخ‌ها به تفکیکِ گزینه. */
    public function counts(): array
    {
        $rows = DB::table('online_poll_answers')->where('poll_id', $this->id)->selectRaw('choice, count(*) as n')->groupBy('choice')->pluck('n', 'choice');

        return array_map(fn ($i) => (int) ($rows[$i] ?? 0), array_keys($this->options ?? []));
    }

    public function choiceOf(int $userId): ?int
    {
        $c = DB::table('online_poll_answers')->where('poll_id', $this->id)->where('user_id', $userId)->value('choice');

        return $c === null ? null : (int) $c;
    }
}
