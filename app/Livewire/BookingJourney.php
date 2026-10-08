<?php

namespace App\Livewire;

use App\Content\Catalog;
use App\Content\Pricing;
use App\Domain\Booking\AppointmentWriter;
use App\Domain\Booking\AvailabilityService;
use App\Domain\Booking\BookingConflict;
use App\Jobs\SendAppointmentConfirmation;
use App\Models\AddOn;
use App\Models\BookingConfiguration;
use App\Models\Customer;
use App\Models\NotificationDelivery;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Customer booking backed by the same catalog and schedule the studio panel uses. */
class BookingJourney extends Component
{
    private array $slotCache = [];

    private ?array $dateCache = null;

    public string $step = 'service';

    public ?string $serviceSlug = null;

    /** @var list<string> */
    #[Url(as: 'extras')]
    public array $addOnSlugs = [];

    public string $professional = 'any';

    #[Url]
    public ?string $date = null;

    #[Url]
    public ?string $time = null;

    public string $firstName = '';

    public string $lastName = '';

    public string $phone = '';

    public string $email = '';

    public string $customerNote = '';

    public string $idempotencyKey = '';

    public ?string $confirmationReference = null;

    public ?string $confirmationStaff = null;

    public ?string $confirmationTime = null;

    public ?string $conflict = null;

    public function mount(?string $service = null): void
    {
        $this->serviceSlug = Service::query()->where('slug', $service)->where('is_active', true)->where('is_online_bookable', true)->value('slug');
        $this->idempotencyKey = (string) Str::uuid();
        $this->addOnSlugs = $this->applicableSlugs($this->addOnSlugs);
        $this->date = is_string($this->date) && $this->validDate($this->date) ? $this->date : null;
        $this->time = is_string($this->time) && $this->time !== null && $this->slotIsOffered($this->date, $this->time) ? $this->time : null;
    }

    public function steps(): array
    {
        return array_merge(['service'], $this->availableAddOns() !== [] ? ['extras'] : [], ['contact', 'professional', 'time', 'review']);
    }

    public function stepLabels(): array
    {
        return ['service' => 'Service', 'extras' => 'Extras', 'contact' => 'Your details', 'professional' => 'Professional', 'time' => 'Time', 'review' => 'Review'];
    }

    public function selectedService(): ?array
    {
        $service = Service::query()->with('category')->where('slug', $this->serviceSlug)->where('is_active', true)->where('is_online_bookable', true)->first();
        if (! $service) {
            return null;
        }

        return [
            'id' => $service->id, 'slug' => $service->slug, 'name' => $service->name,
            'category' => $service->category->name, 'duration_minutes' => $service->duration_minutes,
            'price_mode' => $service->price_mode, 'price_minor' => $service->price_minor,
            'price_note' => $service->price_note, 'summary' => $service->summary,
        ];
    }

    public function availableAddOns(): array
    {
        $service = $this->serviceModel();
        if (! $service) {
            return [];
        }

        return $service->addOns()->where('add_ons.is_active', true)->get()->map(fn (AddOn $item) => $this->addOnData($item))->all();
    }

    public function selectedAddOns(): array
    {
        return array_values(array_filter($this->availableAddOns(), fn ($item) => in_array($item['slug'], $this->addOnSlugs, true)));
    }

    public function professionalOptions(): array
    {
        $options = ['any' => 'Any available professional'];
        $service = $this->serviceModel();
        if (! $service) {
            return $options;
        }
        $phoneHash = $this->normalizedPhoneHash();
        $isNew = $phoneHash === null || ! Customer::withTrashed()->where('phone_search_hash', $phoneHash)->exists();
        foreach ($this->staffMembers($isNew) as $staff) {
            $options[(string) $staff->id] = $staff->name;
        }

        return $options;
    }

    public function professionalLabel(): string
    {
        return $this->professionalOptions()[$this->professional] ?? 'Any available professional';
    }

    public function dates(): array
    {
        if (! $this->serviceModel() || ! BookingConfiguration::current()->online_booking_enabled) {
            return [];
        }
        if ($this->dateCache !== null) {
            return $this->dateCache;
        }
        $start = CarbonImmutable::now(BookingConfiguration::current()->timezone)->startOfDay();
        $end = $start->addDays(BookingConfiguration::current()->booking_horizon_days);
        $availableDates = collect($this->slotsBetween($start, $end))->pluck('date')->unique()->flip();
        $result = [];
        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            if ($availableDates->has($day->toDateString())) {
                $result[] = ['value' => $day->toDateString(), 'label' => $day->format('D, M j'), 'weekday' => $day->format('l'), 'is_today' => $day->isToday()];
            }
        }

        return $this->dateCache = $result;
    }

    public function slots(): array
    {
        if (! $this->date) {
            return [];
        }

        return array_map(fn ($slot) => ['value' => $slot['time'], 'label' => CarbonImmutable::parse($slot['instant'])->setTimezone(BookingConfiguration::current()->timezone)->format('g:i A'), 'instant' => $slot['instant'], 'staff_name' => $slot['staff_name']], $this->slotsForDay($this->date));
    }

    public function totalMinutes(): ?int
    {
        $service = $this->serviceModel();
        if (! $service || $service->duration_minutes === null) {
            return null;
        }
        $staff = $this->professional !== 'any' ? Staff::query()->with('services')->find((int) $this->professional) : null;
        $base = $staff?->services->firstWhere('id', $service->id)?->pivot?->duration_override_minutes ?? $service->duration_minutes;

        return (int) $base + (int) collect($this->selectedAddOns())->sum('duration_minutes');
    }

    public function durationLabel(): ?string
    {
        $minutes = $this->totalMinutes();

        return $minutes === null ? null : ($minutes >= 60 ? intdiv($minutes, 60).' hr'.($minutes % 60 ? ' '.($minutes % 60).' min' : '') : $minutes.' min');
    }

    public function priceLines(): array
    {
        $lines = [];
        if ($service = $this->selectedService()) {
            $lines[] = ['name' => $service['name'], 'price' => $this->priceLabel($service['price_mode'], $service['price_minor'])];
        }
        foreach ($this->selectedAddOns() as $extra) {
            $lines[] = ['name' => $extra['name'], 'price' => $this->priceLabel($extra['price_mode'], $extra['price_minor'])];
        }

        return $lines;
    }

    public function summary(): array
    {
        $items = [];
        if ($service = $this->selectedService()) {
            $items[] = ['price_mode' => $service['price_mode'], 'price_minor' => $service['price_minor']];
        }
        foreach ($this->selectedAddOns() as $extra) {
            $items[] = ['price_mode' => $extra['price_mode'], 'price_minor' => $extra['price_minor']];
        }
        if ($items === []) {
            return ['label' => 'Price', 'value' => 'Choose a service', 'note' => null];
        }

        return Pricing::summary($items);
    }

    public function timeLabel(): ?string
    {
        if (! $this->date || ! $this->time) {
            return null;
        }

        return CarbonImmutable::parse($this->date.' '.$this->time, BookingConfiguration::current()->timezone)->format('l, F j, Y · g:i A');
    }

    public function updatedServiceSlug(): void
    {
        $this->dateCache = null;
        $this->serviceSlug = Service::query()->where('slug', $this->serviceSlug)->where('is_active', true)->where('is_online_bookable', true)->value('slug');
        $this->addOnSlugs = $this->applicableSlugs($this->addOnSlugs);
        $this->professional = 'any';
        $this->date = null;
        $this->time = null;
        $this->step = 'service';
    }

    public function updatedAddOnSlugs(): void
    {
        $this->dateCache = null;
        $this->addOnSlugs = $this->applicableSlugs($this->addOnSlugs);
        $this->time = null;
    }

    public function updatedPhone(): void
    {
        $this->dateCache = null;
        $this->professional = 'any';
        $this->date = null;
        $this->time = null;
    }

    public function updatedProfessional(): void
    {
        $this->dateCache = null;
        $this->date = null;
        $this->time = null;
    }

    public function selectDate(string $date): void
    {
        $this->date = $this->validDate($date) ? $date : null;
        $this->time = null;
        $this->conflict = null;
    }

    public function selectTime(string $time): void
    {
        if (! $this->slotIsOffered($this->date, $time)) {
            $this->time = null;
            $this->conflict = 'That time is no longer available. Choose another time.';

            return;
        }
        $this->time = $time;
        $this->conflict = null;
    }

    public function nextStep(): void
    {
        $this->resetErrorBag();
        $steps = $this->steps();
        $index = array_search($this->step, $steps, true);
        if (! $this->stepIsValid($this->step) || $index === false || ! isset($steps[$index + 1])) {
            return;
        }
        $this->step = $steps[$index + 1];
    }

    public function previousStep(): void
    {
        $steps = $this->steps();
        $index = array_search($this->step, $steps, true);
        if ($index !== false && $index > 0) {
            $this->step = $steps[$index - 1];
        }
    }

    public function confirm(AppointmentWriter $writer): void
    {
        $this->resetErrorBag();
        if (! $this->stepIsValid('review')) {
            return;
        }
        $rateKey = 'booking-confirm:'.hash('sha256', (string) request()->ip());
        if (RateLimiter::tooManyAttempts($rateKey, 8)) {
            $this->step = 'contact';
            $this->addError('phone', 'Please wait a little before trying again.');

            return;
        }
        RateLimiter::hit($rateKey, 60);
        $service = $this->serviceModel();
        try {
            $appointment = $writer->confirm(
                [(int) $service->id], array_map(fn ($slug) => (int) AddOn::query()->where('slug', $slug)->value('id'), $this->addOnSlugs),
                $this->professional === 'any' ? null : (int) $this->professional,
                (string) $this->date, (string) $this->time,
                ['first_name' => $this->firstName, 'last_name' => $this->lastName, 'phone' => $this->phone, 'email' => $this->email, 'customer_note' => $this->customerNote],
                $this->idempotencyKey, 'website'
            );
        } catch (BookingConflict $exception) {
            $this->step = 'time';
            $this->conflict = $exception->getMessage();
            $this->time = null;

            return;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $key = match ($field) {
                    'first_name' => 'firstName', 'last_name' => 'lastName', 'customer_note' => 'customerNote', default => $field
                };
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }
            $this->step = array_key_exists('first_name', $exception->errors()) || array_key_exists('phone', $exception->errors()) || array_key_exists('email', $exception->errors()) ? 'contact' : 'review';

            return;
        }
        $this->confirmationReference = $appointment->public_reference;
        $this->confirmationStaff = $appointment->staff?->name;
        $this->confirmationTime = $appointment->starts_at_utc->setTimezone($appointment->timezone)->format('l, F j, Y · g:i A');
        if ($appointment->wasRecentlyCreated && $this->email !== '' && config('gossips.booking.email_enabled')) {
            try {
                $delivery = NotificationDelivery::query()->create([
                    'appointment_id' => $appointment->id, 'notification_type' => 'appointment.confirmation',
                    'channel' => 'email', 'recipient' => mb_strtolower($this->email), 'status' => 'queued',
                ]);
                SendAppointmentConfirmation::dispatch($delivery->id)->afterCommit();
            } catch (\Throwable $exception) {
                if (isset($delivery)) {
                    $delivery->update(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 4000)]);
                }
                report($exception);
            }
        }
        $this->step = 'done';
    }

    public function restart(): void
    {
        $this->addOnSlugs = [];
        $this->professional = 'any';
        $this->date = null;
        $this->time = null;
        $this->firstName = '';
        $this->lastName = '';
        $this->phone = '';
        $this->email = '';
        $this->customerNote = '';
        $this->confirmationReference = null;
        $this->step = 'service';
        $this->idempotencyKey = (string) Str::uuid();
    }

    public function render(): View
    {
        return view('livewire.booking-journey', ['categories' => ServiceCategory::query()->where('is_active', true)->whereHas('services', fn ($q) => $q->where('is_active', true)->where('is_online_bookable', true))->with(['services' => fn ($q) => $q->where('is_active', true)->where('is_online_bookable', true)->orderBy('position')])->orderBy('position')->get()]);
    }

    private function stepIsValid(string $step): bool
    {
        if ($step === 'service' && ! $this->serviceModel()) {
            $this->addError('serviceSlug', 'Choose a service to continue.');

            return false;
        }
        if ($step === 'contact') {
            $this->validate(['firstName' => 'required|string|max:120', 'lastName' => 'nullable|string|max:120', 'phone' => 'required|string|min:8|max:30', 'email' => 'nullable|email|max:255', 'customerNote' => 'nullable|string|max:2000']);
        }
        if ($step === 'professional') {
            if (! array_key_exists($this->professional, $this->professionalOptions())) {
                $this->professional = 'any';
            }
        }
        if ($step === 'time' && (! $this->date || ! $this->slotIsOffered($this->date, (string) $this->time))) {
            $this->addError('time', 'Choose an available day and time.');

            return false;
        }
        if ($step === 'review' && (! $this->date || ! $this->slotIsOffered($this->date, (string) $this->time))) {
            $this->step = 'time';
            $this->conflict = 'That time is no longer available. Choose another time.';

            return false;
        }

        return true;
    }

    private function serviceModel(): ?Service
    {
        return Service::query()->where('slug', $this->serviceSlug)->where('is_active', true)->where('is_online_bookable', true)->first();
    }

    private function slotsForDay(string $date): array
    {
        $cacheKey = implode('|', [$this->serviceSlug, implode(',', $this->addOnSlugs), $this->professional, $this->normalizedPhoneHash() ?? 'new', $date]);
        if (array_key_exists($cacheKey, $this->slotCache)) {
            return $this->slotCache[$cacheKey];
        }
        $service = $this->serviceModel();
        if (! $service) {
            return [];
        }
        $addOns = array_map(fn ($slug) => (int) AddOn::query()->where('slug', $slug)->value('id'), $this->addOnSlugs);
        $phoneHash = $this->normalizedPhoneHash();
        $isNew = $phoneHash === null || ! Customer::withTrashed()->where('phone_search_hash', $phoneHash)->exists();

        return $this->slotCache[$cacheKey] = app(AvailabilityService::class)->slots([$service], $addOns, $this->professional === 'any' ? null : (int) $this->professional, $isNew, CarbonImmutable::parse($date, BookingConfiguration::current()->timezone), CarbonImmutable::parse($date, BookingConfiguration::current()->timezone), true);
    }

    private function slotsBetween(CarbonImmutable $from, CarbonImmutable $through): array
    {
        $service = $this->serviceModel();
        if (! $service) {
            return [];
        }
        $addOns = array_map(fn ($slug) => (int) AddOn::query()->where('slug', $slug)->value('id'), $this->addOnSlugs);
        $phoneHash = $this->normalizedPhoneHash();
        $isNew = $phoneHash === null || ! Customer::withTrashed()->where('phone_search_hash', $phoneHash)->exists();

        return app(AvailabilityService::class)->slots(
            [$service], $addOns, $this->professional === 'any' ? null : (int) $this->professional,
            $isNew, $from, $through, true,
        );
    }

    private function slotIsOffered(?string $date, string $time): bool
    {
        if (! $date || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            return false;
        }
        foreach ($this->slotsForDay($date) as $slot) {
            if ($slot['time'] === $time) {
                return true;
            }
        }

        return false;
    }

    private function validDate(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        if (! checkdate($month, $day, $year)) {
            return false;
        }
        $start = CarbonImmutable::now(BookingConfiguration::current()->timezone)->startOfDay();
        $candidate = CarbonImmutable::parse($date, BookingConfiguration::current()->timezone)->startOfDay();

        return $candidate->greaterThanOrEqualTo($start) && $candidate->lessThanOrEqualTo($start->addDays(BookingConfiguration::current()->booking_horizon_days));
    }

    private function staffMembers(bool $isNew): array
    {
        $service = $this->serviceModel();
        if (! $service) {
            return [];
        }

        $query = $service->staff()->where('staff.is_active', true)->where('staff.is_bookable', true);
        if ($isNew) {
            $query->where('staff.accepts_new_clients', true)->wherePivot('accepts_new_clients', true);
        }

        return $query->orderBy('staff.position')->get()->all();
    }

    private function applicableSlugs(array $slugs): array
    {
        $allowed = array_column($this->availableAddOns(), 'slug');

        return array_values(array_intersect(array_values(array_filter($slugs, 'is_string')), $allowed));
    }

    private function normalizedPhoneHash(): ?string
    {
        $digits = preg_replace('/\D+/', '', $this->phone);
        if (! $digits || strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return hash_hmac('sha256', $digits, (string) config('app.key'));
    }

    private function addOnData(AddOn $item): array
    {
        return ['id' => $item->id, 'slug' => $item->slug, 'name' => $item->name, 'note' => $item->description, 'duration_minutes' => $item->duration_minutes, 'price_mode' => $item->price_mode, 'price_minor' => $item->price_minor];
    }

    public function priceLabel(string $mode, ?int $minor): string
    {
        return Catalog::priceLabel(['price_mode' => $mode, 'price_minor' => $minor]);
    }
}
