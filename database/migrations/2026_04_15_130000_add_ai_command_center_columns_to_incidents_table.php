<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->json('ai_command_center')->nullable()->after('ai_verified_at');
            $table->timestamp('ai_commanded_at')->nullable()->after('ai_command_center');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropColumn(['ai_command_center', 'ai_commanded_at']);
        });
    }
};
