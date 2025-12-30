<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('sku')->nullable();
            $table->string('unit', 20)->default('pcs');
            $table->decimal('current_stock', 12, 2)->default(0);
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->unsignedInteger('cost_per_unit_cents')->default(0);
            $table->foreignId('preferred_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->dateTime('last_purchase_at')->nullable();
            $table->decimal('last_purchase_qty', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'name']);
            $table->index(['preferred_supplier_id']);
            $table->index(['current_stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
