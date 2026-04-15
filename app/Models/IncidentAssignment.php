<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incident_id',
    'rescuer_id',
    'assigned_by',
    'status',
    'notes',
    'assigned_at',
    'accepted_at',
    'arrived_at',
    'completed_at',
])]
class IncidentAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'status'       => AssignmentStatus::class,
            'assigned_at'  => 'datetime',
            'accepted_at'  => 'datetime',
            'arrived_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function rescuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescuer_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            AssignmentStatus::Completed->value,
            AssignmentStatus::Cancelled->value,
        ]);
    }

    public function scopeForRescuer(Builder $query, int $rescuerId): Builder
    {
        return $query->where('rescuer_id', $rescuerId);
    }
}
