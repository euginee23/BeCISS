<?php

namespace App\Models;

use Database\Factories\CertificatePurposeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificatePurpose extends Model
{
    /** @use HasFactory<CertificatePurposeFactory> */
    use HasFactory;

    /**
     * The free-text option that is always offered after the managed list.
     */
    public const string OTHER = 'Other';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<CertificatePurpose>  $query
     * @return Builder<CertificatePurpose>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<CertificatePurpose>  $query
     * @return Builder<CertificatePurpose>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Active purpose names for the request forms, with "Other" always last.
     *
     * @return list<string>
     */
    public static function options(): array
    {
        return [
            ...static::query()->active()->ordered()->pluck('name')->all(),
            self::OTHER,
        ];
    }
}
