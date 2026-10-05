<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The ⌘K palette's endpoint: GET /admin/search?q=… → users, partners, pages.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = is_string($q = $request->query('q')) ? $q : '';

        return response()->json(GlobalSearch::find($term));
    }
}
