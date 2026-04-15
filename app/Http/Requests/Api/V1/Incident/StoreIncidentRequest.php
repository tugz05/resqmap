<?php

namespace App\Http\Requests\Api\V1\Incident;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::enum(IncidentType::class)],
            'severity'    => ['required', Rule::enum(IncidentSeverity::class)],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],

            'latitude'    => ['required', 'numeric', 'between:-90,90'],
            'longitude'   => ['required', 'numeric', 'between:-180,180'],
            'address'     => ['nullable', 'string', 'max:500'],
            'barangay'    => ['nullable', 'string', 'max:255'],
            'city'        => ['nullable', 'string', 'max:255'],
            'province'    => ['nullable', 'string', 'max:255'],

            'photos'      => ['nullable', 'array', 'max:5'],
            'photos.*'    => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
