<?php

namespace App\Services\Exercise;

use App\Models\Exercise;
use App\Models\Partner;
use App\Services\PartnerExerciseFileService;
use Illuminate\Http\UploadedFile;

/**
 * Writing a partner's link to a catalogue exercise and their Partner Override
 * on it: the description, image and video their members see instead of the
 * catalogue's. Reading what a partner sees is PartnerExerciseView's job.
 *
 * The partner admin (their own partner, /partner/exercises) and the super
 * admin (any partner, the exercise slide-over) both write through here, so
 * the two cannot drift. Override files go through PartnerExerciseFileService,
 * which names them deterministically per partner and exercise, so storing a
 * new image replaces the old one and removing one deletes the file.
 *
 * A blank field is no override: PartnerExerciseView falls back to the
 * catalogue for it.
 */
final class PartnerOverrides
{
    public function __construct(private PartnerExerciseFileService $files) {}

    /**
     * Apply a change to the partner's override, linking the exercise first if
     * it is not linked yet. Only what $changes names is touched:
     *
     * - `description`: present sets it; an empty value clears it.
     * - `image` / `video`: an uploaded file replaces the override's own.
     * - `remove_image` / `remove_video`: truthy clears it and deletes the file
     *   (an upload in the same change wins).
     *
     * @param  array{description?: ?string, image?: ?UploadedFile, video?: ?UploadedFile, remove_image?: mixed, remove_video?: mixed}  $changes
     */
    public function write(Partner $partner, Exercise $exercise, array $changes): void
    {
        $pivot = $this->pivot($partner, $exercise);

        $data = [
            'description' => $pivot?->description,
            'image' => $pivot?->image,
            'video' => $pivot?->video,
        ];

        if (array_key_exists('description', $changes)) {
            $data['description'] = $changes['description'] ?: null;
        }

        if (($changes['image'] ?? null) instanceof UploadedFile) {
            $data['image'] = $this->files->storeImage($changes['image'], $partner, $exercise);
        } elseif (filter_var($changes['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN) && $pivot?->image) {
            $this->files->deleteImage($partner, $exercise);
            $data['image'] = null;
        }

        if (($changes['video'] ?? null) instanceof UploadedFile) {
            $data['video'] = $this->files->storeVideo($changes['video'], $partner, $exercise);
        } elseif (filter_var($changes['remove_video'] ?? false, FILTER_VALIDATE_BOOLEAN) && $pivot?->video) {
            $this->files->deleteVideo($partner, $exercise);
            $data['video'] = null;
        }

        if ($pivot) {
            $partner->exercises()->updateExistingPivot($exercise->getKey(), $data);
        } else {
            $partner->exercises()->attach($exercise->getKey(), $data);
        }
    }

    /**
     * Drop the whole override, files included, so the partner's members see
     * the catalogue again. The exercise stays linked. Not linked: nothing to do.
     */
    public function clear(Partner $partner, Exercise $exercise): void
    {
        if ($this->pivot($partner, $exercise) === null) {
            return;
        }

        $this->deleteFiles($partner, $exercise);
        $partner->exercises()->updateExistingPivot($exercise->getKey(), [
            'description' => null,
            'image' => null,
            'video' => null,
        ]);
    }

    /**
     * Put the exercise in the partner's library with no override. Linking one
     * that is already linked blanks its override row (its files stay), as the
     * partner admin's link always has.
     */
    public function link(Partner $partner, Exercise $exercise): void
    {
        $partner->exercises()->syncWithoutDetaching([
            $exercise->getKey() => ['description' => null, 'image' => null, 'video' => null],
        ]);
    }

    /**
     * Take the exercise out of the partner's library, which hides it from
     * their members, and delete the override's files.
     */
    public function unlink(Partner $partner, Exercise $exercise): void
    {
        $this->deleteFiles($partner, $exercise);
        $partner->exercises()->detach($exercise->getKey());
    }

    private function deleteFiles(Partner $partner, Exercise $exercise): void
    {
        $pivot = $this->pivot($partner, $exercise);

        if ($pivot?->image) {
            $this->files->deleteImage($partner, $exercise);
        }
        if ($pivot?->video) {
            $this->files->deleteVideo($partner, $exercise);
        }
    }

    /**
     * The partner_exercises row, or null when the exercise is not linked.
     */
    private function pivot(Partner $partner, Exercise $exercise): ?object
    {
        return $partner->exercises()->find($exercise->getKey())?->pivot;
    }
}
