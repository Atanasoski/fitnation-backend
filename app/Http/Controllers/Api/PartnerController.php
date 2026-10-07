<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PartnerResource;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartnerController extends Controller
{
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
}
