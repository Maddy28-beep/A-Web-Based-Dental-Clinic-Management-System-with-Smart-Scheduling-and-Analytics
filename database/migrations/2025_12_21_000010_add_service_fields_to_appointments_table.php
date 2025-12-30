<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('dentist_id')->constrained('services')->nullOnDelete();
            $table->unsignedSmallInteger('service_duration_minutes')->nullable()->after('service_id');
            $table->unsignedSmallInteger('buffer_minutes')->default(0)->after('service_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn(['service_duration_minutes', 'buffer_minutes']);
        });
    }
};
