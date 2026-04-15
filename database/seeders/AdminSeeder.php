<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'name'              => 'System Administrator',
                'email'             => 'admin@resqmap.test',
                'password'          => Hash::make('password'),
                'role'              => UserRole::Admin,
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'Operations Administrator',
                'email'             => 'ops.admin@resqmap.test',
                'password'          => Hash::make('password'),
                'role'              => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($admins as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                $data,
            );
        }

        $this->command->info('✓ Admin accounts seeded (2 accounts)');
        $this->command->table(
            ['Name', 'Email', 'Password', 'Role'],
            collect($admins)->map(fn ($a) => [$a['name'], $a['email'], 'password', 'admin'])->toArray(),
        );
    }
}
