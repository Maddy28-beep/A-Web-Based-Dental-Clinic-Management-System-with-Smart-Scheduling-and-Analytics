<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tooth_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tooth_id')->constrained('teeth')->cascadeOnDelete();
            $table->string('condition', 50);
            $table->string('procedure', 50)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('recorded_at');
            $table->string('image_before_path')->nullable();
            $table->string('image_after_path')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tooth_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tooth_histories');
    }
};
