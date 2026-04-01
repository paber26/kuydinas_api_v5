<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'whatsapp')) {
                $table->string('whatsapp', 25)->nullable()->after('image');
            }

            if (!Schema::hasColumn('users', 'province_code')) {
                $table->string('province_code', 20)->nullable()->after('whatsapp');
            }

            if (!Schema::hasColumn('users', 'province_name')) {
                $table->string('province_name')->nullable()->after('province_code');
            }

            if (!Schema::hasColumn('users', 'regency_code')) {
                $table->string('regency_code', 20)->nullable()->after('province_name');
            }

            if (!Schema::hasColumn('users', 'regency_name')) {
                $table->string('regency_name')->nullable()->after('regency_code');
            }

            if (!Schema::hasColumn('users', 'district_code')) {
                $table->string('district_code', 20)->nullable()->after('regency_name');
            }

            if (!Schema::hasColumn('users', 'district_name')) {
                $table->string('district_name')->nullable()->after('district_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'whatsapp',
                'province_code',
                'province_name',
                'regency_code',
                'regency_name',
                'district_code',
                'district_name',
            ];

            $existingColumns = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn('users', $column)
            ));

            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
