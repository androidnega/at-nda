<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('course_reps');
    }

    public function down(): void
    {
        // Legacy `course_reps` removed; restore from git history / older migrations if needed.
    }
};
