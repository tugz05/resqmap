<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_locations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            // GPS metadata (Flutter provides these natively)
            $table->float('accuracy')->nullable()->comment('GPS accuracy radius in meters');
            $table->float('altitude')->nullable()->comment('Altitude in meters');
            $table->float('heading')->nullable()->comment('Direction of travel in degrees (0-360)');
            $table->float('speed')->nullable()->comment('Speed in m/s');

            $table->boolean('is_active')->default(true)->comment('Whether the user is currently sharing location');

            // When the device last sent coordinates (different from updated_at to detect stale locations)
            $table->timestamp('located_at')->useCurrent();

            $table->timestamps();

            $table->index(['latitude', 'longitude'], 'user_locations_coordinates_index');
            $table->index('is_active');
            $table->index('located_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_locations');
    }
};
