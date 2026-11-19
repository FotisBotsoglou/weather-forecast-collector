<?php

namespace App\Http\Requests;

use App\Weather\ProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForecastQueryRequest extends FormRequest
{
    public function rules(ProviderRegistry $registry): array
    {
        return [
            'provider' => ['nullable', 'string', Rule::in($registry->known())],
            'issued_on' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
