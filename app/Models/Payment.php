<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payable_type',
        'payable_id',
        'or_number',
        'amount',
        'paid_at',
        'received_by',
        'payor_name',
        'remarks',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The certificate or blotter this payment settles.
     *
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    /**
     * The cashier who received the payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * What the payment was for, e.g. "Barangay Clearance" or "Blotter Report".
     */
    public function getServiceLabelAttribute(): string
    {
        return match (true) {
            $this->payable instanceof Certificate => $this->payable->type_label,
            $this->payable instanceof Blotter => __('Blotter Report'),
            default => __('Unknown'),
        };
    }

    /**
     * The certificate or blotter number the payment settles.
     */
    public function getReferenceNumberAttribute(): string
    {
        return match (true) {
            $this->payable instanceof Certificate => $this->payable->certificate_number,
            $this->payable instanceof Blotter => $this->payable->blotter_number,
            default => '—',
        };
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopePaidBetween(Builder $query, CarbonInterface|string $from, CarbonInterface|string $to): Builder
    {
        return $query->whereBetween('paid_at', [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
        ]);
    }

    /**
     * Whether an OR number has already been issued on another payment.
     */
    public static function orNumberTaken(string $orNumber): bool
    {
        return static::query()->where('or_number', $orNumber)->exists();
    }
}
