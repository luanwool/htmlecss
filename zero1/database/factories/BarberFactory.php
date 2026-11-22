<?php

namespace Database\Factories;

use App\Models\Barber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Barber> */
class BarberFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Profissional '.Str::random(5);

        return ['display_name' => $name, 'slug' => Str::slug($name), 'status' => 'active', 'is_public' => true];
    }
}
