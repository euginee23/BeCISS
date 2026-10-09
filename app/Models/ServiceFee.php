<?php

namespace App\Models;

use Database\Factories\ServiceFeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceFee extends Model
{
    /** @use HasFactory<ServiceFeeFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    public const array BLOTTER_SERVICES = [
        'blotter' => 'Blotter Report',
    ];

    /**
     * Ensure every predefined service type has a fee record in the database.
     * Creates missing records with a default fee of ₱0.00 (active).
     *
     * Certificate fees live on certificate_types, so only blotter fees are synced.
     */
    public static function sync(): void
    {
        foreach (static::BLOTTER_SERVICES as $type => $label) {
            static::firstOrCreate(
                ['service_type' => $type],
                ['label' => $label, 'fee' => 0.00, 'is_active' => true],
            );
        }
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'service_type',
        'label',
        'fee',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the fee for a given service type.
     */
    public static function getFee(string $serviceType): float
    {
        $serviceFee = static::where('service_type', $serviceType)
            ->where('is_active', true)
            ->first();

        return $serviceFee ? (float) $serviceFee->fee : 0.00;
    }
}
