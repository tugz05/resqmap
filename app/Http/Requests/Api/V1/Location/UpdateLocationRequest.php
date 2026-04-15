<?php

namespace App\Http\Requests\Api\V1\Location;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy'  => ['nullable', 'numeric', 'min:0'],
            'altitude'  => ['nullable', 'numeric'],
            'heading'   => ['nullable', 'numeric', 'between:0,360'],
            'speed'     => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
