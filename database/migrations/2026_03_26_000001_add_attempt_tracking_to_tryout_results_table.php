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

        Schema::table('tryout_results', function (Blueprint $table) {
            if (!Schema::hasColumn('tryout_results', 'attempt_number')) {
                $table->unsignedInteger('attempt_number')->default(1)->after('tryout_id');
            }

            if (!Schema::hasColumn('tryout_results', 'status')) {
                $table->string('status', 30)->default('in_progress')->after('correct_answer');
            }

            if (!Schema::hasColumn('tryout_results', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('started_at');
            }
        });

        // SQLite does not support MySQL-style UPDATE with JOIN and table alias.
        // Skip the data backfill on SQLite (used in testing); run it only on MySQL/MariaDB.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                UPDATE tryout_results tr
                LEFT JOIN tryout_registrations reg
                    ON reg.user_id = tr.user_id
                    AND reg.tryout_id = tr.tryout_id
                SET
                    tr.attempt_number = COALESCE(tr.attempt_number, 1),
                    tr.status = CASE
                        WHEN reg.status = 'completed' THEN 'completed'
                        ELSE 'in_progress'
                    END,
                    tr.finished_at = CASE
                        WHEN reg.status = 'completed' THEN reg.finished_at
                        ELSE NULL
                    END
            ");
        }

        if ($this->indexExists('tryout_results', 'tryout_results_user_id_tryout_id_unique')) {
            Schema::table('tryout_results', function (Blueprint $table) {
                $table->dropUnique('tryout_results_user_id_tryout_id_unique');
            });
        }

        if (!$this->indexExists('tryout_results', 'tryout_results_user_tryout_attempt_unique')) {
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

        if (!$this->indexExists('tryout_results', 'tryout_results_user_id_tryout_id_unique')) {
            Schema::table('tryout_results', function (Blueprint $table) {
                $table->unique(['user_id', 'tryout_id']);
            });
        }

        Schema::table('tryout_results', function (Blueprint $table) {
            if (Schema::hasColumn('tryout_results', 'finished_at')) {
                $table->dropColumn('finished_at');
            }

            if (Schema::hasColumn('tryout_results', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('tryout_results', 'attempt_number')) {
                $table->dropColumn('attempt_number');
            }
        });
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
