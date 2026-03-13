<?php

namespace App\Http\Requests;

use App\Enums\PlanType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdatePlanRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpeg,png,webp|max:5120',
            'is_active' => 'nullable|boolean',
            'type' => ['required', Rule::enum(PlanType::class)],
            'duration_weeks' => 'nullable|integer|min:1|max:52',
        ];
    }

    /**
     * Handle a failed validation attempt (partner library: redirect to index with edit_plan_id).
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->routeIs('partner.programs.update')) {
            $plan = $this->route('plan');

            session()->flash('edit_plan_id', $plan->id);

            throw (new ValidationException($validator))
                ->redirectTo(route('partner.programs.show', $plan));
        }

        parent::failedValidation($validator);
    }
}
