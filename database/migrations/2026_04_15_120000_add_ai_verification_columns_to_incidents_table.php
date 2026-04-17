<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->string('ai_verdict', 20)->nullable()->after('status');
            $table->unsignedTinyInteger('ai_confidence')->nullable()->after('ai_verdict');
            $table->text('ai_summary')->nullable()->after('ai_confidence');
            $table->json('ai_red_flags')->nullable()->after('ai_summary');
            $table->string('ai_recommended_action', 40)->nullable()->after('ai_red_flags');
            $table->string('ai_verifier_model', 120)->nullable()->after('ai_recommended_action');
            $table->timestamp('ai_verified_at')->nullable()->after('ai_verifier_model');

            $table->index(['ai_verdict', 'ai_confidence']);
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex(['ai_verdict', 'ai_confidence']);
            $table->dropColumn([
                'ai_verdict',
                'ai_confidence',
                'ai_summary',
                'ai_red_flags',
                'ai_recommended_action',
                'ai_verifier_model',
                'ai_verified_at',
            ]);
        });
    }
};
