<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'reporter_id',
    'type',
    'severity',
    'status',
    'title',
    'description',
    'latitude',
    'longitude',
    'address',
    'barangay',
    'city',
    'province',
    'photo_paths',
    'reported_at',
    'verified_at',
    'verified_by',
    'dispatched_at',
    'resolved_at',
])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type'          => IncidentType::class,
            'severity'      => IncidentSeverity::class,
            'status'        => IncidentStatus::class,
            'photo_paths'   => 'array',
            'latitude'      => 'decimal:7',
            'longitude'     => 'decimal:7',
            'reported_at'   => 'datetime',
            'verified_at'   => 'datetime',
            'dispatched_at' => 'datetime',
            'resolved_at'   => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $incident): void {
            $incident->ulid ??= (string) Str::ulid();
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(IncidentAssignment::class);
    }

    public function activeAssignment(): HasMany
    {
        return $this->hasMany(IncidentAssignment::class)
            ->whereNotIn('status', [
                AssignmentStatus::Completed->value,
                AssignmentStatus::Cancelled->value,
            ]);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            static fn (IncidentStatus $s): string => $s->value,
            array_filter(IncidentStatus::cases(), static fn (IncidentStatus $s): bool => $s->isActive()),
        ));
    }

    public function scopeNearby(Builder $query, float $lat, float $lng, float $radiusKm = 10): Builder
    {
        // Haversine approximation using bounding box pre-filter then exact calculation
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));

        return $query
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->selectRaw(
                '*, ( 6371 * acos( cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)) ) ) AS distance_km',
                [$lat, $lng, $lat],
            )
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km');
    }

    public function scopeOfType(Builder $query, IncidentType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeOfStatus(Builder $query, IncidentStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
