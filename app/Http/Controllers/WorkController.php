<?php

namespace App\Http\Controllers;

use App\Content\Catalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkController extends Controller
{
    /**
     * The public work and reviews page.
     *
     * The gallery filter is a plain query parameter handled on the server, like the
     * services page filters, so each view is linkable and works without JavaScript.
     * An unrecognised category is discarded rather than reflected back into the
     * page, and the filter list only ever offers categories that actually hold
     * approved slots.
     */
    public function __invoke(Request $request): View
    {
        $categories = Catalog::categories();
        $categorySlugs = array_column($categories, 'slug');

        $requestedCategory = $request->query('category');
        $activeCategory = is_string($requestedCategory) && in_array($requestedCategory, $categorySlugs, true)
            ? $requestedCategory
            : null;

        $tiles = Catalog::workTiles($activeCategory);

        // Only offer a filter that has work behind it.
        $filters = array_values(array_filter(
            $categories,
            static fn (array $category): bool => Catalog::workTiles($category['slug']) !== []
        ));

        $activeCategoryName = $activeCategory === null
            ? null
            : collect($categories)->firstWhere('slug', $activeCategory)['name'];

        return view('work.index', [
            'categories' => $filters,
            'activeCategory' => $activeCategory,
            'activeCategoryName' => $activeCategoryName,
            'tiles' => $tiles,
            'tileCount' => count($tiles),
            'rating' => Catalog::ratingSummary(),
            'googleRating' => Catalog::googleRatingSummary(),
        ]);
    }
}
