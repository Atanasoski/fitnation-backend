<?php

namespace App\Http\Requests;

use App\Models\WorkoutTemplate;
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
                'required',
                'exists:plans,id',
                new PlanTheActorMayChange,
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'week_number' => 'nullable|integer|min:1|max:52',
            // day_of_week (commented out)
            // 'day_of_week' => [
            //     'nullable',
            //     'integer',
            //     'min:0',
            //     'max:6',
            //     function (string $attribute, mixed $value, \Closure $fail): void {
            //         if ($value === null) {
            //             return;
            //         }
            //         $plan = $this->route('plan');
            //         if (! $plan) {
            //             return;
            //         }
            //         $existing = WorkoutTemplate::where('plan_id', $plan->id)
            //             ->where('day_of_week', (int) $value)
            //             ->first();
            //         if ($existing) {
            //             $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            //             $dayName = $dayNames[(int) $value] ?? 'Day '.$value;
            //             $fail($dayName.' is already assigned to \''.$existing->name.'\'.');
            //         }
            //     },
            // ],
        ];
    }
}
