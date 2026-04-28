<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Http\Resources\Api\PartnerResource;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class PartnerController extends Controller
{
    /**
     * Display a listing of the partners.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Partner::class);

        $partners = Partner::with('identity')->latest()->get();

        return PartnerResource::collection($partners);
    }

    /**
     * Store a newly created partner in storage.
     */
    public function store(StorePartnerRequest $request): JsonResponse
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

        return response()->json([
            'message' => 'Partner created successfully',
            'data' => new PartnerResource($partner->load('identity')),
        ], 201);
    }

    /**
     * Display the specified partner.
     */
    public function show(Partner $partner): JsonResponse
    {
        $this->authorize('view', $partner);

        $partner->load('identity', 'users');

        return response()->json([
            'data' => new PartnerResource($partner),
        ]);
    }

    /**
     * List all active partners (public — used by mobile registration dropdown).
     */
    public function activeList(): AnonymousResourceCollection
    {
        $partners = Partner::with('identity')->where('is_active', true)->orderBy('name')->get();

        return PartnerResource::collection($partners);
    }

    public function branding(Partner $partner): JsonResponse
    {

        $partner->load('identity');

        return response()->json([
            'data' => new PartnerResource($partner),
        ], 200);
    }

    /**
     * Update the specified partner in storage.
     */
    public function update(UpdatePartnerRequest $request, Partner $partner): JsonResponse
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

        return response()->json([
            'message' => 'Partner updated successfully',
            'data' => new PartnerResource($partner->load('identity')),
        ]);
    }

    /**
     * Remove the specified partner from storage.
     */
    public function destroy(Partner $partner): JsonResponse
    {
        $this->authorize('delete', $partner);

        // Check if partner can be deleted
        if (! $partner->canBeDeleted()) {
            return response()->json([
                'message' => 'Cannot delete partner with existing users. Please remove all users first.',
            ], 422);
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

        return response()->json([
            'message' => 'Partner deleted successfully',
        ]);
    }
}
