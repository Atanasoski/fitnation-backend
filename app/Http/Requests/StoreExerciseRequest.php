<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreExerciseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->hasRole('admin') && $user->partner === null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => [
                'required',
                'exists:categories,id',
                function ($attribute, $value, $fail) {
                    $category = Category::find($value);
                    if ($category && $category->type !== CategoryType::Workout) {
                        $fail('The selected category must be a workout category.');
                    }
                },
            ],
            'movement_pattern_id' => ['required', 'exists:movement_patterns,id'],
            'target_region_id' => ['required', 'exists:target_regions,id'],
            'equipment_type_id' => ['required', 'exists:equipment_types,id'],
            'angle_id' => ['nullable', 'exists:angles,id'],
            'image' => ['nullable', 'string', 'max:255'],
            'default_rest_sec' => ['nullable', 'integer', 'min:0'],
            'primary_muscle_group_ids' => ['nullable', 'array'],
            'primary_muscle_group_ids.*' => ['exists:muscle_groups,id'],
            'secondary_muscle_group_ids' => ['nullable', 'array'],
            'secondary_muscle_group_ids.*' => ['exists:muscle_groups,id'],
            'training_style_ids' => ['nullable', 'array'],
            'training_style_ids.*' => ['exists:training_styles,id'],
        ];
    }
}
