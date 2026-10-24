<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\RefundRequest;
use App\Http\Requests\VerifyPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private RazorpayService $razorpay) {}

    /** POST /payments/orders: create a Razorpay order + local payment row. */
    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        $data     = $request->validated();
        $amount   = (int) round($data['amount'] * 100); // major -> minor units
        $currency = strtoupper($data['currency'] ?? config('razorpay.currency'));
        $receipt  = 'rcpt_' . Str::upper(Str::random(14));
        $notes    = $data['notes'] ?? [];

        try {
            $order = $this->razorpay->createOrder($amount, $currency, $receipt, $notes);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to create order with payment gateway.'], 502);
        }

        $payment = Payment::create([
            'user_id'           => $request->user()->id,
            'receipt'           => $receipt,
            'razorpay_order_id' => $order['id'],
            'amount'            => $amount,
            'currency'          => $currency,
            'status'            => PaymentStatus::Created,
            'description'       => $data['description'] ?? null,
            'notes'             => $notes ?: null,
        ]);

        return response()->json([
            'message' => 'Order created.',
            'data'    => [
                'key_id'   => config('razorpay.key_id'),   // public key, used by Checkout.js
                'order_id' => $order['id'],
                'amount'   => $amount,
                'currency' => $currency,
                'payment'  => new PaymentResource($payment),
            ],
        ], 201);
    }

    /** POST /payments/verify: verify signature returned by Checkout.js. */
    public function verify(VerifyPaymentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $payment = Payment::where('razorpay_order_id', $data['razorpay_order_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($payment->status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Payment already verified.', 'data' => new PaymentResource($payment)]);
        }

        $valid = $this->razorpay->verifyPaymentSignature(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
        );

        if (! $valid) {
            $payment->markFailed('SIGNATURE_MISMATCH', 'Payment signature verification failed.', $data['razorpay_payment_id']);

            return response()->json(['message' => 'Invalid payment signature.'], 422);
        }

        $method = null;
        try {
            $method = $this->razorpay->fetchPayment($data['razorpay_payment_id'])['method'] ?? null;
        } catch (Throwable $e) {
            report($e); // non-fatal: webhook will also carry the method
        }

        DB::transaction(function () use ($payment, $data, $method) {
            $payment = Payment::lockForUpdate()->find($payment->id);
            $payment->markPaid($data['razorpay_payment_id'], $method);
        });

        return response()->json([
            'message' => 'Payment verified.',
            'data'    => new PaymentResource($payment->refresh()),
        ]);
    }

    /** GET /payments: list the authenticated user's payments. */
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'in:' . implode(',', array_column(PaymentStatus::cases(), 'value'))]]);

        $payments = Payment::where('user_id', $request->user()->id)
            ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return PaymentResource::collection($payments);
    }

    /** GET /payments/{id} */
    public function show(Request $request, int $id): PaymentResource
    {
        $payment = Payment::with('refunds')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return new PaymentResource($payment);
    }

    /** POST /admin/payments/{payment}/refund (admin key required). */
    public function refund(RefundRequest $request, Payment $payment): JsonResponse
    {
        if (! $payment->canBeRefunded() || ! $payment->razorpay_payment_id) {
            return response()->json(['message' => 'Payment is not refundable.'], 422);
        }

        $amount = $request->filled('amount') ? (int) round($request->input('amount') * 100) : null;

        if ($amount !== null && $amount > $payment->refundableAmount()) {
            return response()->json([
                'message' => 'Refund amount exceeds refundable balance.',
                'data'    => ['refundable_amount' => $payment->refundableAmount()],
            ], 422);
        }

        try {
            $result = $this->razorpay->refund(
                $payment->razorpay_payment_id,
                $amount,
                array_filter(['reason' => $request->input('reason'), 'receipt' => $payment->receipt]),
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Refund failed at payment gateway.'], 502);
        }

        DB::transaction(function () use ($payment, $result, $request) {
            $payment->refunds()->updateOrCreate(
                ['razorpay_refund_id' => $result['id']],
                [
                    'amount' => $result['amount'],
                    'status' => $result['status'] ?? 'pending',
                    'reason' => $request->input('reason'),
                ],
            );
            $payment->syncRefundState();
        });

        return response()->json([
            'message' => 'Refund initiated.',
            'data'    => new PaymentResource($payment->refresh()->load('refunds')),
        ], 201);
    }
}
