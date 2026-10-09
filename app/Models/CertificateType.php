<?php

namespace App\Models;

use Database\Factories\CertificateTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'requirements',
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
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return static::withTrashed()
            ->ordered()
            ->pluck('name', 'slug')
            ->all();
    }

    /**
     * Active types only, for the staff request forms.
     *
     * @return array<string, string>
     */
    public static function activeLabels(): array
    {
        return static::query()
            ->active()
            ->ordered()
            ->pluck('name', 'slug')
            ->all();
    }

    /**
     * Active types a resident is allowed to request themselves.
     *
     * @return array<string, string>
     */
    public static function residentLabels(): array
    {
        return static::query()
            ->active()
            ->where('available_to_residents', true)
            ->ordered()
            ->pluck('name', 'slug')
            ->all();
    }

    /**
     * The fee charged for an active type, or zero for unknown and retired ones.
     */
    public static function feeFor(string $slug): float
    {
        $type = static::query()->active()->where('slug', $slug)->first();

        return $type ? (float) $type->fee : 0.00;
    }

    /**
     * Whether a type requires Community Tax Certificate details on issuance.
     */
    public static function requiresCtc(string $slug): bool
    {
        return (bool) static::withTrashed()->where('slug', $slug)->value('requires_ctc');
    }

    /**
     * Build a unique slug from a type name.
     */
    public static function slugFor(string $name, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::snake(Str::lower(preg_replace('/[^A-Za-z0-9 ]+/', ' ', $name))), 90, '') ?: 'certificate';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'_'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  Builder<CertificateType>  $query
     * @return Builder<CertificateType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<CertificateType>  $query
     * @return Builder<CertificateType>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Whether a fee must be collected before the certificate is processed.
     */
    public function isFree(): bool
    {
        return (float) $this->fee <= 0;
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
