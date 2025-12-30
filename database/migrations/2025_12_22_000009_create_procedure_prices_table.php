<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_prices', function (Blueprint $table) {
            $table->id();
            $table->string('procedure_type');
            $table->foreignId('dentist_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('base_price_cents');
            $table->unsignedInteger('per_tooth_cents')->default(0);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['procedure_type']);
            $table->index(['dentist_id']);
            $table->index(['procedure_type', 'dentist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_prices');
    }
};
