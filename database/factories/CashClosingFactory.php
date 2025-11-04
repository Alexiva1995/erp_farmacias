<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CashClosing;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashClosing>
 */
class CashClosingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
     protected $model = CashClosing::class;

    public function definition(): array
    {
        return [
            'seller_id' => User::factory(),
            'status' => $this->faker->randomElement([CashClosing::OPEN, CashClosing::CLOSED]),
            'closing_date' => $this->faker->dateTimeThisMonth(),
        ];
    }
}
