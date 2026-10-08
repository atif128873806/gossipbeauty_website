        @if ($isOwner)
            <section class="mt-8 card p-6">
                <p class="eyebrow">Team and weekly hours</p><h2 class="mt-2 font-display text-2xl">Professionals</h2>
                @if ($isOwner)
                    <form method="post" action="{{ route('admin.staff.store') }}" class="mt-4 grid gap-3 rounded-card border border-line p-4 md:grid-cols-3">@csrf
                        <label class="text-sm">Professional name<input name="name" required class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label><label class="text-sm">Public title<input name="title" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                        <fieldset class="md:col-span-3"><legend class="text-sm font-medium">Eligible services</legend><div class="mt-2 grid gap-2 sm:grid-cols-3">@foreach($services as $service)<label class="flex gap-2 text-sm"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}">{{ $service->name }}</label>@endforeach</div></fieldset>
                        <button class="btn btn-secondary md:col-span-3">Add professional</button>
                    </form>
                @endif
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    @foreach ($staff as $person)
                        <details class="rounded-card border border-line p-4">
                            <summary class="cursor-pointer font-medium">{{ $person->name }} <span class="font-normal text-muted-ink">· {{ $person->title }} · {{ $person->is_bookable ? 'Bookable' : 'Unavailable' }}</span></summary>
                            <div class="mt-4">
                            @if($isOwner)
                                <form method="post" action="{{ route('admin.staff.update', $person) }}" class="space-y-3">@csrf @method('PUT')
                                    <label class="block text-sm">Professional name<input name="name" value="{{ $person->name }}" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label><label class="block text-sm">Public title<input name="title" value="{{ $person->title }}" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                                    <fieldset><legend class="text-sm font-medium">Eligible services and specialist-specific settings</legend><div class="mt-2 max-h-64 overflow-auto space-y-2">
                                        @foreach($services as $service)
                                            @php($assignment=$person->services->firstWhere('id',$service->id)?->pivot)
                                            <div class="rounded-card border border-line p-2">
                                                <label class="flex gap-2 text-sm"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked($assignment)>{{ $service->name }}</label>
                                                <label class="mt-1 flex gap-2 text-xs"><input type="checkbox" name="new_client_service_ids[]" value="{{ $service->id }}" @checked($assignment?->accepts_new_clients)>Accept new clients for this service</label>
                                                <div class="mt-2 grid grid-cols-2 gap-2"><label class="text-xs">Duration override (min)<input type="number" name="service_specs[{{ $service->id }}][duration_minutes]" value="{{ $assignment?->duration_override_minutes }}" min="5" max="600" class="mt-1 min-h-10 w-full rounded-card border border-line px-2"></label><label class="text-xs">Price override (CAD)<input type="number" name="service_specs[{{ $service->id }}][price_amount]" value="{{ $assignment?->price_override_minor === null ? '' : number_format($assignment->price_override_minor/100,2) }}" min="0" step="0.01" class="mt-1 min-h-10 w-full rounded-card border border-line px-2"></label></div>
                                            </div>
                                        @endforeach
                                    </div></fieldset><button class="btn btn-secondary">Save profile & services</button>
                                </form>
                            @else<h3 class="font-medium">{{ $person->name }} <span class="font-normal text-muted-ink">· {{ $person->title }}</span></h3>@endif
                            <form method="post" action="{{ route('admin.staff.booking', $person) }}" class="mt-3 flex flex-wrap items-center gap-3">@csrf @method('PATCH')
                                <input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($person->is_active)>Active</label><input type="hidden" name="is_bookable" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_bookable" value="1" @checked($person->is_bookable)>Bookable</label>
                                <input type="hidden" name="accepts_new_clients" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="accepts_new_clients" value="1" @checked($person->accepts_new_clients)>Accept new clients</label><button class="btn btn-secondary">Save</button>
                            </form>
                            <h4 class="mt-5 font-semibold">Weekly hours</h4><div class="mt-2 space-y-2">@foreach (range(0,6) as $weekday)@php($hours=$person->workingHours->firstWhere('weekday',$weekday))
                                <form method="post" action="{{ route('admin.staff.hours', $person) }}" class="staff-hours-form grid grid-cols-[1fr_auto_auto_auto] items-center gap-2 border-t border-line pt-2">@csrf @method('PUT')
                                    <input type="hidden" name="weekday" value="{{ $weekday }}"><input type="hidden" name="enabled" value="0"><label class="staff-hours-day flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked($hours)>{{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$weekday] }}</label>
                                    <label class="text-xs">Opens<input type="time" name="opens_at" value="{{ $hours?substr((string)$hours->opens_at,0,5):'10:00' }}" class="mt-1 min-h-10 w-full rounded-card border border-line px-2"></label><label class="text-xs">Closes<input type="time" name="closes_at" value="{{ $hours?substr((string)$hours->closes_at,0,5):'19:00' }}" class="mt-1 min-h-10 w-full rounded-card border border-line px-2"></label><button class="text-sm underline">Save</button>
                                </form>
                            @endforeach</div>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif
