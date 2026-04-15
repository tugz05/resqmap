<?php

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique()->comment('Public-facing identifier for the incident');

            $table->foreignId('reporter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('type')->default(IncidentType::Other->value);
            $table->string('severity')->default(IncidentSeverity::Medium->value);
            $table->string('status')->default(IncidentStatus::Pending->value);

            $table->string('title');
            $table->text('description')->nullable();

            // Geo-coordinates (Grab-style precise location)
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('address')->nullable()->comment('Human-readable address from reverse geocoding');
            $table->string('barangay')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();

            $table->json('photo_paths')->nullable()->comment('Array of stored image paths');

            $table->timestamp('reported_at')->useCurrent();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('status');
            $table->index('type');
            $table->index('reporter_id');
            $table->index(['latitude', 'longitude'], 'incidents_coordinates_index');
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
