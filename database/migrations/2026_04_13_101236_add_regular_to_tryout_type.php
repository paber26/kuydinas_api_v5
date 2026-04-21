<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tryouts MODIFY COLUMN type ENUM('free', 'premium', 'regular') NOT NULL DEFAULT 'free'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tryouts MODIFY COLUMN type ENUM('free', 'premium') NOT NULL DEFAULT 'free'");
    }
};
