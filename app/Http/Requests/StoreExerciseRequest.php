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

    /**
     * A new exercise without a curated rest starts at 90s, the admin form's
     * and the importer's default.
     */
    protected function defaultRestWhenOmitted(): int
    {
        return 90;
    }
}
