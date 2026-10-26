<?php

namespace Database\Seeders;

use App\Models\Praktikum;
use Illuminate\Database\Seeder; 

class PraktikumSeeder extends Seeder
{
    public function run(): void
    {
        Praktikum::create([
            'praktikum_label' => 'Survei Terestris 1',
            'semester' => 'genap',
            'is_active' => true,
            'praktikum_slug' => 'survei-terestris-1'
        ]);
        Praktikum::create([
            'praktikum_label' => 'Survei Terestris 2',
            'semester' => 'ganjil',
            'is_active' => true,
            'praktikum_slug' => 'survei-terestris-2'
        ]);
        Praktikum::create([
            'praktikum_label' => 'Survei Rekayasa',
            'semester' => 'genap',
            'is_active' => true,
            'praktikum_slug' => 'survei-rekayasa'
        ]);
        Praktikum::create([
            'praktikum_label' => 'Survei Kadaster',
            'semester' => 'genap',
            'is_active' => true,
            'praktikum_slug' => 'survei-kadaster'
        ]);
        Praktikum::create([
            'praktikum_label' => 'Survei GNSS',
            'semester' => 'ganjil',
            'is_active' => true,
            'praktikum_slug' => 'survei-gnss'
        ]);
        Praktikum::create([
            'praktikum_label' => 'Survei Hidrografi',
            'semester' => 'genap',
            'is_active' => true,
            'praktikum_slug' => 'survei-hidrografi'
        ]);
        
    }
}