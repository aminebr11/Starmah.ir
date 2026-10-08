<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\User;
use App\Services\BirthdayService;
use App\Services\GamificationService;
use App\Support\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** تولدِ دانش‌آموزان: فهرست، قالب‌های تبریک، ارسال با یک لمس. */
class BirthdayController extends Controller
{
    public function index(Request $request, BirthdayService $bdays): Response
    {
        $teacher = $request->user();
        $bdays->runForSchool($teacher->school_id);
        $sms = SmsGateway::can($teacher);

        return Inertia::render('Teacher/Birthdays', [
            'birthdays' => $bdays->forTeacher($teacher),
            'templates' => BirthdayService::TEMPLATES,
            'teacher'   => $teacher->name,
            'smsOk'     => $sms['ok'],
            'smsReason' => $sms['reason'],
            'focus'     => (int) $request->query('student', 0) ?: null,
        ]);
    }

    public function send(Request $request, User $user, GamificationService $game): RedirectResponse
    {
        $teacher = $request->user();
        abort_unless(Classroom::where('teacher_id', $teacher->id)
            ->whereHas('students', fn ($q) => $q->where('users.id', $user->id))->exists(), 403);

        $data = $request->validate([
            'text'      => ['required', 'string', 'max:600'],
            'gift'      => ['nullable', 'integer', 'in:0,5,10,20,30,50'],
            'to_parent' => ['nullable', 'boolean'],
        ], [], ['text' => 'متنِ تبریک']);

        $text = BirthdayService::fill($data['text'], $user, $teacher);

        $a = Announcement::create([
            'school_id' => $teacher->school_id,
            'sender_id' => $teacher->id,
            'title'     => '🎂 تولدت مبارک!',
            'audience'  => 'personal',
            'body'      => $text,
        ]);
        $a->recipients()->sync([$user->id]);

        $notes = [];
        $gift = (int) ($data['gift'] ?? 0);
        if ($gift > 0) {
            $game->awardXp($user, $gift, '🎁 هدیه‌ی تولد از ' . $teacher->name, $teacher);
            $notes[] = \App\Support\Jalali::fa((string) $gift) . " امتیاز هدیه";
        }
        if (! empty($data['to_parent'])) {
            $can = SmsGateway::can($teacher);
            $phones = SmsGateway::parentPhones($user);
            if ($can['ok'] && $phones) {
                $r = SmsGateway::sendMany($teacher, $teacher->school, array_map(fn ($p) => ['user' => null, 'phone' => $p], $phones),
                    "ولیِّ گرامی، تولدِ {$user->name} مبارک! 🎂\n" . $text, 'birthday');
                $notes[] = $r['ok'] ? 'پیامک به ولی' : 'پیامک ارسال نشد';
            } elseif (! $can['ok']) {
                $notes[] = 'پیامک: ' . $can['reason'];
            }
        }

        [$jy] = BirthdayService::jToday();
        DB::table('settings')->updateOrInsert(['key' => "bday-sent:{$user->id}:{$jy}"], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]);

        return back()->with('flash', "🎉 تبریکِ تولد برای «{$user->name}» فرستاده شد" . ($notes ? ' — ' . implode('، ', $notes) : '') . '.');
    }
}
