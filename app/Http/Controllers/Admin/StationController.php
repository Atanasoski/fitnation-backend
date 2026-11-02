<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Station;
use App\Services\Stations\StationQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The super admin's hand on a Partner's Stations (spec qr-stations, ticket
 * 05): create, edit and soft-delete, curate the ordered exercise list from
 * that partner's linked exercises, and download the printable QR.
 *
 * The short code is shown but never written here: it is generated once by the
 * Station model and printed on stickers, so it must never change (ADR-0005).
 */
class StationController extends Controller
{
    public function __construct(private StationQr $qr) {}

    public function create(Partner $partner): View
    {
        return view('admin.stations.create', [
            'partner' => $partner,
            'station' => new Station,
            'exercises' => $this->pickableExercises($partner),
            'selectedIds' => [],
        ]);
    }

    public function store(Request $request, Partner $partner): RedirectResponse
    {
        $data = $this->validated($request, $partner);

        $station = $partner->stations()->create(['name' => $data['name']]);
        $station->exercises()->sync($this->ordered($data['exercise_ids'] ?? []));

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', "Station \"{$station->name}\" created. Its code is {$station->short_code}.");
    }

    public function edit(Partner $partner, Station $station): View
    {
        return view('admin.stations.edit', [
            'partner' => $partner,
            'station' => $station,
            'exercises' => $this->pickableExercises($partner),
            'selectedIds' => $station->exercises()->pluck('workout_exercises.id')->all(),
        ]);
    }

    public function update(Request $request, Partner $partner, Station $station): RedirectResponse
    {
        $data = $this->validated($request, $partner);

        $station->update(['name' => $data['name']]);
        $station->exercises()->sync($this->ordered($data['exercise_ids'] ?? []));

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', "Station \"{$station->name}\" saved.");
    }

    /**
     * Soft delete only: the short code lives on printed stickers, so the row
     * stays to keep the code reserved and its URL degrading gracefully.
     */
    public function destroy(Partner $partner, Station $station): RedirectResponse
    {
        $station->delete();

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', "Station \"{$station->name}\" deleted. Its code stays reserved; the printed URL now shows the fallback page.");
    }

    /**
     * The print-ready QR image, as a download in the asked-for format.
     */
    public function qr(Partner $partner, Station $station, string $format): Response
    {
        [$content, $contentType] = $format === 'png'
            ? [$this->qr->png($station), 'image/png']
            : [$this->qr->svg($station), 'image/svg+xml'];

        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"station-{$station->short_code}.{$format}\"",
        ]);
    }

    /**
     * @return array{name: string, exercise_ids?: array<int, int>}
     */
    private function validated(Request $request, Partner $partner): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'exercise_ids' => ['array'],
            'exercise_ids.*' => [
                'integer',
                'distinct',
                // Only exercises linked to this partner may be curated.
                Rule::exists('partner_exercises', 'exercise_id')->where('partner_id', $partner->id),
            ],
        ]);
    }

    /**
     * The curated list in the order the admin arranged it, as sync() input.
     *
     * @param  array<int, int>  $exerciseIds
     * @return array<int, array{display_order: int}>
     */
    private function ordered(array $exerciseIds): array
    {
        return collect($exerciseIds)
            ->values()
            ->mapWithKeys(fn (int $id, int $index) => [$id => ['display_order' => $index]])
            ->all();
    }

    /**
     * What the picker offers: the partner's linked, non-archived exercises.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, equipment: string|null}>
     */
    private function pickableExercises(Partner $partner)
    {
        return $partner->exercises()
            ->available()
            ->with('equipmentType')
            ->orderBy('workout_exercises.name')
            ->get()
            ->map(fn ($exercise) => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'equipment' => $exercise->equipmentType?->name,
            ])
            ->values();
    }
}
