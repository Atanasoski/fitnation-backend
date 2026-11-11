<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\Exercise\ExerciseGallery;
use App\Services\Exercise\PartnerOverrides;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bulk link and unlink from the partner lens on the exercise gallery (#134):
 * the bulk bar and each card's toggle. Writes go through PartnerOverrides.
 * Every action returns to the gallery slice it came from (`back`).
 */
class PartnerLibraryController extends Controller
{
    public function __construct(private PartnerOverrides $overrides) {}

    public function link(Request $request, Partner $partner): RedirectResponse
    {
        $count = $this->overrides->linkMany($partner, $this->exerciseIds($request));

        return $this->backToGallery($request)->with('success', "Linked {$count} to {$partner->name}.");
    }

    public function unlink(Request $request, Partner $partner): RedirectResponse
    {
        ['unlinked' => $count, 'kept' => $kept] = $this->overrides->unlinkMany($partner, $this->exerciseIds($request));

        $message = "Unlinked {$count} from {$partner->name}";
        if ($kept !== []) {
            $message .= sprintf('; %d kept: %s', count($kept), implode('; ', array_map(
                fn ($e) => $e->exercise->name.' (on '.implode(', ', $e->stations).')',
                $kept,
            )));
        }

        return $this->backToGallery($request)->with('success', $message.'.');
    }

    /**
     * @return list<int>
     */
    private function exerciseIds(Request $request): array
    {
        return array_map('intval', $request->validate([
            'exercise_ids' => ['required', 'array', 'min:1'],
            'exercise_ids.*' => ['integer'],
        ])['exercise_ids']);
    }

    private function backToGallery(Request $request): RedirectResponse
    {
        return redirect()->to(ExerciseGallery::backUrl($request->input('back')));
    }
}
