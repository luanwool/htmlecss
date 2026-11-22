<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Serviço '.Str::random(6);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'price_cents' => 5000,
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
            'is_public' => true,
            'is_active' => true,
        ];
    }
}
