<?php

namespace Database\Seeders;

use App\Models\Commune;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CommuneSeeder extends Seeder
{
    public function run(): void
    {
        $map = (array) config('chile.comunas_por_region', []);

        foreach ($map as $regionCode => $names) {
            foreach ((array) $names as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }

                Commune::firstOrCreate(
                    ['name' => $name, 'region_code' => $regionCode],
                    [] // no extra fields
                );
            }
        }
    }
}
