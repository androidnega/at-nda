<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (SystemSetting::count() === 0) {
            SystemSetting::create([
                'enable_face_verification' => true,
                'enable_ip_binding' => true,
                'allow_multiple_index_on_device' => false,
                'face_match_threshold' => 0.50,
            ]);
        }
    }
}
