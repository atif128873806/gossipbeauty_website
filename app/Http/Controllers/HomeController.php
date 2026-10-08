<?php

namespace App\Http\Controllers;

use App\Content\Catalog;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\ServicePresenter;

class HomeController extends Controller
{
    public function __invoke()
    {
        $metadata = collect(Catalog::categories())->keyBy('slug');
        $categories = ServiceCategory::query()->where('is_active', true)->orderBy('position')->get()->map(fn ($category) => [
            'slug' => $category->slug, 'name' => $category->name, 'summary' => $category->summary ?? '',
            'image' => $metadata->get($category->slug)['image'] ?? 'nails',
        ])->all();
        $services = Service::query()->with('category')->where('is_active', true)->where('is_featured', true)->orderBy('position')->limit(6)->get()
            ->map(fn ($service) => ServicePresenter::service($service))->all();

        return view('home', [
            'categories' => $categories, 'services' => $services,
            'tiles' => Catalog::workTiles(null, 6), 'rating' => Catalog::ratingSummary(),
            'googleRating' => Catalog::googleRatingSummary(),
            'studio' => Catalog::studioSummary(), 'visit' => Catalog::visit(),
        ]);
    }
}
