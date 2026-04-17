<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->json('ai_dispatch')->nullable()->after('ai_commanded_at');
            $table->timestamp('ai_dispatched_at')->nullable()->after('ai_dispatch');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropColumn(['ai_dispatch', 'ai_dispatched_at']);
        });
    }
};
