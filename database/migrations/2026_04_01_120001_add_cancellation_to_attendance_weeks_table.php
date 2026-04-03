<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_weeks', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_weeks', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('week_date');
            }

            if (! Schema::hasColumn('attendance_weeks', 'cancelled_by')) {
                $table->string('cancelled_by', 16)->nullable()->after('cancelled_at'); // rep | lecturer
            }

            if (! Schema::hasColumn('attendance_weeks', 'cancellation_note')) {
                $table->text('cancellation_note')->nullable()->after('cancelled_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_weeks', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('attendance_weeks', 'cancelled_at')) {
                $drops[] = 'cancelled_at';
            }
            if (Schema::hasColumn('attendance_weeks', 'cancelled_by')) {
                $drops[] = 'cancelled_by';
            }
            if (Schema::hasColumn('attendance_weeks', 'cancellation_note')) {
                $drops[] = 'cancellation_note';
            }

            if (! empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
