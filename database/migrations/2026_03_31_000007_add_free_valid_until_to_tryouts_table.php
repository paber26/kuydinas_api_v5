<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tryouts')) {
            return;
        }

        if (Schema::hasColumn('tryouts', 'free_valid_until')) {
            return;
        }

        Schema::table('tryouts', function (Blueprint $table) {
            $table->date('free_valid_until')->nullable()->after('free_valid_days');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tryouts') || !Schema::hasColumn('tryouts', 'free_valid_until')) {
            return;
        }

        Schema::table('tryouts', function (Blueprint $table) {
            $table->dropColumn('free_valid_until');
        });
    }
};
