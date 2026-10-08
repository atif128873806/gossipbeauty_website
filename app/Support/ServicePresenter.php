<?php

namespace App\Support;

use App\Models\AddOn;
use App\Models\Service;

final class ServicePresenter
{
    public static function service(Service $service): array
    {
        return [
            'id' => $service->id, 'slug' => $service->slug, 'name' => $service->name,
            'category' => $service->category?->name ?? '', 'category_slug' => $service->category?->slug ?? '',
            'result' => $service->summary ?? '', 'inclusions' => $service->inclusions,
            'duration_minutes' => $service->duration_minutes, 'price_mode' => $service->price_mode,
            'price_minor' => $service->price_minor, 'price_note' => $service->price_note,
            'signature' => $service->is_featured,
        ];
    }

    public static function addOn(AddOn $addOn): array
    {
        return [
            'id' => $addOn->id, 'slug' => $addOn->slug, 'name' => $addOn->name,
            'note' => $addOn->description, 'duration_minutes' => $addOn->duration_minutes,
            'price_mode' => $addOn->price_mode, 'price_minor' => $addOn->price_minor,
        ];
    }
}
