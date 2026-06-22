<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** پل والدین: گفت‌وگوی دوسویه‌ی والد/معلم حول یک دانش‌آموز. */
class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $messages = Message::with(['sender'])
            ->where(function ($q) use ($user) {
                $q->where('recipient_id', $user->id)->orWhere('sender_id', $user->id);
            })
            ->latest()->limit(50)->get()
            ->sortBy('id')->values()
            ->map(fn ($m) => [
                'id'   => $m->id,
                'body' => $m->body,
                'kind' => $m->kind,
                'mine' => $m->sender_id === $user->id,
                'from' => $m->sender?->name,
                'time' => $m->created_at?->format('H:i'),
            ]);

        // طرف مقابل: برای والد → معلمِ کلاسِ فرزند؛ برای معلم → والدینِ دانش‌آموزان
        return Inertia::render('Shared/Messages', [
            'messages' => $messages,
            'contacts' => $this->contacts($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_id' => ['nullable', 'exists:users,id'],
            'student_id'   => ['nullable', 'exists:users,id'],
            'body'         => ['required', 'string', 'max:1000'],
            'kind'         => ['nullable', 'in:message,encouragement'],
        ]);

        Message::create([
            'school_id'    => $request->user()->school_id,
            'sender_id'    => $request->user()->id,
            'recipient_id' => $data['recipient_id'] ?? null,
            'student_id'   => $data['student_id'] ?? null,
            'kind'         => $data['kind'] ?? 'message',
            'body'         => $data['body'],
        ]);

        return back();
    }

    private function contacts(User $user): array
    {
        // ساده برای MVP: همه‌ی معلم‌ها و والدینِ همان مدرسه
        return User::where('school_id', $user->school_id)
            ->where('id', '!=', $user->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['teacher', 'parent']))
            ->limit(20)->get(['id', 'name'])->toArray();
    }
}
