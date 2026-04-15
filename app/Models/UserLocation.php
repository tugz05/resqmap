<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'latitude',
    'longitude',
    'accuracy',
    'altitude',
    'heading',
    'speed',
    'is_active',
    'located_at',
])]
class UserLocation extends Model
{
    protected function casts(): array
    {
        return [
            'latitude'   => 'decimal:7',
            'longitude'  => 'decimal:7',
            'accuracy'   => 'float',
            'altitude'   => 'float',
            'heading'    => 'float',
            'speed'      => 'float',
            'is_active'  => 'boolean',
            'located_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Locations updated within the last N minutes (to exclude stale GPS pings) */
    public function scopeFresh(Builder $query, int $minutes = 5): Builder
    {
        return $query->where('located_at', '>=', now()->subMinutes($minutes));
    }

    public function scopeNearby(Builder $query, float $lat, float $lng, float $radiusKm = 20): Builder
    {
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));

        return $query
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
    }
}
