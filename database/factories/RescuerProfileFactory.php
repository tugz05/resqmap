<?php

namespace Database\Factories;

use App\Models\RescuerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RescuerProfile>
 */
class RescuerProfileFactory extends Factory
{
    private const AGENCIES = [
        'Bureau of Fire Protection (BFP)',
        'Philippine Red Cross (PRC)',
        'Philippine National Police (PNP)',
        'DRRM Office',
        'LGU Emergency Response Team',
        'Philippine Coast Guard (PCG)',
        'Armed Forces of the Philippines (AFP)',
        'Department of Social Welfare and Development (DSWD)',
    ];

    private const SPECIALIZATIONS = [
        'Fire Rescue',
        'Medical Response',
        'Search and Rescue (SAR)',
        'Water Rescue',
        'Hazmat Response',
        'Trauma Care',
        'Urban Search and Rescue (USAR)',
        'Mountain Rescue',
    ];

    private const UNIT_NAMES = [
        'Alpha Team',
        'Bravo Team',
        'Charlie Team',
        'Delta Team',
        'Echo Team',
        'Station 1',
        'Station 2',
        'Rapid Response Unit',
        'Quick Reaction Force',
    ];

    public function definition(): array
    {
        return [
            'user_id'        => User::factory()->rescuer(),
            'agency_name'    => fake()->randomElement(self::AGENCIES),
            'unit_name'      => fake()->randomElement(self::UNIT_NAMES),
            'badge_number'   => strtoupper(fake()->bothify('??-####')),
            'contact_number' => fake()->numerify('09#########'),
            'specialization' => fake()->randomElement(self::SPECIALIZATIONS),
            'is_active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
