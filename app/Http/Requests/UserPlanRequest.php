<?php

namespace App\Http\Requests;

use App\Enums\PlanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A plan a member of staff creates or edits for a user, from the plan outline
 * (023/06). Programs need weeks; Routines have none. Authorisation is the
 * route's PlanPolicy guard.
 *
 * is_active is accepted for the older plan pages that still post it; it only
 * ever reaches the plan through PlanActivation, and a new plan ignores it.
 */
class UserPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(PlanType::class)],
            'duration_weeks' => ['nullable', 'required_if:type,'.PlanType::Program->value, 'integer', 'min:1', 'max:52'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The plan's own columns, as they should be stored: a Routine keeps no weeks.
     *
     * @return array{name: string, description: ?string, type: PlanType, duration_weeks: ?int}
     */
    public function planAttributes(): array
    {
        $type = PlanType::from($this->validated('type'));

        return [
            'name' => $this->validated('name'),
            'description' => $this->validated('description'),
            'type' => $type,
            'duration_weeks' => $type === PlanType::Program ? (int) $this->validated('duration_weeks') : null,
        ];
    }
}
