<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * فهرستِ همه‌ی کاربرانِ سامانه برای ادمینِ کل — جست‌وجو و ویرایشِ نام و
 * شماره‌ی موبایلِ هر کس، از مدیر و معلم تا دانش‌آموز، والد و خودِ ادمین.
 */
class UserDirectoryController extends Controller
{
    private const ROLE_FA = [
        Roles::SUPER_ADMIN => 'ادمینِ کل', Roles::SCHOOL_ADMIN => 'مدیرِ مدرسه',
        Roles::TEACHER => 'معلم', Roles::STUDENT => 'دانش‌آموز', Roles::PARENT => 'والد',
    ];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');

        $users = User::query()->with(['roles:id,name', 'school:id,name'])
            ->when($role !== '' && isset(self::ROLE_FA[$role]), fn ($w) => $w->role($role))
            ->when($q !== '', function ($w) use ($q) {
                $digits = strtr($q, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
                $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$digits}%")
                    ->orWhere('national_id', 'like', "%{$digits}%"));
            })
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$request->user()->id])
            ->orderBy('name')
            ->paginate(40)->withQueryString();

        $users->getCollection()->transform(fn (User $u) => [
            'id' => $u->id, 'name' => $u->name, 'phone' => $u->phone, 'national_id' => $u->national_id,
            'avatar' => $u->avatar_url, 'school' => $u->school?->name,
            'roles' => $u->roles->pluck('name')->map(fn ($r) => self::ROLE_FA[$r] ?? $r)->values(),
            'me' => $u->id === $request->user()->id,
        ]);

        return Inertia::render('Admin/Users', [
            'users' => $users, 'filters' => ['q' => $q, 'role' => $role], 'roles' => self::ROLE_FA,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            // هر شماره در هر مدرسه یک‌بار؛ برای کاربرانِ بدونِ مدرسه (ادمینِ کل، والد) در میانِ خودشان
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9۰-۹+\s-]{7,20}$/u',
                Rule::unique('users', 'phone')->where(fn ($w) => $user->school_id ? $w->where('school_id', $user->school_id) : $w->whereNull('school_id'))->ignore($user->id)],
        ], ['phone.regex' => 'شماره‌ی موبایل فقط می‌تواند عدد داشته باشد.'], ['phone' => 'شماره موبایل', 'name' => 'نام']);

        $phone = strtr(preg_replace('/[\s-]/', '', $data['phone']), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
        $user->update(['name' => trim($data['name']), 'phone' => $phone]);

        return back()->with('flash', "مشخصاتِ «{$user->name}» ذخیره شد ✅");
    }
}
