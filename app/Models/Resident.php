<?php

namespace App\Models;

use App\Concerns\CapitalizesWords;
use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    /** @use HasFactory<ResidentFactory> */
    use CapitalizesWords, HasFactory, SoftDeletes;

    /**
     * The selectable puroks. Stored verbatim, so the label is the value.
     *
     * @var list<string>
     */
    public const array PUROKS = [
        'Purok 1',
        'Purok 2',
        'Purok 3',
        'Purok 4',
        'Purok 5',
        'Purok 6',
        'Purok 7',
        'Purok 8',
        'Purok 9',
        'Purok 10',
    ];

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
        'house_number',
        'street',
        'purok',
        'residency_start_date',
        'occupation',
        'monthly_income',
        'is_voter',
        'household_head_id',
        'profile_photo_path',
        'status',
        'rejection_reason',
        'approved_at',
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
            'residency_start_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    protected function firstName(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::capitalizeWords($value));
    }

    protected function middleName(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::capitalizeWords($value));
    }

    protected function lastName(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::capitalizeWords($value));
    }

    protected function street(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::capitalizeWords($value));
    }

    protected function occupation(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::capitalizeWords($value));
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
     * Get the resident's composed address.
     *
     * The `address` column was replaced by structured parts; this accessor keeps
     * every existing `$resident->address` render site working unchanged.
     */
    public function getAddressAttribute(): string
    {
        return collect([$this->house_number, $this->street, $this->purok])
            ->filter()
            ->implode(', ');
    }

    /**
     * Get the purok number without its "Purok " prefix.
     *
     * The blotter DOCX template hard-codes "Purok ${purok_name}", so the
     * placeholder must receive the bare number.
     */
    public function getPurokNumberAttribute(): ?string
    {
        if (! $this->purok) {
            return null;
        }

        return trim(str_ireplace('Purok', '', $this->purok)) ?: null;
    }

    /**
     * Derive how many whole years the resident has lived in the barangay.
     *
     * Derived rather than stored so the figure stays correct as time passes.
     */
    public function getYearsOfResidencyAttribute(): ?int
    {
        return $this->residency_start_date?->diffInYears(now());
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
     * Get the blotters filed by the resident.
     *
     * @return HasMany<Blotter, $this>
     */
    public function blotters(): HasMany
    {
        return $this->hasMany(Blotter::class);
    }

    /**
     * Calculate the resident's age.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birthdate?->age;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
