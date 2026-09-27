<?php

namespace Database\Seeders;

use App\Enums\CountryStatus;
use App\Enums\Role;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin account from .env (ADMIN_EMAIL / ADMIN_PASSWORD), so no credentials live in the code.
        if (filled(env('ADMIN_EMAIL')) && filled(env('ADMIN_PASSWORD'))) {
            User::updateOrCreate(
                ['email' => env('ADMIN_EMAIL')],
                ['name' => env('ADMIN_NAME', 'Admin'), 'password' => env('ADMIN_PASSWORD'), 'role' => Role::Admin],
            );
        } else {
            $this->command->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set in .env: no admin user created.');
        }

        // Only the countries explicitly named in the rough direction. All tentative: the route is not final.
        $countries = [
            ['NL', 'Netherlands', 'Nederland'],
            ['DE', 'Germany', 'Duitsland'],
            ['TR', 'Turkey', 'Turkije'],
            ['CN', 'China', 'China'],
            ['VN', 'Vietnam', 'Vietnam'],
        ];

        foreach ($countries as $i => [$iso, $en, $nl]) {
            Country::firstOrCreate(['iso_code' => $iso], [
                'name' => ['en' => $en, 'nl' => $nl],
                'status' => CountryStatus::Tentative,
                'sort_order' => ($i + 1) * 10,
            ]);
        }
    }
}
