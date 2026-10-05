<?php

namespace App\Http\Requests;

use App\Rules\PlanTheActorMayChange;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkoutTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
                'required',
                'exists:plans,id',
                new PlanTheActorMayChange,
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'week_number' => 'nullable|integer|min:1|max:52',
            // A day of the week, Monday 0 to Sunday 6; null is any day.
            'day_of_week' => 'nullable|integer|min:0|max:6',
        ];
    }
}
