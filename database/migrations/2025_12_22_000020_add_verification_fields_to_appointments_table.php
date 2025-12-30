<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('booking_reference_code', 30)->nullable()->after('id');
            $table->dateTime('checked_in_at')->nullable()->after('status');
            $table->dateTime('in_treatment_at')->nullable()->after('checked_in_at');
            $table->dateTime('completed_at')->nullable()->after('in_treatment_at');
            $table->dateTime('cancelled_at')->nullable()->after('completed_at');
            $table->dateTime('no_show_at')->nullable()->after('cancelled_at');

            $table->unique(['booking_reference_code']);
            $table->index(['status', 'start_at']);
            $table->index(['checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['checked_in_at']);
            $table->dropIndex(['status', 'start_at']);
            $table->dropUnique(['booking_reference_code']);
            $table->dropColumn([
                'booking_reference_code',
                'checked_in_at',
                'in_treatment_at',
                'completed_at',
                'cancelled_at',
                'no_show_at',
            ]);
        });
    }
};
