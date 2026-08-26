<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearchService $search): JsonResponse
    {
        $this->authorize('viewAny', Site::class);

        $query = $request->string('q')->trim()->toString();

        return response()->json([
            'query' => $query,
            'results' => $search->search($query),
        ]);
    }
}
