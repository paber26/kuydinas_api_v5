<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_tryout_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bundle_id')->constrained('bundles')->cascadeOnDelete();
            // tryout asli di bundle yang diganti
            $table->foreignId('original_tryout_id')->constrained('tryouts')->cascadeOnDelete();
            // tryout pengganti yang dipilih user
            $table->foreignId('replacement_tryout_id')->constrained('tryouts')->cascadeOnDelete();
            $table->timestamps();

            // satu user hanya bisa swap satu kali per tryout per bundle
            $table->unique(['user_id', 'bundle_id', 'original_tryout_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_tryout_swaps');
    }
};
