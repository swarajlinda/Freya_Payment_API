<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status'  => PaymentStatus::class,
            'notes'   => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function refundableAmount(): int
    {
        return max(0, $this->amount - $this->amount_refunded);
    }

    public function canBeRefunded(): bool
    {
        return in_array($this->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)
            && $this->refundableAmount() > 0;
    }

    public function markPaid(string $paymentId, ?string $method = null): void
    {
        if ($this->status !== PaymentStatus::Created && $this->status !== PaymentStatus::Failed) {
            return; // already paid / refunded: idempotent
        }

        $this->update([
            'razorpay_payment_id' => $paymentId,
            'method'              => $method ?? $this->method,
            'status'              => PaymentStatus::Paid,
            'paid_at'             => now(),
            'failure_code'        => null,
            'failure_reason'      => null,
        ]);
    }

    public function markFailed(?string $code, ?string $reason, ?string $paymentId = null): void
    {
        if ($this->status !== PaymentStatus::Created) {
            return; // never downgrade a paid payment
        }

        $this->update([
            'razorpay_payment_id' => $paymentId ?? $this->razorpay_payment_id,
            'status'              => PaymentStatus::Failed,
            'failure_code'        => $code,
            'failure_reason'      => $reason,
        ]);
    }

    /** Recalculate refunded total + status from refund rows. */
    public function syncRefundState(): void
    {
        $total = (int) $this->refunds()->where('status', '!=', 'failed')->sum('amount');

        $this->amount_refunded = $total;

        if ($total > 0 && in_array($this->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true)) {
            $this->status = $total >= $this->amount
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded;
        }

        $this->save();
    }
}
