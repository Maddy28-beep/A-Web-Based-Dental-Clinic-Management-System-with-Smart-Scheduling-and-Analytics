<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teeth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('tooth_code', 8);
            $table->string('dentition', 16)->default('adult');
            $table->string('condition', 50)->default('healthy');
            $table->string('procedure', 50)->nullable();
            $table->text('notes')->nullable();
            $table->string('severity', 20)->default('healthy');
            $table->dateTime('last_recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'tooth_code']);
            $table->index(['patient_id', 'dentition']);
            $table->index(['patient_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teeth');
    }
};
