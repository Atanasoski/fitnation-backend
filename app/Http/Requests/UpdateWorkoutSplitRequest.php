<?php

namespace App\Http\Requests;

use App\Enums\SplitFocus;
use App\Models\TargetRegion;
use App\Rules\FullBodyIsRequestedAlone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkoutSplitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $validRegionCodes = TargetRegion::pluck('code')->toArray();

        return [
            'days_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'focus' => ['required', Rule::enum(SplitFocus::class)],
            'day_index' => ['required', 'integer', 'min:0', 'max:6'],
            'target_regions' => ['required', 'array', 'min:1', new FullBodyIsRequestedAlone],
            'target_regions.*' => ['required', 'string', Rule::in($validRegionCodes)],
        ];
    }
}
