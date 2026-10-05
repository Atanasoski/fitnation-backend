<?php

namespace App\Services\Exercise;

/**
 * What ExerciseArchive did with an exercise it was asked to remove.
 */
enum ArchiveOutcome: string
{
    /** It was used, so it was retired and its history kept. */
    case Archived = 'archived';

    /** Nobody ever used it, so it was deleted outright. */
    case Deleted = 'deleted';
}
