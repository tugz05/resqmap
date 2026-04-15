<?php

namespace App\Http\Requests\Api\V1\Incident;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRescuerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rescuer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Rescuer->value),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
