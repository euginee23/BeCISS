<?php

namespace App\Models;

use App\Concerns\CapitalizesWords;
use App\Concerns\HasActivityLogs;
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
    use CapitalizesWords, HasActivityLogs, HasFactory, SoftDeletes;

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
     * @var array<string, string>
     */
    public const array CIVIL_STATUSES = [
        'single' => 'Single',
        'married' => 'Married',
        'widowed' => 'Widowed',
        'separated' => 'Separated',
        'divorced' => 'Divorced',
    ];

    /**
     * @var array<string, string>
     */
    public const array EDUCATION_LEVELS = [
        'none' => 'No Formal Education',
        'elementary_undergraduate' => 'Elementary Undergraduate',
        'elementary_graduate' => 'Elementary Graduate',
        'high_school_undergraduate' => 'High School Undergraduate',
        'high_school_graduate' => 'High School Graduate',
        'senior_high_graduate' => 'Senior High School Graduate',
        'vocational' => 'Vocational / Technical',
        'college_undergraduate' => 'College Undergraduate',
        'college_graduate' => 'College Graduate',
        'post_graduate' => 'Post Graduate',
    ];

    /**
     * @var array<string, string>
     */
    public const array EMPLOYMENT_STATUSES = [
        'employed' => 'Employed',
        'self_employed' => 'Self-Employed',
        'unemployed' => 'Unemployed',
        'student' => 'Student',
        'retired' => 'Retired',
        'homemaker' => 'Homemaker',
    ];

    /**
     * @var list<string>
     */
    public const array BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /**
     * Stored sector flags, keyed by column. Senior citizens are derived from age.
     *
     * @var array<string, string>
     */
    public const array SECTORS = [
        'is_pwd' => 'Person with Disability (PWD)',
        'is_solo_parent' => 'Solo Parent',
        'is_4ps_beneficiary' => '4Ps Beneficiary',
        'is_indigenous' => 'Indigenous People (IP)',
        'is_ofw' => 'Overseas Filipino Worker (OFW)',
        'is_out_of_school_youth' => 'Out-of-School Youth',
    ];

    /**
     * Age groups used by the registry filters, as [min, max] years.
     *
     * @var array<string, array{0: int, 1: ?int}>
     */
    public const array AGE_GROUPS = [
        'minor' => [0, 17],
        'adult' => [18, 59],
        'senior' => [60, null],
    ];

    public const int SENIOR_AGE = 60;

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
        'place_of_birth',
        'gender',
        'civil_status',
        'citizenship',
        'religion',
        'blood_type',
        'contact_number',
        'email',
        'house_number',
        'street',
        'purok',
        'residency_start_date',
        'educational_attainment',
        'employment_status',
        'occupation',
        'monthly_income',
        'is_voter',
        'is_pwd',
        'pwd_id_number',
        'is_solo_parent',
        'is_4ps_beneficiary',
        'is_indigenous',
        'is_ofw',
        'is_out_of_school_youth',
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
            'is_pwd' => 'boolean',
            'is_solo_parent' => 'boolean',
            'is_4ps_beneficiary' => 'boolean',
            'is_indigenous' => 'boolean',
            'is_ofw' => 'boolean',
            'is_out_of_school_youth' => 'boolean',
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
        return $this->residency_start_date ? (int) $this->residency_start_date->diffInYears(now()) : null;
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

    public function isSenior(): bool
    {
        return $this->age !== null && $this->age >= self::SENIOR_AGE;
    }

    /**
     * Labels of every sector the resident belongs to, seniors included.
     *
     * @return list<string>
     */
    public function getSectorLabelsAttribute(): array
    {
        $labels = $this->isSenior() ? ['Senior Citizen'] : [];

        foreach (self::SECTORS as $column => $label) {
            if ($this->{$column}) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public function getCivilStatusLabelAttribute(): string
    {
        return self::CIVIL_STATUSES[$this->civil_status] ?? ucfirst((string) $this->civil_status);
    }

    public function getEducationLabelAttribute(): ?string
    {
        return self::EDUCATION_LEVELS[$this->educational_attainment] ?? null;
    }

    public function getEmploymentLabelAttribute(): ?string
    {
        return self::EMPLOYMENT_STATUSES[$this->employment_status] ?? null;
    }

    /**
     * Filter by an age group from AGE_GROUPS, using the birthdate.
     *
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeAgeGroup(Builder $query, string $group): Builder
    {
        [$min, $max] = self::AGE_GROUPS[$group] ?? [0, null];

        return $query
            ->whereDate('birthdate', '<=', now()->subYears($min)->toDateString())
            ->when($max !== null, fn (Builder $query) => $query->whereDate('birthdate', '>', now()->subYears($max + 1)->toDateString()));
    }

    /**
     * Filter by a sector key: a SECTORS column, or "senior".
     *
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeInSector(Builder $query, string $sector): Builder
    {
        if ($sector === 'senior') {
            return $query->ageGroup('senior');
        }

        return array_key_exists($sector, self::SECTORS)
            ? $query->where($sector, true)
            : $query;
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
