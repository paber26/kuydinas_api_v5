<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tryout_registrations')) {
            return;
        }

        if (Schema::hasColumn('tryout_registrations', 'expires_at')) {
            return;
        }

        Schema::table('tryout_registrations', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tryout_registrations') || !Schema::hasColumn('tryout_registrations', 'expires_at')) {
            return;
        }

        Schema::table('tryout_registrations', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};

