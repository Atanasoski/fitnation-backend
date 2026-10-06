<?php

namespace App\Http\Requests;

class StoreExerciseRequest extends ExerciseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->hasRole('admin') && $user->partner === null;
    }
}
