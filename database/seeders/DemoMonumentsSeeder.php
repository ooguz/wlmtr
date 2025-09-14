<?php

namespace Database\Seeders;

use App\Models\Monument;
use Illuminate\Database\Seeder;

class DemoMonumentsSeeder extends Seeder
{
    /**
     * Seed a few demo monuments with coordinates in Turkey.
     */
    public function run(): void
    {
        $monuments = [
            [
                'wikidata_id' => 'Q406',
                'name_tr' => 'Ayasofya',
                'description_tr' => 'İstanbul, Sultanahmet’te yer alan tarihi yapı.',
                'latitude' => 41.008583,
                'longitude' => 28.980175,
                'city' => 'Fatih',
                'province' => 'İstanbul',
                'country' => 'Turkey',
                'wikidata_url' => 'https://www.wikidata.org/wiki/Q406',
            ],
            [
                'wikidata_id' => 'Q12560',
                'name_tr' => 'Anıtkabir',
                'description_tr' => 'Mustafa Kemal Atatürk’ün anıt mezarı.',
                'latitude' => 39.925533,
                'longitude' => 32.836944,
                'city' => 'Çankaya',
                'province' => 'Ankara',
                'country' => 'Turkey',
                'wikidata_url' => 'https://www.wikidata.org/wiki/Q12560',
            ],
            [
                'wikidata_id' => 'Q181954',
                'name_tr' => 'Efes Antik Kenti',
                'description_tr' => 'İzmir Selçuk yakınlarında antik kent.',
                'latitude' => 37.939167,
                'longitude' => 27.341667,
                'city' => 'Selçuk',
                'province' => 'İzmir',
                'country' => 'Turkey',
                'wikidata_url' => 'https://www.wikidata.org/wiki/Q181954',
            ],
            [
                'wikidata_id' => 'Q132783',
                'name_tr' => 'Nemrut Dağı',
                'description_tr' => 'Adıyaman’da antik heykelleri ile ünlü dağ.',
                'latitude' => 37.980000,
                'longitude' => 38.740000,
                'city' => 'Kahta',
                'province' => 'Adıyaman',
                'country' => 'Turkey',
                'wikidata_url' => 'https://www.wikidata.org/wiki/Q132783',
            ],
        ];

        foreach ($monuments as $data) {
            Monument::updateOrCreate(
                ['wikidata_id' => $data['wikidata_id']],
                array_merge($data, [
                    'has_photos' => false,
                    'photo_count' => 0,
                    'last_synced_at' => now(),
                ])
            );
        }
    }
}
