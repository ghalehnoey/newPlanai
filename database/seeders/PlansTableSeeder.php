<?php

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlansTableSeeder extends Seeder
{
    public function run()
    {
        Plan::create(['name' => 'استارتر',     'price' => 100000, 'questions_limit' => 300]);
        Plan::create(['name' => 'حرفه‌ای',     'price' => 200000, 'questions_limit' => 700]);
        Plan::create(['name' => 'نامحدود',     'price' => 500000, 'questions_limit' => 99999]);
    }
}