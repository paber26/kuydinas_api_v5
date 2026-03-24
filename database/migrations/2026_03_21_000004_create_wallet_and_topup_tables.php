<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('topup_packages')) {
            Schema::create('topup_packages', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->unsignedBigInteger('coin_amount');
                $table->unsignedBigInteger('bonus_coin')->default(0);
                $table->unsignedBigInteger('price');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('topup_transactions')) {
            Schema::create('topup_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('topup_package_id')->constrained('topup_packages')->restrictOnDelete();
                $table->string('order_id', 100)->unique();
                $table->string('gateway', 50)->default('midtrans');
                $table->string('snap_token')->nullable();
                $table->text('redirect_url')->nullable();
                $table->unsignedBigInteger('gross_amount');
                $table->unsignedBigInteger('coin_amount');
                $table->unsignedBigInteger('bonus_coin')->default(0);
                $table->string('status', 30)->default('pending');
                $table->string('transaction_status', 50)->nullable();
                $table->string('fraud_status', 50)->nullable();
                $table->string('payment_type', 50)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->json('raw_response')->nullable();
                $table->json('raw_notification')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index('topup_package_id');
            });
        }

        if (!Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type', 50);
                $table->unsignedBigInteger('amount');
                $table->unsignedBigInteger('coin_amount')->default(0);
                $table->unsignedBigInteger('balance_before');
                $table->unsignedBigInteger('balance_after');
                $table->string('reference_type', 100)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index(['reference_type', 'reference_id']);
            });
        }

        if (Schema::hasTable('topup_packages') && DB::table('topup_packages')->count() === 0) {
            DB::table('topup_packages')->insert([
                [
                    'name' => 'Paket 1',
                    'coin_amount' => 100,
                    'bonus_coin' => 0,
                    'price' => 10000,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Paket 2',
                    'coin_amount' => 250,
                    'bonus_coin' => 50,
                    'price' => 20000,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Paket 3',
                    'coin_amount' => 400,
                    'bonus_coin' => 120,
                    'price' => 30000,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Paket 4',
                    'coin_amount' => 550,
                    'bonus_coin' => 200,
                    'price' => 40000,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('topup_transactions');
        Schema::dropIfExists('topup_packages');
    }
};
