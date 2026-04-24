<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support MODIFY COLUMN; skip on SQLite (TEXT and LONGTEXT are equivalent in SQLite).
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE soals MODIFY question LONGTEXT NOT NULL');
        DB::statement('ALTER TABLE soals MODIFY explanation LONGTEXT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE soals MODIFY question TEXT NOT NULL');
        DB::statement('ALTER TABLE soals MODIFY explanation TEXT NULL');
    }
};
