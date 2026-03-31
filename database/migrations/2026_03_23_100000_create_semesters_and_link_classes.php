<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->string('year_label', 32)->comment('e.g. 2024/2025');
            $table->unsignedTinyInteger('term')->comment('1 or 2');
            $table->string('label', 128)->nullable()->comment('Optional display override');
            $table->timestamps();
            $table->unique(['year_label', 'term']);
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('level')->constrained('semesters')->nullOnDelete();
        });

        $y = (int) date('Y');
        $y2 = $y + 1;
        $label = $y.'/'.$y2;
        $now = now();
        DB::table('semesters')->insert([
            ['year_label' => $label, 'term' => 1, 'label' => null, 'created_at' => $now, 'updated_at' => $now],
            ['year_label' => $label, 'term' => 2, 'label' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Normalize legacy level 1–4 → 100–400 where applicable
        foreach ([1 => 100, 2 => 200, 3 => 300, 4 => 400] as $old => $new) {
            DB::table('classes')->where('level', $old)->update(['level' => $new]);
        }
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('semester_id');
        });
        Schema::dropIfExists('semesters');
    }
};
