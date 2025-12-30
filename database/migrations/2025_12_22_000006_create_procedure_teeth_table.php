<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_teeth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained()->cascadeOnDelete();
            $table->string('tooth_code');
            $table->json('surfaces')->nullable();
            $table->timestamps();

            $table->unique(['procedure_id', 'tooth_code']);
            $table->index(['tooth_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_teeth');
    }
};
