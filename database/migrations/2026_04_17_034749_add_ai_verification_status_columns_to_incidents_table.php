<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->string('ai_verification_status', 20)
                ->default('pending')
                ->after('ai_verified_at');
            $table->timestamp('ai_verification_queued_at')
                ->nullable()
                ->after('ai_verification_status');
            $table->timestamp('ai_verification_started_at')
                ->nullable()
                ->after('ai_verification_queued_at');
            $table->text('ai_verification_error')
                ->nullable()
                ->after('ai_verification_started_at');
            $table->unsignedTinyInteger('ai_verification_attempts')
                ->default(0)
                ->after('ai_verification_error');

            $table->index('ai_verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex(['ai_verification_status']);
            $table->dropColumn([
                'ai_verification_status',
                'ai_verification_queued_at',
                'ai_verification_started_at',
                'ai_verification_error',
                'ai_verification_attempts',
            ]);
        });
    }
};
