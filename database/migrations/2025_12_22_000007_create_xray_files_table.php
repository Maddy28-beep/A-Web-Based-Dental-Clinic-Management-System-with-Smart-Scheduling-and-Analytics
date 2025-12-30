<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tooth_code')->nullable();
            $table->string('original_name');
            $table->string('mime_type');
            $table->integer('size_bytes')->default(0);
            $table->string('encrypted_path');
            $table->dateTime('recorded_at')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at']);
            $table->index(['visit_id']);
            $table->index(['procedure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_files');
    }
};
