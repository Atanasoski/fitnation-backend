<?php

namespace App\Http\Requests;

use App\Rules\PlanTheActorMayChange;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkoutTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     * day_of_week commented out.
     */
    protected function prepareForValidation(): void
    {
        // if ($this->has('day_of_week') && $this->day_of_week === '') {
        //     $this->merge(['day_of_week' => null]);
        // }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => [
                'nullable',
                'exists:plans,id',
                new PlanTheActorMayChange,
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            // 'day_of_week' => 'nullable|integer|min:0|max:6',
        ];
    }
}
