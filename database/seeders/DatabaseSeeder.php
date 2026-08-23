<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if ((bool) config('platform.showcase_content', false)) {
            $this->call(ShowcaseContentSeeder::class);
        }
    }
}
