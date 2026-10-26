<?php

namespace Database\Seeders;

use App\Models\Dosen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DosenSeeder extends Seeder
{
    // [nama, nip, kepakaran] — surel & password placeholder, pengampu diisi manual nanti
    private array $dosen = [
        ['Prof. Dr. Ir. Sari Bahagiarti Kusumayudha., M.Sc.', '195612191984112001', ['Analisis Sumber Daya Mineral']],
        ['Prof. Dr. Ir. Eko Teguh Paripurno, M.T.', '196206031988031001', ['Mitigasi Bencana']],
        ['Dr. Ir. H. Bambang Kuncoro Prasongko, M.T.', '195902261991031001', ['Penginderaan Jauh']],
        ['Dr. Ir. Jatmika Setiawan, M.T.', '196404111993031001', ['Perpetaan']],
        ['Dr. Ir. Sugeng Raharjo, M.T.', '195812081992031001', ['Perpetaan']],
        ['Dr. Ir. Barlian Dwinagara, M.T.', '196407061990081001', ['Pemetaan Sumber Daya Mineral']],
        ['Ir. Ediyanto, M.T.', '196003311992031001', ['Analisis Potensi Mineral']],
        ['Dr. Arif Rianto Budi Nugroho, S.T., M.Si.', '197112092021211003', ['Penginderaan Jauh', 'Manajemen Kebencanaan']],
        ['Oktavia Dewi Alfiani, ST., MT.', '198510112019032015', ['Sistem Informasi Geografis', 'Kartografi']],
        ['Dr. Herry Riswandi, S.T., M.T.', '198201302021211004', ['Penginderaan Jauh']],
        ['Ir. Dessy Apriyanti, S.T., M.Eng.', '199204222022032012', ['Survei Terestris', 'Geodesi Murni']],
        ['Ir. Lysa Dora Ayu Nugraini, S.T., M.T.', '198906022022032008', ['Penginderaan Jauh', 'Survei Geodesi']],
        ['Ir. Monica Maharani, S.T., M.Eng.', '199404282022032016', ['Fotogrametri', 'Pemodelan 3D', 'Computer Vision']],
        ['Dwi Wahyuningrum, S.T., M.Eng.', '199208312022032011', ['Sistem Informasi Geografi', 'Kartografi', 'Geoinformatika']],
        ['Maulana Yudinugroho, S.Si., M.Eng.', '199508092022031012', ['Penginderaan Jauh', 'Peta Tematik Kebumian']],
        ['Naufal Setiawan, S.T., M.Sc.', '198909252022031005', ['Penginderaan Jauh', 'GNSS']],
        ['Anugerah Satria Pradana Budiyono, S.T., M.Eng.', '199611222024061002', ['Kadaster', 'Sistem Informasi Pertanahan']],
        ['Ririn Wuri Rahayu, S.T., M.T.', '199609042024062001', ['GNSS Seismology and Volcanology']],
        ['Dr. Muhammad Syahrullah Fathulhuda, S.T., M.T', '900000001', ['Geodesi dan Geomatika Kelautan']],
        ['Adistina Lailia Fajar Dewi, S.Si., M.T', '900000002', ['Penginderaan Jauh']],
        ['Arizal Bawasir, S.T., M.T.', '900000003', ['GNSS Interferometric Reflectometry', 'GNSS Meteorology']],
        ['S R Bening Pratiwi K, S.Si., M.T', '900000004', ['Sistem Informasi Geografis']],
        ['Wening Aisyah Fauziana Koman, S.T., M.Eng.', '900000005', ['Environmental Geomatics']],
    ];

    public function run(): void
    {
        // updateOrCreate: nip ini juga ada di database/data/user_dosen.json (DataSeeder)
        Dosen::updateOrCreate([
            'nip' => '117190045',
        ], [
            'nama' => 'M Rouf Indhra Dewa Sambodo',
            'surel' => 'muhammad.rouf94@gmail.com',
            'kepakaran' => [],
            'pengampu' => ['Survei Terestris 2'],
            'password' => 'password',
            'is_active' => true,
        ]);

        foreach ($this->dosen as [$nama, $nip, $kepakaran]) {
            Dosen::updateOrCreate([
                'nip' => $nip,
            ], [
                'nama' => $nama,
                'surel' => Str::slug($nama).'@todo.local',
                'kepakaran' => $kepakaran,
                'pengampu' => [],
                'password' => 'password',
                'is_active' => true,
            ]);
        }
    }
}
