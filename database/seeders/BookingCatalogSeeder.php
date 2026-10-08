<?php

namespace Database\Seeders;

use App\Content\Catalog;
use App\Models\AddOn;
use App\Models\BookingConfiguration;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\WorkingHour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookingCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (Catalog::categories() as $position => $category) {
                ServiceCategory::query()->updateOrCreate(
                    ['slug' => $category['slug']],
                    ['name' => $category['name'], 'summary' => $category['summary'], 'position' => $position, 'is_active' => true],
                );
            }

            foreach (Catalog::catalog() as $position => $item) {
                $category = ServiceCategory::query()->where('slug', $item['category_slug'])->firstOrFail();

                Service::query()->updateOrCreate(
                    ['slug' => $item['slug']],
                    [
                        'service_category_id' => $category->id,
                        'name' => $item['name'],
                        'summary' => $item['result'],
                        'inclusions' => $item['inclusions'] ?: null,
                        'duration_minutes' => $item['duration_minutes'],
                        'price_mode' => $item['price_mode'],
                        'price_minor' => $item['price_minor'],
                        'price_note' => $item['price_note'],
                        'is_featured' => $item['signature'],
                        'is_active' => true,
                        'is_online_bookable' => false,
                        'position' => $position,
                    ],
                );
            }

            foreach (Catalog::addOns() as $item) {
                AddOn::query()->updateOrCreate(
                    ['slug' => $item['slug']],
                    [
                        'name' => $item['name'],
                        'description' => $item['note'],
                        'duration_minutes' => $item['duration_minutes'],
                        'price_mode' => $item['price_mode'],
                        'price_minor' => $item['price_minor'],
                        'is_active' => true,
                    ],
                );
            }

            // The public source catalog is only a starter. Preserve its documented
            // category compatibility as editable links rather than inferring it at runtime.
            foreach (Catalog::catalog() as $serviceData) {
                $service = Service::query()->where('slug', $serviceData['slug'])->firstOrFail();
                foreach (Catalog::addOnsFor($serviceData['category_slug']) as $position => $addOnData) {
                    $addOn = AddOn::query()->where('slug', $addOnData['slug'])->firstOrFail();
                    DB::table('service_add_on')->updateOrInsert(
                        ['service_id' => $service->id, 'add_on_id' => $addOn->id],
                        ['is_required' => false, 'position' => $position],
                    );
                }
            }

            $staffRows = [
                ['name' => 'Olena', 'title' => 'Brow Artist', 'categories' => ['brows'], 'accepts_new_clients' => true],
                ['name' => 'Masha', 'title' => 'Top Nail Technician', 'categories' => ['nails'], 'accepts_new_clients' => false],
                ['name' => 'Patrycja', 'title' => 'Hairstylist & Makeup Artist', 'categories' => ['hair', 'makeup'], 'accepts_new_clients' => true],
                ['name' => 'Karyna', 'title' => 'Senior Nail Technician', 'categories' => ['nails'], 'accepts_new_clients' => true],
                ['name' => 'Lera', 'title' => 'Senior Nail Technician', 'categories' => ['nails'], 'accepts_new_clients' => true],
                ['name' => 'Sofia', 'title' => 'Senior Nail Technician', 'categories' => ['nails'], 'accepts_new_clients' => true],
                ['name' => 'Anna', 'title' => 'Senior Nail Technician', 'categories' => ['nails'], 'accepts_new_clients' => true],
            ];

            $schedule = config('gossips.booking.schedule');
            $weekdays = $schedule['days'] ?? [2, 3, 4, 5, 6];
            $opensAt = $schedule['opens_at'] ?? '10:00';
            $closesAt = $schedule['closes_at'] ?? '19:00';

            foreach ($staffRows as $position => $row) {
                $staff = Staff::query()->updateOrCreate(
                    ['name' => $row['name']],
                    [
                        'title' => $row['title'],
                        'is_active' => true,
                        'is_bookable' => false,
                        'accepts_new_clients' => $row['accepts_new_clients'],
                        'position' => $position,
                    ],
                );

                $services = Service::query()->whereHas('category', fn ($query) => $query->whereIn('slug', $row['categories']))->get();
                foreach ($services as $service) {
                    DB::table('staff_service')->updateOrInsert(
                        ['staff_id' => $staff->id, 'service_id' => $service->id],
                        [
                            'duration_override_minutes' => null,
                            'price_override_minor' => null,
                            'accepts_new_clients' => $row['accepts_new_clients'],
                        ],
                    );
                }

                foreach ($weekdays as $weekday) {
                    WorkingHour::query()->firstOrCreate([
                        'staff_id' => $staff->id,
                        'weekday' => (int) $weekday,
                        'opens_at' => $opensAt.':00',
                        'closes_at' => $closesAt.':00',
                        'effective_from' => null,
                        'effective_until' => null,
                    ]);
                }
            }

            BookingConfiguration::query()->firstOrCreate(['id' => 1], [
                'timezone' => 'America/Toronto',
                'booking_horizon_days' => $schedule['days_ahead'] ?? 14,
                'minimum_notice_minutes' => 0,
                'slot_increment_minutes' => $schedule['slot_minutes'] ?? 30,
                'online_booking_enabled' => false,
                'instant_confirmation' => true,
                'allow_online_cancellation' => false,
                'allow_online_rescheduling' => false,
            ]);
        });
    }
}
