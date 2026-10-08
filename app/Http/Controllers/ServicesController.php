<?php

namespace App\Http\Controllers;

use App\Content\Catalog;
use App\Models\AddOn;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\ServicePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServicesController extends Controller
{
    public function __invoke(Request $request): View
    {
        $categories = ServiceCategory::query()->where('is_active', true)->orderBy('position')->get()
            ->map(fn ($category) => ['slug' => $category->slug, 'name' => $category->name, 'summary' => $category->summary ?? '', 'image' => collect(Catalog::categories())->firstWhere('slug', $category->slug)['image'] ?? 'nails'])
            ->all();
        $categorySlugs = array_column($categories, 'slug');
        $requestedCategory = $request->query('category');
        $activeCategory = is_string($requestedCategory) && in_array($requestedCategory, $categorySlugs, true) ? $requestedCategory : null;
        $requestedQuery = $request->query('q');
        $query = is_string($requestedQuery) ? trim(Str::substr($requestedQuery, 0, 60)) : '';
        $query = $query === '' ? null : $query;
        $records = Service::query()->with('category')->where('is_active', true)
            ->when($activeCategory, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $activeCategory)))
            ->when($query, fn ($q) => $q->where(fn ($search) => $search->where('name', 'like', '%'.$query.'%')->orWhere('summary', 'like', '%'.$query.'%')->orWhere('inclusions', 'like', '%'.$query.'%')->orWhere('price_note', 'like', '%'.$query.'%')))
            ->orderBy('position')->get();
        $services = $records->map(fn (Service $service) => ServicePresenter::service($service))->all();
        $groups = collect($categories)->filter(fn ($category) => $activeCategory === null || $category['slug'] === $activeCategory)
            ->map(fn ($category) => ['category' => $category, 'services' => array_values(array_filter($services, fn ($service) => $service['category_slug'] === $category['slug']))])
            ->filter(fn ($group) => $group['services'] !== [])->values()->all();
        $addOns = $records->isNotEmpty() && ($activeCategory === null || $activeCategory === 'nails')
            ? AddOn::query()->where('is_active', true)->whereHas('services', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', 'nails')))->orderBy('name')->get()->map(fn (AddOn $item) => ServicePresenter::addOn($item))->all()
            : [];

        return view('services.index', [
            'categories' => $categories, 'activeCategory' => $activeCategory, 'query' => $query,
            'groups' => $groups, 'serviceCount' => count($services), 'catalogCount' => Service::query()->where('is_active', true)->count(),
            'addOns' => $addOns,
        ]);
    }
}
