<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\SchoolRequest;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** بازگشت از درگاهِ پرداختِ اشتراکِ مدرسه. */
class PublicPaymentController extends Controller
{
    public function callback(Request $request, PaymentTransaction $transaction, PaymentService $pay): RedirectResponse
    {
        if ($transaction->status === 'paid') {
            return redirect()->route('register.thanks', ['ref' => $transaction->ref_id]);
        }

        $res = $pay->verify($transaction, $request->all());

        if ($res['ok']) {
            $transaction->update(['status' => 'paid', 'ref_id' => $res['ref_id']]);
            if ($transaction->school_request_id) {
                SchoolRequest::whereKey($transaction->school_request_id)->update(['paid' => true]);
            }

            return redirect()->route('register.thanks', ['ref' => $res['ref_id']]);
        }

        $transaction->update(['status' => 'failed']);

        return redirect()->route('register.thanks', ['failed' => 1]);
    }
}
