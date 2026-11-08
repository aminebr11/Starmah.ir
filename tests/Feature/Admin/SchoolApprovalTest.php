<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\SchoolRequest;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** تأییدِ درخواستِ مدرسه: درخواستِ تکراری خطای ۵۰۰ نمی‌دهد؛ اطلاعاتِ ورود/رد با پیامک می‌رود. */
class SchoolApprovalTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function admin(): User
    {
        $this->seedRoles();
        $u = User::create(['name' => 'ادمین', 'phone' => '09120000099', 'email' => 'root@x.ir', 'password' => bcrypt('password')]);
        $u->assignRole(Roles::SUPER_ADMIN);

        return $u;
    }

    private function plan(): Plan
    {
        return Plan::create(['key' => 'trial', 'name' => 'نسخه‌ی آزمایشی', 'price' => 0, 'max_classes' => 3, 'max_students_per_class' => 30, 'duration_days' => 7, 'is_active' => true, 'sort' => 1]);
    }

    private function req(array $o = []): SchoolRequest
    {
        return SchoolRequest::create($o + ['school_name' => 'زهری', 'manager_name' => 'علی', 'manager_phone' => '09137568188',
            'manager_email' => 'ali@x.ir', 'city' => 'یزد', 'level' => 'دبستان', 'classes_count' => 1, 'status' => 'pending']);
    }

    public function test_duplicate_requests_give_a_clear_message_not_a_500(): void
    {
        $admin = $this->admin();
        $plan = $this->plan();
        $a = $this->req();
        $b = $this->req(['school_name' => 'زهرا']);

        $this->actingAs($admin)->get(route('admin.schools'))->assertOk()
            ->assertInertia(fn ($p) => $p->where('pending.0.dupes', 1)->where('pending.0.taken', null));

        $this->actingAs($admin)->post(route('admin.schools.approve', $a), ['plan_id' => $plan->id, 'password_mode' => 'manual', 'password' => 'secret12'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $manager = User::where('phone', '09137568188')->firstOrFail();
        $this->assertTrue($manager->hasRole(Roles::SCHOOL_ADMIN));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret12', $manager->password));

        // دومی با همان شماره: پیامِ روشن، نه ۵۰۰
        $this->actingAs($admin)->post(route('admin.schools.approve', $b), ['plan_id' => $plan->id, 'password_mode' => 'auto'])
            ->assertRedirect()->assertSessionHasErrors('req_' . $b->id);
        $this->assertSame('pending', $b->fresh()->status);
        $this->actingAs($admin)->get(route('admin.schools'))
            ->assertInertia(fn ($p) => $p->where('pending.0.taken.name', 'علی'));

        // با شماره‌ی دیگر ساخته می‌شود؛ ایمیلِ تکراری کنار گذاشته می‌شود
        $this->actingAs($admin)->post(route('admin.schools.approve', $b), ['plan_id' => $plan->id, 'password_mode' => 'auto', 'manager_phone' => '۰۹۱۳۱۱۱۲۲۳۳'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $second = User::where('phone', '09131112233')->firstOrFail();
        $this->assertNull($second->email);
        $this->assertSame('approved', $b->fresh()->status);
    }

    public function test_credentials_and_rejection_are_sent_by_sms(): void
    {
        $admin = $this->admin();
        $plan = $this->plan();
        Setting::put('sms_enabled', '1');
        Setting::put('sms_provider', 'iransms');
        Setting::put('sms_api_key', 'k');
        Setting::put('sms_sender', '3000');
        Http::fake(['*' => Http::response(['result' => 'success', 'messageids' => '11'], 200)]);

        $a = $this->req();
        $this->actingAs($admin)->post(route('admin.schools.approve', $a), ['plan_id' => $plan->id, 'password_mode' => 'manual', 'password' => 'pass1234'])
            ->assertSessionHas('flash.sms', 'sent');
        Http::assertSent(fn ($r) => str_contains(urldecode($r->body() . $r->url()), 'pass1234') && str_contains(urldecode($r->body() . $r->url()), '09137568188'));

        $b = $this->req(['school_name' => 'نور', 'manager_phone' => '09135554433', 'manager_email' => null]);
        $this->actingAs($admin)->post(route('admin.schools.reject', $b), ['reason' => 'اطلاعات ناقص'])->assertRedirect();
        $this->assertSame('rejected', $b->fresh()->status);
        Http::assertSent(fn ($r) => str_contains(urldecode($r->body() . $r->url()), 'اطلاعات ناقص'));
    }

    public function test_plan_with_any_key_can_be_used(): void
    {
        $admin = $this->admin();
        $plan = Plan::create(['key' => 'enterprise-yazd', 'name' => 'سازمانی', 'price' => 0, 'max_classes' => 10, 'max_students_per_class' => 30, 'duration_days' => 365, 'is_active' => true, 'sort' => 2]);
        $r = $this->req(['manager_email' => null]);
        $this->actingAs($admin)->post(route('admin.schools.approve', $r), ['plan_id' => $plan->id, 'password_mode' => 'auto'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('enterprise-yazd', \App\Models\School::where('plan_id', $plan->id)->value('plan'));
    }
}
