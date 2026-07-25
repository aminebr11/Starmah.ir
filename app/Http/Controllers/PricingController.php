<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

/** صفحه‌ی عمومیِ قیمت — طرح‌های فعال با قیمتِ تنظیم‌شده‌ی ادمین. */
class PricingController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::where('is_active', true)->orderBy('sort')->get()->map(fn ($p) => [
            'key'          => $p->key,
            'name'         => $p->name,
            'description'  => $p->description,
            'features'     => $p->features ?: [],
            'price'        => (int) $p->price,
            'period_label' => $p->period_label,
            'highlighted'  => (bool) $p->highlighted,
            'max_classes'  => $p->max_classes,
            'max_students_per_class' => $p->max_students_per_class,
            'duration_days'=> $p->duration_days,
        ]);

        return Inertia::render('Pricing', ['plans' => $plans]);
    }
}
