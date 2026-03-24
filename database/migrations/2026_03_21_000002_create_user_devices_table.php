<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_devices')) {
            return;
        }

        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device');
            $table->string('device_type')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
