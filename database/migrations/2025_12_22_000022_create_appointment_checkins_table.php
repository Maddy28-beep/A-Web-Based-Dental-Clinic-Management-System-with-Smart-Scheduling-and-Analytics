<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->dateTime('checked_in_at');
            $table->foreignId('checked_in_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 30)->default('reference_code');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['appointment_id']);
            $table->index(['checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_checkins');
    }
};
