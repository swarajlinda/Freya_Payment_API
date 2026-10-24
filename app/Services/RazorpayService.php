<?php

namespace App\Services;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayService
{
    private Api $api;

    public function __construct()
    {
        $this->api = new Api(config('razorpay.key_id'), config('razorpay.key_secret'));
    }

    /** @param int $amount smallest currency unit (paise) */
    public function createOrder(int $amount, string $currency, string $receipt, array $notes = []): array
    {
        return $this->api->order->create([
            'amount'   => $amount,
            'currency' => $currency,
            'receipt'  => $receipt,
            'notes'    => $notes,
        ])->toArray();
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        try {
            $this->api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature'  => $signature,
            ]);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = (string) config('razorpay.webhook_secret');

        if ($secret === '' || $signature === '') {
            return false;
        }

        try {
            $this->api->utility->verifyWebhookSignature($rawBody, $signature, $secret);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }

    public function fetchPayment(string $paymentId): array
    {
        return $this->api->payment->fetch($paymentId)->toArray();
    }

    /** @param int|null $amount null = full refund */
    public function refund(string $paymentId, ?int $amount = null, array $notes = []): array
    {
        $params = ['notes' => $notes];

        if ($amount !== null) {
            $params['amount'] = $amount;
        }

        return $this->api->payment->fetch($paymentId)->refund($params)->toArray();
    }
}
