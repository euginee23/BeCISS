<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    /**
     * The parts of the system a staff action can belong to.
     *
     * @var array<string, string>
     */
    public const array MODULES = [
        'residents' => 'Residents',
        'certificates' => 'Certificates',
        'appointments' => 'Appointments',
        'blotters' => 'Blotters',
    ];

    /**
     * The recorded actions.
     *
     * @var array<string, string>
     */
    public const array ACTIONS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'processing' => 'Started Processing',
        'ready' => 'Marked Ready for Pickup',
        'completed' => 'Completed',
        'paid' => 'Recorded Payment',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
        'no_show' => 'Marked No Show',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'module',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * Record a staff action.
     *
     * @param  array<string, mixed>|null  $properties
     */
    public static function record(
        string $module,
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $properties = null,
    ): self {
        return self::create([
            'user_id' => auth()->id(),
            'module' => $module,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    /**
     * The staff member who performed the action.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The record the action was performed on, if it still exists.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the human-readable action label.
     */
    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    /**
     * Get the human-readable module label.
     */
    public function getModuleLabelAttribute(): string
    {
        return self::MODULES[$this->module] ?? ucfirst($this->module);
    }
}
