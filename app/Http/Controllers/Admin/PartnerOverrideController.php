<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\Partner;
use App\Services\Exercise\ExerciseGallery;
use App\Services\Exercise\PartnerOverrides;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The super admin's hand on any partner's Partner Override, from the
 * exercise slide-over (spec 023, ticket 04): edit, clear, link and unlink.
 * Writes go through PartnerOverrides, the same module the partner admin's own
 * page uses. Every action returns to the gallery slice it came from (`back`)
 * with the exercise open again.
 */
class PartnerOverrideController extends Controller
{
    public function __construct(private PartnerOverrides $overrides) {}

    public function update(Request $request, Exercise $exercise, Partner $partner): RedirectResponse
    {
        $changes = $request->validateWithBag('override', [
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'video' => ['nullable', 'mimes:mp4,webm,ogg', 'max:51200'],
            'remove_image' => ['nullable', 'boolean'],
            'remove_video' => ['nullable', 'boolean'],
        ]);

        $this->overrides->write($partner, $exercise, $changes);

        return $this->backToExercise($request, $exercise, "{$partner->name}'s override saved.");
    }

    public function clear(Request $request, Exercise $exercise, Partner $partner): RedirectResponse
    {
        $this->overrides->clear($partner, $exercise);

        return $this->backToExercise($request, $exercise, "{$partner->name}'s override cleared. Their members see the catalogue version.");
    }

    public function link(Request $request, Exercise $exercise): RedirectResponse
    {
        $partnerId = $request->validateWithBag('override', [
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
        ])['partner_id'];
        $partner = Partner::findOrFail($partnerId);

        $this->overrides->link($partner, $exercise);

        return $this->backToExercise($request, $exercise, "Linked to {$partner->name}.");
    }

    public function unlink(Request $request, Exercise $exercise, Partner $partner): RedirectResponse
    {
        $this->overrides->unlink($partner, $exercise);

        return $this->backToExercise($request, $exercise, "Unlinked from {$partner->name}. Their members no longer see it.");
    }

    private function backToExercise(Request $request, Exercise $exercise, string $message): RedirectResponse
    {
        return redirect()
            ->to(ExerciseGallery::backUrl($request->input('back'), ['edit' => $exercise->getKey()]))
            ->with('success', $message);
    }
}
