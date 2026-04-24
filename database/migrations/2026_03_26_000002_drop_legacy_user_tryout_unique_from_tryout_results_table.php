<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tryout_results')) {
            return;
        }

        foreach ([
            'tryout_results_user_tryout_unique',
            'tryout_results_user_id_tryout_id_unique',
        ] as $indexName) {
            if ($this->indexExists('tryout_results', $indexName)) {
                Schema::table('tryout_results', function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            }
        }

        if (!$this->indexExists('tryout_results', 'tryout_results_user_tryout_attempt_unique')
            && Schema::hasColumn('tryout_results', 'attempt_number')) {
            Schema::table('tryout_results', function (Blueprint $table) {
                $table->unique(['user_id', 'tryout_id', 'attempt_number'], 'tryout_results_user_tryout_attempt_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tryout_results')) {
            return;
        }

        if ($this->indexExists('tryout_results', 'tryout_results_user_tryout_attempt_unique')) {
            Schema::table('tryout_results', function (Blueprint $table) {
                $table->dropUnique('tryout_results_user_tryout_attempt_unique');
            });
        }

        if (!$this->indexExists('tryout_results', 'tryout_results_user_tryout_unique')) {
            Schema::table('tryout_results', function (Blueprint $table) {
                $table->unique(['user_id', 'tryout_id'], 'tryout_results_user_tryout_unique');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        // SQLite does not have information_schema; use Schema::getIndexes() instead.
        if (DB::getDriverName() === 'sqlite') {
            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $index) {
                if ($index['name'] === $indexName) {
                    return true;
                }
            }
            return false;
        }

        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
