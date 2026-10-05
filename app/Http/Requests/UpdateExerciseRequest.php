<?php

namespace App\Http\Requests;

class UpdateExerciseRequest extends ExerciseRequest
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
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        abort(403, 'Only system administrators can update exercises.');
    }
}
