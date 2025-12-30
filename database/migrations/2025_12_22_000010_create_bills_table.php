<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('unpaid');
            $table->string('currency', 10)->default('PHP');
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('add_ons_cents')->default(0);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->unsignedInteger('paid_cents')->default(0);
            $table->unsignedInteger('balance_cents')->default(0);
            $table->dateTime('locked_at')->nullable();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
            $table->index(['status', 'due_at']);
            $table->index(['visit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
