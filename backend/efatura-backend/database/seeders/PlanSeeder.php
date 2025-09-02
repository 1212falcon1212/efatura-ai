<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['code' => 'basic'], [
            'name' => 'Aylık Paket',
            'price_try' => 549,
            'limits' => ['invoices_per_month' => 1000],
            'active' => true,
        ]);
    }
}


