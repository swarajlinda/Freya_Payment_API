<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'receipt'         => $this->receipt,
            'order_id'        => $this->razorpay_order_id,
            'payment_id'      => $this->razorpay_payment_id,
            'amount'          => $this->amount,
            'amount_decimal'  => number_format($this->amount / 100, 2, '.', ''),
            'currency'        => $this->currency,
            'status'          => $this->status->value,
            'method'          => $this->method,
            'amount_refunded' => $this->amount_refunded,
            'description'     => $this->description,
            'notes'           => $this->notes,
            'failure_code'    => $this->failure_code,
            'failure_reason'  => $this->failure_reason,
            'paid_at'         => $this->paid_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
            'refunds'         => $this->whenLoaded('refunds', fn () => $this->refunds->map(fn ($r) => [
                'id'         => $r->id,
                'refund_id'  => $r->razorpay_refund_id,
                'amount'     => $r->amount,
                'status'     => $r->status,
                'reason'     => $r->reason,
                'created_at' => $r->created_at?->toIso8601String(),
            ])),
        ];
    }
}
