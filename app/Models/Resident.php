<?php

namespace App\Models;

use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    /** @use HasFactory<ResidentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',
        'gender',
        'civil_status',
        'contact_number',
        'address',
        'purok',
        'years_of_residency',
        'occupation',
        'monthly_income',
        'is_voter',
        'household_head_id',
        'profile_photo_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'monthly_income' => 'decimal:2',
            'is_voter' => 'boolean',
            'years_of_residency' => 'integer',
        ];
    }

    /**
     * Get the resident's full name.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Get the user that owns this resident profile.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the household head.
     *
     * @return BelongsTo<Resident, $this>
     */
    public function householdHead(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'household_head_id');
    }

    /**
     * Get the household members.
     *
     * @return HasMany<Resident, $this>
     */
    public function householdMembers(): HasMany
    {
        return $this->hasMany(Resident::class, 'household_head_id');
    }

    /**
     * Get the certificates for the resident.
     *
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Get the appointments for the resident.
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Calculate the resident's age.
     */
    public function getAgeAttribute(): int
    {
        return $this->birthdate->age;
    }
}
