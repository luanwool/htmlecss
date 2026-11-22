<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MembershipPlan> */
class MembershipPlanFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Plano '.Str::random(5);

        return [
            'name' => $name, 'slug' => Str::slug($name), 'price_cents' => 9900,
            'usage_mode' => 'limited_global', 'uses_per_cycle' => 4, 'is_published' => true,
        ];
    }
}
