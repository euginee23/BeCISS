<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The service a certificate pickup or payment visit is booked under.
     */
    public const string CERTIFICATE_VISIT = 'certificate_request';

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
        'is_bookable',
        'show_on_website',
        'is_system',
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
            'is_bookable' => 'boolean',
            'show_on_website' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Every service keyed by slug, including retired ones for old appointments.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return static::withTrashed()->ordered()->pluck('name', 'slug')->all();
    }

    /**
     * Build a unique slug from a service name.
     */
    public static function slugFor(string $name): string
    {
        $base = Str::limit(Str::snake(Str::lower(preg_replace('/[^A-Za-z0-9 ]+/', ' ', $name))), 90, '') ?: 'service';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'_'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Services residents and staff can book an appointment for.
     *
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->active()->where('is_bookable', true);
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeOnWebsite(Builder $query): Builder
    {
        return $query->active()->where('show_on_website', true);
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Appointments booked for this service, matched on the slug.
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'service_type', 'slug');
    }
}
