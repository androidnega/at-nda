<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances') || ! Schema::hasColumn('attendances', 'status')) {
            return;
        }

        DB::table('attendances')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '');
            })
            ->update(['status' => 'present']);

        DB::table('attendances')
            ->where('status', 'absent')
            ->whereNotNull('check_in_time')
            ->update(['status' => 'late']);
    }

    public function down(): void
    {
        // Non-reversible data normalization.
    }
};
