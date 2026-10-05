<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_sessions', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('llm_runner_info');
        });
    }

    public function down(): void
    {
        Schema::table('review_sessions', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
