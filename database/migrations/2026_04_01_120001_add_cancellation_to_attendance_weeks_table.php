<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_weeks', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('week_date');
            $table->string('cancelled_by', 16)->nullable()->after('cancelled_at'); // rep | lecturer
            $table->text('cancellation_note')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_weeks', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancellation_note']);
        });
    }
};
