<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('procedure_type');
            $table->string('description')->nullable();
            $table->integer('cost_cents')->nullable();
            $table->dateTime('performed_at');
            $table->json('requires_allergy_tags')->nullable();
            $table->json('allergy_conflicts')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'performed_at']);
            $table->index(['visit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
