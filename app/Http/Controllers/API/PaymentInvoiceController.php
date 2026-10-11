<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Invoicing;
use Illuminate\Http\Request;

/**
 * 결제 한 건의 "정식 인보이스"(US Letter) 보기 — 저장된 인보이스가 없는 결제(예전 결제·결제 대기)도 같은 양식으로 연다.
 *  - 회원: 내 결제만 (GET /api/payments/{id}/invoice)
 *  - 관리자: 아무 결제나 (GET /api/admin/payments/{id}/invoice, 관리자 이상 — AdminTier 기본 등급)
 * 저장된 인보이스가 있으면 그것을, 없으면 결제 내용으로 만든 미리보기(저장 안 함)를 보여 준다. 카드 종류·끝 4자리는 Stripe 에서 읽어 채운다.
 */
class PaymentInvoiceController extends Controller
{
    public function mine(Request $request, $id)
    {
        $payment = Payment::where('id', (int) $id)->where('user_id', $request->user()->id)->first();
        return $payment ? $this->render($payment, $request->query('lang') === 'en' ? 'en' : 'ko') : $this->notFound();
    }

    public function admin(Request $request, $id)
    {
        $payment = Payment::find((int) $id);
        return $payment ? $this->render($payment, $request->query('lang') === 'en' ? 'en' : 'ko') : $this->notFound();
    }

    private function render(Payment $payment, string $lang = 'ko')
    {
        Invoicing::ensureCardDetails($payment);
        $payment = $payment->fresh() ?: $payment;

        $invoice = Invoicing::ready() ? Invoice::where('payment_id', $payment->id)->first() : null;
        $invoice ??= Invoicing::previewForPayment($payment);

        return response(Invoicing::renderHtml($invoice, true, $lang), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function notFound()
    {
        return response()->json(['success' => false, 'message' => '결제 내역을 찾을 수 없어요'], 404);
    }
}
