<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bundles')) {
            Schema::create('bundles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('price');
                $table->string('cover_image')->nullable();
                $table->enum('limit_type', ['time', 'quota'])->default('quota');
                $table->unsignedInteger('limit_quota')->nullable();
                $table->date('limit_start_date')->nullable();
                $table->date('limit_end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bundle_tryout')) {
            Schema::create('bundle_tryout', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bundle_id')->constrained('bundles')->cascadeOnDelete();
                $table->foreignId('tryout_id')->constrained('tryouts')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['bundle_id', 'tryout_id']);
            });
        }

        if (!Schema::hasTable('bundle_transactions')) {
            Schema::create('bundle_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('bundle_id')->constrained('bundles')->restrictOnDelete();
                $table->string('order_id', 100)->unique();
                $table->string('gateway', 50)->default('midtrans');
                $table->string('snap_token')->nullable();
                $table->text('redirect_url')->nullable();
                $table->unsignedBigInteger('gross_amount');
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
                $table->index('bundle_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_transactions');
        Schema::dropIfExists('bundle_tryout');
        Schema::dropIfExists('bundles');
    }
};
