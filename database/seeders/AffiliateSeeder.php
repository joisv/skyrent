<?php

namespace Database\Seeders;

use App\Models\Affiliate;
use App\Models\Iphones;
use Illuminate\Database\Seeder;

class AffiliateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $affiliates = [
            [
                'code' => 'BWI',
                'name' => 'Affiliate Banyuwangi Kota',
                'slug' => 'affiliate-banyuwangi-kota',
                'email' => 'banyuwangi@skyrent.id',
                'phone' => '0812-9876-1122',
                'address' => 'Jl. Ahmad Yani No. 88, Rogojampi',
                'city' => 'Banyuwangi',
                'province' => 'Jawa Timur',
                'postal_code' => '68411',
                'description' => 'Mitra cabang operasional Banyuwangi melayani area kota & wisata.',
                'is_active' => true,
            ],
            [
                'code' => 'SLO',
                'name' => 'Affiliate Solo Balapan',
                'slug' => 'affiliate-solo-balapan',
                'email' => 'solo@skyrent.id',
                'phone' => '0813-8877-3344',
                'address' => 'Jl. Slamet Riyadi No. 120, Surakarta',
                'city' => 'Surakarta',
                'province' => 'Jawa Tengah',
                'postal_code' => '57131',
                'description' => 'Mitra operasional area Solo dan sekitarnya.',
                'is_active' => true,
            ],
            [
                'code' => 'MLG',
                'name' => 'Affiliate Malang Ijen',
                'slug' => 'affiliate-malang-ijen',
                'email' => 'malang@skyrent.id',
                'phone' => '0811-2233-4455',
                'address' => 'Jl. Besar Ijen No. 45, Klojen',
                'city' => 'Malang',
                'province' => 'Jawa Timur',
                'postal_code' => '65115',
                'description' => 'Cabang kemitraan mahasiswa & wisatawan Malang Raya.',
                'is_active' => true,
            ],
        ];

        foreach ($affiliates as $data) {
            $aff = Affiliate::firstOrCreate(
                ['code' => $data['code']],
                $data
            );

            // Assign at least 1 unit iPhone to the first affiliate if available
            if ($data['code'] === 'BWI' && $aff->iphones()->count() === 0) {
                $iphone = Iphones::whereNull('affiliate_id')->first();
                if ($iphone) {
                    $iphone->update(['affiliate_id' => $aff->id]);
                }
            }
        }
    }
}
