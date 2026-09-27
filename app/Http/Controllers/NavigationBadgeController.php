<?php

namespace App\Http\Controllers;

use App\Support\Navigation\NavigationBadges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationBadgeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'badges' => NavigationBadges::for($request->user()),
        ]);
    }
}
