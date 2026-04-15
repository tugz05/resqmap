<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\RescuerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RescuerSeeder extends Seeder
{
    /**
     * Fixed rescuer accounts with known credentials for development/testing.
     */
    private const FIXED_RESCUERS = [
        [
            'user' => [
                'name'              => 'Juan dela Cruz',
                'email'             => 'rescuer.juan@resqmap.test',
                'password'          => 'password',
                'email_verified_at' => true,
            ],
            'profile' => [
                'agency_name'    => 'Bureau of Fire Protection (BFP)',
                'unit_name'      => 'Station 1 - Alpha Team',
                'badge_number'   => 'BFP-0001',
                'contact_number' => '09171234567',
                'specialization' => 'Fire Rescue',
                'is_active'      => true,
            ],
        ],
        [
            'user' => [
                'name'              => 'Maria Santos',
                'email'             => 'rescuer.maria@resqmap.test',
                'password'          => 'password',
                'email_verified_at' => true,
            ],
            'profile' => [
                'agency_name'    => 'Philippine Red Cross (PRC)',
                'unit_name'      => 'Medical Response Unit',
                'badge_number'   => 'PRC-0042',
                'contact_number' => '09281234567',
                'specialization' => 'Medical Response',
                'is_active'      => true,
            ],
        ],
        [
            'user' => [
                'name'              => 'Ricardo Reyes',
                'email'             => 'rescuer.ricardo@resqmap.test',
                'password'          => 'password',
                'email_verified_at' => true,
            ],
            'profile' => [
                'agency_name'    => 'DRRM Office',
                'unit_name'      => 'Rapid Response Unit',
                'badge_number'   => 'DRRM-0015',
                'contact_number' => '09391234567',
                'specialization' => 'Search and Rescue (SAR)',
                'is_active'      => true,
            ],
        ],
        [
            'user' => [
                'name'              => 'Ana Gonzales',
                'email'             => 'rescuer.ana@resqmap.test',
                'password'          => 'password',
                'email_verified_at' => true,
            ],
            'profile' => [
                'agency_name'    => 'Philippine Coast Guard (PCG)',
                'unit_name'      => 'Water Rescue Team',
                'badge_number'   => 'PCG-0088',
                'contact_number' => '09501234567',
                'specialization' => 'Water Rescue',
                'is_active'      => true,
            ],
        ],
        [
            'user' => [
                'name'              => 'Carlos Bautista',
                'email'             => 'rescuer.carlos@resqmap.test',
                'password'          => 'password',
                'email_verified_at' => true,
            ],
            'profile' => [
                'agency_name'    => 'Philippine National Police (PNP)',
                'unit_name'      => 'Quick Reaction Force',
                'badge_number'   => 'PNP-0023',
                'contact_number' => '09611234567',
                'specialization' => 'Urban Search and Rescue (USAR)',
                'is_active'      => false, // inactive rescuer for testing
            ],
        ],
    ];

    public function run(): void
    {
        $seeded = [];

        // ── Fixed rescuers with known credentials ──────────────────────────
        foreach (self::FIXED_RESCUERS as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name'              => $data['user']['name'],
                    'password'          => Hash::make($data['user']['password']),
                    'role'              => UserRole::Rescuer,
                    'email_verified_at' => $data['user']['email_verified_at'] ? now() : null,
                ],
            );

            if (! $user->rescuerProfile()->exists()) {
                $user->rescuerProfile()->create($data['profile']);
            }

            $seeded[] = [
                $data['user']['name'],
                $data['user']['email'],
                'password',
                $data['profile']['agency_name'],
                $data['profile']['specialization'],
                $data['profile']['is_active'] ? 'Active' : 'Inactive',
            ];
        }

        // ── Random factory rescuers (factory creates the user automatically) ─
        RescuerProfile::factory(5)->create();

        $this->command->info('✓ Rescuer accounts seeded (5 fixed + 5 random = 10 total)');
        $this->command->table(
            ['Name', 'Email', 'Password', 'Agency', 'Specialization', 'Status'],
            $seeded,
        );
    }
}
