<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_materials', function (Blueprint $table) {
            $table->id();
            $table->string('procedure_type', 100);
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->boolean('is_per_tooth')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['procedure_type', 'inventory_item_id']);
            $table->index(['procedure_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_materials');
    }
};
