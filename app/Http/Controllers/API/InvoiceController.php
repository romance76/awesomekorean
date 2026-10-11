<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Invoicing;
use Illuminate\Http\Request;

/**
 * 회원용 인보이스 보기 — 내 인보이스만. 남의 번호거나 없는 번호는 똑같이 404 (번호가 있는지조차 알려 주지 않는다).
 * 기본은 영수증 HTML(메일과 같은 모양). ?format=json 이면 같은 내용을 JSON 으로.
 */
class InvoiceController extends Controller
{
    public function show(Request $request, string $invoiceNo)
    {
        $notFound = fn () => response()->json(['success' => false, 'message' => '인보이스를 찾을 수 없어요'], 404);

        if (!preg_match('/^[A-Z]{2}-\d{4}-\d{6,9}$/', $invoiceNo) || !Invoicing::ready()) return $notFound();

        $invoice = Invoice::where('invoice_no', $invoiceNo)->where('user_id', $request->user()->id)->first();
        if (!$invoice) return $notFound();

        if ($request->query('format') === 'json') {
            return response()->json(['success' => true, 'data' => [
                'invoice_no' => $invoice->invoice_no,
                'status' => $invoice->status,
                'currency' => $invoice->currency,
                'subtotal_cents' => $invoice->subtotal_cents,
                'discount_cents' => $invoice->discount_cents,
                'total_cents' => $invoice->total_cents,
                'line_items' => $invoice->line_items,
                'billing_name' => $invoice->billing_name,
                'billing_email' => $invoice->billing_email,
                'issued_at' => $invoice->issued_at,
                'voided_at' => $invoice->voided_at,
                'void_reason' => $invoice->void_reason,
            ]]);
        }

        return response(Invoicing::renderHtml($invoice, true), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
