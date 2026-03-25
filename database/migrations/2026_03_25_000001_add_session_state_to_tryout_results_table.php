<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tryout_results') || Schema::hasColumn('tryout_results', 'session_state')) {
            return;
        }

        Schema::table('tryout_results', function (Blueprint $table) {
            $table->json('session_state')->nullable()->after('answers');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tryout_results') || !Schema::hasColumn('tryout_results', 'session_state')) {
            return;
        }

        Schema::table('tryout_results', function (Blueprint $table) {
            $table->dropColumn('session_state');
        });
    }
};
