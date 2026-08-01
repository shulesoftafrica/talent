<?php

namespace Database\Seeders;

use App\Models\VerificationType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VerificationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['key' => 'identity', 'name' => 'Identity', 'default_payer' => 'candidate', 'default_price' => 4, 'sort_order' => 1],
            ['key' => 'education', 'name' => 'Education', 'default_payer' => 'candidate', 'default_price' => 12, 'sort_order' => 2],
            ['key' => 'professional_license', 'name' => 'Professional License', 'default_payer' => 'candidate', 'default_price' => 5, 'sort_order' => 3],
            ['key' => 'employment_history', 'name' => 'Employment History', 'default_payer' => 'candidate', 'default_price' => 14, 'sort_order' => 4],
            ['key' => 'background_check', 'name' => 'Background / Criminal Clearance', 'default_payer' => 'school', 'default_price' => 10, 'sort_order' => 5],
            ['key' => 'references', 'name' => 'References', 'default_payer' => 'candidate', 'default_price' => 6, 'sort_order' => 6],
        ];

        foreach ($types as $type) {
            VerificationType::updateOrCreate(['key' => $type['key']], $type);
        }
    }
}
