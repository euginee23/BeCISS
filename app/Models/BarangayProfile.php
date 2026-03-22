<?php

namespace App\Models;

use Database\Factories\BarangayProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarangayProfile extends Model
{
    /** @use HasFactory<BarangayProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'barangay_name',
        'municipality',
        'province',
        'zip_code',
        'address',
        'phone',
        'email',
        'website',
        'captain_name',
        'secretary_name',
        'treasurer_name',
        'office_hours',
        'logo_path',
    ];

    /**
     * Get the singleton barangay profile, creating one with defaults if it does not exist.
     */
    public static function get(): static
    {
        return static::firstOrCreate([], [
            'barangay_name' => 'Barangay',
            'office_hours' => 'Monday - Friday, 8:00 AM - 5:00 PM',
        ]);
    }

    /**
     * Get the full location string.
     */
    public function getLocationAttribute(): string
    {
        return collect([$this->municipality, $this->province])
            ->filter()
            ->implode(', ');
    }
}
