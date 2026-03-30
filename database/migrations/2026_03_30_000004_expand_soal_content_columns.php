<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE soals MODIFY question LONGTEXT NOT NULL');
        DB::statement('ALTER TABLE soals MODIFY explanation LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE soals MODIFY question TEXT NOT NULL');
        DB::statement('ALTER TABLE soals MODIFY explanation TEXT NULL');
    }
};
