<?php

declare(strict_types=1);

namespace App\Http\Requests\Restaurant;

use App\Enums\KitchenStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateKitchenStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(KitchenStatusEnum::class)],
        ];
    }
}
