<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Proxies Academy's skill taxonomy (categories/skills/levels) for ShuleSoft's
 * Job Posting editor skill picker — reads the academy schema directly, the
 * same cross-schema pattern VerifiedSkillsRepository/VacancySkillMatcher
 * already use, so shulesoft_newversion never needs its own Academy DB
 * connection just to populate a dropdown.
 */
class SkillsCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $levels = DB::connection('academy')->table('skill_levels')
            ->orderBy('rank')
            ->get(['id', 'name', 'slug', 'rank']);

        $skills = DB::connection('academy')->table('skills')
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'category_id', 'name', 'slug']);

        $categories = DB::connection('academy')->table('skill_categories')
            ->where('status', 1)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug'])
            ->map(function ($category) use ($skills) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'skills' => $skills->where('category_id', $category->id)->values(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'levels' => $levels,
            'categories' => $categories,
        ]);
    }
}
