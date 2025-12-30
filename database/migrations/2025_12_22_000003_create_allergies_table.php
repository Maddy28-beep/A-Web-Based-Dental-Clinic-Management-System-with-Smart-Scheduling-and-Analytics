<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('tag');
            $table->string('severity')->default('mild');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->dateTime('recorded_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'is_active']);
            $table->index(['patient_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergies');
    }
};
