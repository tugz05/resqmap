<?php

namespace Database\Factories;

use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentProfile>
 */
class ResidentProfileFactory extends Factory
{
    private const BARANGAYS = [
        'Barangay Poblacion',
        'Barangay San Isidro',
        'Barangay San Jose',
        'Barangay Santa Cruz',
        'Barangay Santo Niño',
        'Barangay Bagong Silang',
        'Barangay Palo Alto',
        'Barangay Mabini',
        'Barangay Rizal',
        'Barangay Magsaysay',
        'Barangay Quezon',
        'Barangay Luna',
    ];

    private const MUNICIPALITIES = [
        ['name' => 'Cebu City',         'province' => 'Cebu',         'lat' => 10.3157, 'lng' => 123.8854],
        ['name' => 'Davao City',        'province' => 'Davao del Sur', 'lat' => 7.1907,  'lng' => 125.4553],
        ['name' => 'Quezon City',       'province' => 'Metro Manila',  'lat' => 14.6760, 'lng' => 121.0437],
        ['name' => 'Makati',            'province' => 'Metro Manila',  'lat' => 14.5547, 'lng' => 121.0244],
        ['name' => 'Iloilo City',       'province' => 'Iloilo',        'lat' => 10.6969, 'lng' => 122.5644],
        ['name' => 'Cagayan de Oro',    'province' => 'Misamis Oriental','lat' => 8.4822, 'lng' => 124.6472],
        ['name' => 'Bacolod',           'province' => 'Negros Occidental','lat' => 10.6765,'lng' => 122.9509],
        ['name' => 'General Santos',    'province' => 'South Cotabato', 'lat' => 6.1164,  'lng' => 125.1716],
        ['name' => 'Zamboanga City',    'province' => 'Zamboanga del Sur','lat' => 6.9214,'lng' => 122.0790],
        ['name' => 'Pasig',             'province' => 'Metro Manila',  'lat' => 14.5764, 'lng' => 121.0851],
    ];

    public function definition(): array
    {
        $location = fake()->randomElement(self::MUNICIPALITIES);

        // Small coordinate jitter so each resident is at a slightly different spot
        $latJitter = fake()->randomFloat(4, -0.05, 0.05);
        $lngJitter = fake()->randomFloat(4, -0.05, 0.05);

        return [
            'user_id'        => User::factory()->resident(),
            'contact_number' => fake()->numerify('09#########'),
            'street_address' => fake()->numerify('### ') . fake()->streetName(),
            'barangay'       => fake()->randomElement(self::BARANGAYS),
            'municipality'   => $location['name'],
            'province'       => $location['province'],
            'latitude'       => round($location['lat'] + $latJitter, 7),
            'longitude'      => round($location['lng'] + $lngJitter, 7),
        ];
    }

    /**
     * Resident without a known location (coordinates null).
     */
    public function withoutLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude'  => null,
            'longitude' => null,
        ]);
    }
}
