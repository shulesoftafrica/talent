<?php

namespace App\Http\Controllers;

use App\Models\Constant\ReferCity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only lookups against the shared `constant` reference schema, for
 * cascading dropdowns (country -> city) used both pre-auth (landing page
 * signup) and post-auth (profile edit) — no candidate data involved, so no
 * auth guard is needed either way.
 */
class ReferenceController extends Controller
{
    public function cities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer'],
        ]);

        $cities = ReferCity::where('countryid', $data['country_id'])
            ->orderBy('city')
            ->get(['id', 'city']);

        return response()->json(['cities' => $cities]);
    }
}
