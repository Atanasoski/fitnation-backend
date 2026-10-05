<?php

namespace App\Http\Controllers;

use App\Helpers\ColorHelper;
use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Models\Partner;
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

        return view('partners.create');
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

        $membersQuery = $partner->users()->whereDoesntHave('roles', function ($query) {
            $query->whereIn('slug', ['admin', 'partner_admin']);
        });

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

        // Process colors using ColorHelper
        $colors = ColorHelper::processPartnerColors($partner->identity);
        $darkDefaults = config('branding.dark');

        // Build color arrays for form - separated by light/dark
        $lightColorFormData = [
            ['id' => 'primary_color', 'name' => 'Primary Color', 'value' => old('primary_color', $colors['primary']), 'required' => true],
            ['id' => 'secondary_color', 'name' => 'Secondary Color', 'value' => old('secondary_color', $colors['secondary']), 'required' => true],
            ['id' => 'background_color', 'name' => 'Background', 'value' => old('background_color', $colors['background']), 'required' => false],
            ['id' => 'card_background_color', 'name' => 'Card Background', 'value' => old('card_background_color', $colors['card_background']), 'required' => false],
            ['id' => 'text_primary_color', 'name' => 'Text Primary', 'value' => old('text_primary_color', $colors['text_primary']), 'required' => false],
            ['id' => 'text_secondary_color', 'name' => 'Text Secondary', 'value' => old('text_secondary_color', $colors['text_secondary']), 'required' => false],
            ['id' => 'text_on_primary_color', 'name' => 'Text On Primary', 'value' => old('text_on_primary_color', $colors['text_on_primary']), 'required' => false],
            ['id' => 'success_color', 'name' => 'Success', 'value' => old('success_color', $colors['success']), 'required' => false],
            ['id' => 'warning_color', 'name' => 'Warning', 'value' => old('warning_color', $colors['warning']), 'required' => false],
            ['id' => 'danger_color', 'name' => 'Danger', 'value' => old('danger_color', $colors['danger']), 'required' => false],
            ['id' => 'accent_color', 'name' => 'Accent', 'value' => old('accent_color', $colors['accent']), 'required' => false],
            ['id' => 'border_color', 'name' => 'Border', 'value' => old('border_color', $colors['border']), 'required' => false],
        ];

        $darkColorFormData = [
            ['id' => 'primary_color_dark', 'name' => 'Primary Color', 'value' => old('primary_color_dark', $partner->identity->primary_color_dark ?? $darkDefaults['primary']), 'required' => false],
            ['id' => 'secondary_color_dark', 'name' => 'Secondary Color', 'value' => old('secondary_color_dark', $partner->identity->secondary_color_dark ?? $darkDefaults['secondary']), 'required' => false],
            ['id' => 'background_color_dark', 'name' => 'Background', 'value' => old('background_color_dark', $partner->identity->background_color_dark ?? $darkDefaults['background']), 'required' => false],
            ['id' => 'card_background_color_dark', 'name' => 'Card Background', 'value' => old('card_background_color_dark', $partner->identity->card_background_color_dark ?? $darkDefaults['card_background']), 'required' => false],
            ['id' => 'text_primary_color_dark', 'name' => 'Text Primary', 'value' => old('text_primary_color_dark', $partner->identity->text_primary_color_dark ?? $darkDefaults['text_primary']), 'required' => false],
            ['id' => 'text_secondary_color_dark', 'name' => 'Text Secondary', 'value' => old('text_secondary_color_dark', $partner->identity->text_secondary_color_dark ?? $darkDefaults['text_secondary']), 'required' => false],
            ['id' => 'text_on_primary_color_dark', 'name' => 'Text On Primary', 'value' => old('text_on_primary_color_dark', $partner->identity->text_on_primary_color_dark ?? $darkDefaults['text_on_primary']), 'required' => false],
            ['id' => 'success_color_dark', 'name' => 'Success', 'value' => old('success_color_dark', $partner->identity->success_color_dark ?? $darkDefaults['success']), 'required' => false],
            ['id' => 'warning_color_dark', 'name' => 'Warning', 'value' => old('warning_color_dark', $partner->identity->warning_color_dark ?? $darkDefaults['warning']), 'required' => false],
            ['id' => 'danger_color_dark', 'name' => 'Danger', 'value' => old('danger_color_dark', $partner->identity->danger_color_dark ?? $darkDefaults['danger']), 'required' => false],
            ['id' => 'accent_color_dark', 'name' => 'Accent', 'value' => old('accent_color_dark', $partner->identity->accent_color_dark ?? $darkDefaults['accent']), 'required' => false],
            ['id' => 'border_color_dark', 'name' => 'Border', 'value' => old('border_color_dark', $partner->identity->border_color_dark ?? $darkDefaults['border']), 'required' => false],
        ];

        return view('partners.edit', compact('partner', 'lightColorFormData', 'darkColorFormData'));
    }

    /**
     * Update the specified partner in storage.
     */
    public function update(UpdatePartnerRequest $request, Partner $partner): RedirectResponse
    {
        $this->authorize('update', $partner);

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
}
