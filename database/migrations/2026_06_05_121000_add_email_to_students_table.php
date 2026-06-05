<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        if (! Schema::hasColumn('students', 'email')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('email')->nullable()->after('last_name');
                $table->index('email', 'students_email_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('students') || ! Schema::hasColumn('students', 'email')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_email_index');
            $table->dropColumn('email');
        });
    }
};
