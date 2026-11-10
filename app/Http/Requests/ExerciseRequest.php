<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use App\Enums\ExerciseDifficulty;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The fields of a catalogue exercise, the same for create and update:
 * classification, difficulty, selection priority, rest, image and video.
 *
 * Every Exercise has a Default Rest (GLOSSARY), so the validated data
 * always carries one: a request that leaves it out or blank gets
 * defaultRestWhenOmitted() before validation, never NULL.
 */
abstract class ExerciseRequest extends FormRequest
{
    /**
     * The Default Rest a request that leaves it out or blank stands for.
     */
    abstract protected function defaultRestWhenOmitted(): int;

    protected function prepareForValidation(): void
    {
        if ($this->input('default_rest_sec') === null) {
            $this->merge(['default_rest_sec' => $this->defaultRestWhenOmitted()]);
        }
    }

    /**
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
            'difficulty' => ['nullable', Rule::enum(ExerciseDifficulty::class)],
            'selection_priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'default_rest_sec' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'video' => ['nullable', 'mimes:mp4,webm,ogg', 'max:51200'],
            'primary_muscle_group_ids' => ['nullable', 'array'],
            'primary_muscle_group_ids.*' => ['exists:muscle_groups,id'],
            'secondary_muscle_group_ids' => ['nullable', 'array'],
            'secondary_muscle_group_ids.*' => ['exists:muscle_groups,id'],
            'training_style_ids' => ['nullable', 'array'],
            'training_style_ids.*' => ['exists:training_styles,id'],
        ];
    }
}
