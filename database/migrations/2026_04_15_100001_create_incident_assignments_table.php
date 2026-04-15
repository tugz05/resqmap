<?php

use App\Enums\AssignmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_assignments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('incident_id')
                ->constrained('incidents')
                ->cascadeOnDelete();

            $table->foreignId('rescuer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('assigned_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('status')->default(AssignmentStatus::Assigned->value);

            $table->text('notes')->nullable();

            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('incident_id');
            $table->index('rescuer_id');
            $table->index('status');

            // A rescuer can only have one active assignment per incident
            $table->unique(['incident_id', 'rescuer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_assignments');
    }
};
