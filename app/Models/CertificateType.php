<?php

namespace App\Models;

use Database\Factories\CertificateTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CertificateType extends Model
{
    /** @use HasFactory<CertificateTypeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'fee',
        'is_active',
        'available_to_residents',
        'requires_ctc',
        'template_disk',
        'template_path',
        'template_original_name',
        'template_placeholders',
        'template_uploaded_at',
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
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
            'available_to_residents' => 'boolean',
            'requires_ctc' => 'boolean',
            'template_placeholders' => 'array',
            'template_uploaded_at' => 'datetime',
        ];
    }

    /**
     * Every type keyed by slug, including retired ones so historical
     * certificates still resolve a label.
     *
     * Falls back to the shipped constant when the table has not been seeded,
     * which keeps factories and tests working without a seeded database.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = static::withTrashed()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();

        return $labels ?: Certificate::TYPES;
    }

    /**
     * Active types only, for the staff request forms.
     *
     * @return array<string, string>
     */
    public static function activeLabels(): array
    {
        $labels = static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();

        return $labels ?: Certificate::TYPES;
    }

    /**
     * Active types a resident is allowed to request themselves.
     *
     * @return array<string, string>
     */
    public static function residentLabels(): array
    {
        $labels = static::query()
            ->where('is_active', true)
            ->where('available_to_residents', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();

        return $labels ?: Certificate::TYPES;
    }

    /**
     * The fee charged for a type, falling back to the legacy service fee
     * catalogue so unseeded databases keep returning the old amounts.
     */
    public static function feeFor(string $slug): float
    {
        $type = static::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($type) {
            return (float) $type->fee;
        }

        return static::query()->where('slug', $slug)->exists()
            ? 0.00
            : ServiceFee::getFee($slug);
    }

    /**
     * Whether a type requires Community Tax Certificate details on issuance.
     */
    public static function requiresCtc(string $slug): bool
    {
        $type = static::withTrashed()->where('slug', $slug)->first();

        /**
         * Unseeded databases keep the shipped behaviour, where every type
         * collected CTC details.
         */
        return $type ? $type->requires_ctc : array_key_exists($slug, Certificate::TYPES);
    }

    /**
     * Certificates issued under this type, matched on the slug snapshot.
     *
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'type', 'slug');
    }

    /**
     * Absolute path to the uploaded template, when one exists on disk.
     */
    public function templateFullPath(): ?string
    {
        if (! $this->template_path) {
            return null;
        }

        $disk = Storage::disk($this->template_disk ?: 'local');

        return $disk->exists($this->template_path)
            ? $disk->path($this->template_path)
            : null;
    }

    /**
     * Whether an admin-uploaded template is available for this type.
     */
    public function hasTemplate(): bool
    {
        return $this->templateFullPath() !== null;
    }
}
