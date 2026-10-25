<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DataSeeder::class,
            DosenSeeder::class,
            PraktikumSeeder::class,
            AlatSeeder::class,
        ]);
    }
}