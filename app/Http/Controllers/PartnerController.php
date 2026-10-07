<?php

namespace App\Http\Controllers;

use App\Helpers\ColorHelper;
use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Models\Partner;
use App\Models\PartnerIdentity;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PartnerController extends Controller
{
    /**
     * Display a listing of the partners.
     */
    public function index(): View|RedirectResponse
    {
        // The super admin's list lives under /admin now.
        if (auth()->user()->hasRole('admin')) {
            return redirect()->route('admin.partners.index');
        }

        $this->authorize('viewAny', Partner::class);

        $partners = Partner::with('identity')
            ->withCount('users')
            ->latest()
            ->get();

        return view('partners.index', compact('partners'));
    }

    /**
     * Show the form for creating a new partner.
     */
    public function create(): View
    {
        $this->authorize('create', Partner::class);

        return view('partners.create', ['branding' => $this->brandingForm(null)]);
    }

    /**
     * Store a newly created partner in storage.
     */
    public function store(StorePartnerRequest $request): RedirectResponse
    {
        $this->authorize('create', Partner::class);

        $partner = Partner::create($request->only(['name', 'slug', 'domain', 'is_active']));

        $identityData = $request->only(config('branding.identity_fields'));

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('partners', 'public');
            $identityData['logo'] = 'storage/'.$logoPath;
        }

        // Handle background pattern upload
        if ($request->hasFile('background_pattern')) {
            $patternPath = $request->file('background_pattern')->store('partners', 'public');
            $identityData['background_pattern'] = 'storage/'.$patternPath;
        }

        $partner->identity()->create($identityData);

        // Link all default exercises to this partner
        $partner->syncDefaultExercises();

        return redirect()->route('partners.index')
            ->with('success', 'Partner created successfully.');
    }

    /**
     * Display the specified partner.
     */
    public function show(Partner $partner): View|RedirectResponse
    {
        // The super admin's partner page lives under /admin now.
        if (auth()->user()->hasRole('admin')) {
            return redirect()->route('admin.partners.show', $partner);
        }

        $this->authorize('view', $partner);

        $partner->loadCount('users');
        $partner->load('identity');

        $membersQuery = $partner->users()->appUsers();

        $totalMembers = (clone $membersQuery)->count();

        $activeMembersThisWeek = (clone $membersQuery)
            ->whereHas('workoutSessions', function ($query) {
                $query->whereBetween('performed_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ]);
            })
            ->count();

        $colors = ColorHelper::processPartnerColors($partner->identity);
        $colorPalette = ColorHelper::getColorPalette($partner->identity);
        $darkColorPalette = ColorHelper::getDarkColorPalette($partner->identity);

        $usersCount = $partner->users_count;

        return view('partners.show', compact(
            'partner',
            'colors',
            'colorPalette',
            'darkColorPalette',
            'usersCount',
            'totalMembers',
            'activeMembersThisWeek'
        ));
    }

    /**
     * Show the form for editing the specified partner.
     */
    public function edit(Partner $partner): View
    {
        $this->authorize('update', $partner);

        $partner->load('identity');

        return view('partners.edit', ['partner' => $partner, 'branding' => $this->brandingForm($partner->identity)]);
    }

    /**
     * Update the specified partner in storage.
     */
    public function update(UpdatePartnerRequest $request, Partner $partner): RedirectResponse
    {
        $this->authorize('update', $partner);

        if ($request->has('is_active') && ! $request->boolean('is_active') && ! $partner->canBeDeactivated()) {
            return back()->withInput()->withErrors(['is_active' => Partner::HOUSE_CANNOT_BE_DEACTIVATED]);
        }

        $partner->update($request->only(['name', 'slug', 'domain', 'is_active']));

        $identityData = $request->only(config('branding.identity_fields'));

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($partner->identity?->logo) {
                $oldLogoPath = str_replace('storage/', '', $partner->identity->logo);
                Storage::delete($oldLogoPath);
            }

            $logoPath = $request->file('logo')->store('partners', 'public');
            $identityData['logo'] = 'storage/'.$logoPath;
        }

        // Handle background pattern upload
        if ($request->hasFile('background_pattern')) {
            // Delete old pattern if exists
            if ($partner->identity?->background_pattern) {
                $oldPatternPath = str_replace('storage/', '', $partner->identity->background_pattern);
                Storage::delete($oldPatternPath);
            }

            $patternPath = $request->file('background_pattern')->store('partners', 'public');
            $identityData['background_pattern'] = 'storage/'.$patternPath;
        }

        if ($partner->identity) {
            $partner->identity->update($identityData);
        } else {
            $partner->identity()->create($identityData);
        }

        return redirect()->route('partners.index')
            ->with('success', 'Partner updated successfully.');
    }

    /**
     * Remove the specified partner from storage.
     */
    public function destroy(Partner $partner): RedirectResponse
    {
        $this->authorize('delete', $partner);

        // Check if partner can be deleted
        if (! $partner->canBeDeleted()) {
            return redirect()->route('partners.index')
                ->with('error', 'Cannot delete partner with existing users. Please remove all users first.');
        }

        // Delete logo if exists
        if ($partner->identity?->logo) {
            $logoPath = str_replace('storage/', '', $partner->identity->logo);
            Storage::delete($logoPath);
        }

        // Delete background pattern if exists
        if ($partner->identity?->background_pattern) {
            $patternPath = str_replace('storage/', '', $partner->identity->background_pattern);
            Storage::delete($patternPath);
        }

        $partner->delete();

        return redirect()->route('partners.index')
            ->with('success', 'Partner deleted successfully.');
    }

    /**
     * The colours the form and its preview start from, per mode. Only primary
     * and secondary are fields; the backgrounds and text colours are the
     * stored (or default) values the app renders with, for the preview.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    private function brandingForm(?PartnerIdentity $identity): array
    {
        $light = ColorHelper::processPartnerColors($identity);
        $dark = ColorHelper::processPartnerDarkColors($identity);

        foreach (['primary', 'secondary'] as $slot) {
            $light[$slot] = old("{$slot}_color", $light[$slot]);
            $dark[$slot] = old("{$slot}_color_dark", $dark[$slot]);
        }

        return ['light' => $light, 'dark' => $dark];
    }
}
