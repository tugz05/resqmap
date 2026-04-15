<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * All fixed accounts use the password: password
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            RescuerSeeder::class,
            ResidentSeeder::class,
        ]);
    }
}
