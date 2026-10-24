<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __invoke(Request $request, RazorpayService $razorpay): JsonResponse
    {
        // IMPORTANT: verify against the RAW body, not re-encoded JSON.
        $rawBody   = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature');

        if (! $razorpay->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('Razorpay webhook: invalid signature');

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $event   = (string) $request->input('event');
        $payload = $request->input('payload', []);

        DB::transaction(fn () => match ($event) {
            'payment.captured', 'order.paid' => $this->handlePaid($payload),
            'payment.failed'                 => $this->handleFailed($payload),
            'refund.processed', 'refund.failed' => $this->handleRefund($payload),
            default                          => null,
        });

        // Always 200 for verified events so Razorpay stops retrying.
        return response()->json(['status' => 'ok']);
    }

    private function findPayment(?string $orderId): ?Payment
    {
        return $orderId ? Payment::where('razorpay_order_id', $orderId)->lockForUpdate()->first() : null;
    }

    private function handlePaid(array $payload): void
    {
        $entity  = $payload['payment']['entity'] ?? [];
        $orderId = $entity['order_id'] ?? ($payload['order']['entity']['id'] ?? null);

        $this->findPayment($orderId)?->markPaid($entity['id'] ?? '', $entity['method'] ?? null);
    }

    private function handleFailed(array $payload): void
    {
        $entity = $payload['payment']['entity'] ?? [];

        $this->findPayment($entity['order_id'] ?? null)?->markFailed(
            $entity['error_code'] ?? null,
            $entity['error_description'] ?? null,
            $entity['id'] ?? null,
        );
    }

    private function handleRefund(array $payload): void
    {
        $refund  = $payload['refund']['entity'] ?? [];
        $payment = Payment::where('razorpay_payment_id', $refund['payment_id'] ?? '')->lockForUpdate()->first();

        if (! $payment || empty($refund['id'])) {
            return;
        }

        $payment->refunds()->updateOrCreate(
            ['razorpay_refund_id' => $refund['id']],
            ['amount' => $refund['amount'], 'status' => $refund['status'] ?? 'processed'],
        );

        $payment->syncRefundState();
    }
}
