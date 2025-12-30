<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('procedure_type')->nullable();
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('tooth_count')->default(1);
            $table->unsignedInteger('base_price_cents')->default(0);
            $table->unsignedInteger('add_ons_cents')->default(0);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('override_price_cents')->nullable();
            $table->unsignedInteger('total_cents')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['bill_id']);
            $table->index(['procedure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
    }
};
