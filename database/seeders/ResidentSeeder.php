<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ResidentSeeder extends Seeder
{
    /**
     * Fixed resident accounts with known credentials for development/testing.
     */
    private const FIXED_RESIDENTS = [
        [
            'user' => [
                'name'              => 'Rosa Dizon',
                'email'             => 'resident.rosa@resqmap.test',
                'password'          => 'password',
                'email_verified_at' => true,
            ],
            'profile' => [
                'contact_number' => '09171110001',
                'street_address' => '12 Mabini Street',
                'barangay'       => 'Barangay Poblacion',
                'municipality'   => 'Cebu City',
                'province'       => 'Cebu',
                'latitude'       => 10.3157000,
                'longitude'      => 123.8854000,
            ],
        ],
        [
            'user' => [
                'name'              => 'Pedro Lim',
                'email'             => 'resident.pedro@resqmap.test',
                'password'          => 'password',
                'email_verified_at' => true,
            ],
            'profile' => [
                'contact_number' => '09282220002',
                'street_address' => '45 Rizal Avenue',
                'barangay'       => 'Barangay San Isidro',
                'municipality'   => 'Davao City',
                'province'       => 'Davao del Sur',
                'latitude'       => 7.1907000,
                'longitude'      => 125.4553000,
            ],
        ],
        [
            'user' => [
                'name'              => 'Elena Vera',
                'email'             => 'resident.elena@resqmap.test',
                'password'          => 'password',
                'email_verified_at' => true,
            ],
            'profile' => [
                'contact_number' => '09393330003',
                'street_address' => '78 Quezon Boulevard',
                'barangay'       => 'Barangay Magsaysay',
                'municipality'   => 'Iloilo City',
                'province'       => 'Iloilo',
                'latitude'       => 10.6969000,
                'longitude'      => 122.5644000,
            ],
        ],
        [
            'user' => [
                'name'              => 'Fernando Cruz',
                'email'             => 'resident.fernando@resqmap.test',
                'password'          => 'password',
                'email_verified_at' => true,
            ],
            'profile' => [
                'contact_number' => '09504440004',
                'street_address' => '101 Bonifacio Street',
                'barangay'       => 'Barangay Santa Cruz',
                'municipality'   => 'Cagayan de Oro',
                'province'       => 'Misamis Oriental',
                'latitude'       => 8.4822000,
                'longitude'      => 124.6472000,
            ],
        ],
        [
            'user' => [
                'name'              => 'Luz Aguilar',
                'email'             => 'resident.luz@resqmap.test',
                'password'          => 'password',
                'email_verified_at' => false, // unverified for testing
            ],
            'profile' => [
                'contact_number' => '09615550005',
                'street_address' => '55 Luna Street',
                'barangay'       => 'Barangay Santo Niño',
                'municipality'   => 'Quezon City',
                'province'       => 'Metro Manila',
                'latitude'       => 14.6760000,
                'longitude'      => 121.0437000,
            ],
        ],
    ];

    public function run(): void
    {
        $seeded = [];

        // ── Fixed residents with known credentials ─────────────────────────
        foreach (self::FIXED_RESIDENTS as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name'              => $data['user']['name'],
                    'password'          => Hash::make($data['user']['password']),
                    'role'              => UserRole::Resident,
                    'email_verified_at' => $data['user']['email_verified_at'] ? now() : null,
                ],
            );

            if (! $user->residentProfile()->exists()) {
                $user->residentProfile()->create($data['profile']);
            }

            $seeded[] = [
                $data['user']['name'],
                $data['user']['email'],
                'password',
                $data['profile']['barangay'],
                $data['profile']['municipality'].', '.$data['profile']['province'],
                $data['user']['email_verified_at'] ? 'Verified' : 'Unverified',
            ];
        }

        // ── Random factory residents (factory creates the user automatically) ─
        ResidentProfile::factory(10)->create();

        $this->command->info('✓ Resident accounts seeded (5 fixed + 10 random = 15 total)');
        $this->command->table(
            ['Name', 'Email', 'Password', 'Barangay', 'Location', 'Email Status'],
            $seeded,
        );
    }
}
