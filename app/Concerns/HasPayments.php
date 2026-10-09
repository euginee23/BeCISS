<?php

namespace App\Concerns;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasPayments
{
    /**
     * Every payment recorded against this record.
     *
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * The most recent payment, which is the one printed on the document.
     *
     * @return MorphOne<Payment, $this>
     */
    public function latestPayment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable')->latestOfMany('paid_at');
    }

    /**
     * Whether a fee is owed before the record can move on.
     */
    public function requiresPayment(): bool
    {
        return (float) $this->fee > 0 && ! $this->is_paid;
    }

    /**
     * Record a payment and keep the denormalised is_paid / or_number columns
     * in sync, since templates and list views read them directly.
     */
    public function recordPayment(string $orNumber, User $receiver, ?string $remarks = null): Payment
    {
        $payment = $this->payments()->create([
            'or_number' => $orNumber,
            'amount' => $this->fee,
            'paid_at' => now(),
            'received_by' => $receiver->id,
            'payor_name' => $this->resident?->full_name,
            'remarks' => $remarks,
        ]);

        $this->forceFill([
            'is_paid' => true,
            'or_number' => $orNumber,
        ])->save();

        return $payment;
    }
}
