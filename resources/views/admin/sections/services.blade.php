<div class="space-y-6">
    <section class="card p-5 md:p-6" aria-labelledby="services-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="services-heading">Services</h2>
                <p class="mt-1 text-muted-ink">Select a service to edit its public details and booking options.</p>
            </div>
            @if ($isOwner)
                <details class="admin-create-panel w-full md:w-auto">
                    <summary class="btn btn-primary cursor-pointer">Add service</summary>
                    <form method="post" action="{{ route('admin.services.store') }}" class="mt-4 grid gap-3 rounded-card border border-line bg-canvas p-4 sm:grid-cols-2">
                        @csrf
                        <label class="text-sm">Category<select name="service_category_id" required class="mt-1 min-h-11 w-full rounded-card border border-line px-3">@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
                        <label class="text-sm">Service name<input name="name" required maxlength="160" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                        <label class="text-sm">Duration in minutes<input name="duration_minutes" type="number" min="5" max="600" required class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                        <label class="text-sm">Price type<select name="price_mode" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"><option value="fixed">Fixed</option><option value="from">From</option><option value="quote">Consultation</option><option value="free">Free</option></select></label>
                        <label class="text-sm">CAD amount, if applicable<input name="price_amount" type="number" min="0" step="0.01" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                        <button class="btn btn-primary self-end">Create service</button>
                    </form>
                </details>
            @endif
        </div>
        @php
            $serviceGroups = $services->groupBy('service_category_id');
        @endphp
        <nav class="admin-category-links mt-6" aria-label="Service categories">
            @foreach ($serviceGroups as $categoryId => $group)
                <a href="#service-category-{{ $categoryId }}">{{ $group->first()->category?->name ?? 'Other' }}</a>
            @endforeach
        </nav>
        @foreach ($serviceGroups as $categoryId => $group)
            <section id="service-category-{{ $categoryId }}" class="mt-7 scroll-mt-24" aria-labelledby="service-category-title-{{ $categoryId }}">
                <div class="flex items-baseline justify-between gap-3 border-b border-line pb-2">
                    <h3 id="service-category-title-{{ $categoryId }}">{{ $group->first()->category?->name ?? 'Other' }}</h3>
                    <span class="text-sm text-muted-ink">{{ $group->count() }} services</span>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($group as $service)
                        @php
                            $priceLabel = match ($service->price_mode) {
                                'fixed' => '$'.number_format(((int) $service->price_minor) / 100, 2),
                                'from' => 'From $'.number_format(((int) $service->price_minor) / 100, 2),
                                'free' => 'No charge',
                                default => 'Consultation',
                            };
                        @endphp
                        <li class="py-3">
                            <details class="admin-editor">
                                <summary>
                                    <span class="font-semibold">{{ $service->name }}</span>
                                    <span class="text-sm text-muted-ink">{{ $service->duration_minutes ? $service->duration_minutes.' min' : 'Duration needed' }} · {{ $priceLabel }} · {{ $service->is_online_bookable ? 'Online' : 'In studio only' }}{{ $service->is_active ? '' : ' · Archived' }}</span>
                                </summary>
                                <div class="admin-editor-body mt-4">
                                    <form method="post" action="{{ route('admin.services.update', $service) }}" class="grid gap-3 sm:grid-cols-2">
                                        @csrf @method('PUT')
                                        <label class="text-sm">Service name<input name="name" value="{{ $service->name }}" required maxlength="160" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                                        <label class="text-sm">Duration in minutes<input name="duration_minutes" value="{{ $service->duration_minutes }}" type="number" min="5" max="600" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                                        <label class="text-sm sm:col-span-2">Public description<textarea name="summary" rows="3" maxlength="2000" class="mt-1 w-full rounded-card border border-line p-3">{{ $service->summary }}</textarea></label>
                                        <label class="text-sm">Price type<select name="price_mode" class="mt-1 min-h-11 w-full rounded-card border border-line px-3">@foreach(['fixed'=>'Fixed','from'=>'From','quote'=>'Consultation','free'=>'Free'] as $mode=>$label)<option value="{{ $mode }}" @selected($service->price_mode===$mode)>{{ $label }}</option>@endforeach</select></label>
                                        <label class="text-sm">CAD amount, if applicable<input name="price_amount" value="{{ $service->price_minor === null ? '' : number_format($service->price_minor / 100, 2) }}" type="number" min="0" step="0.01" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                                        <label class="text-sm">Buffer before, minutes<input name="buffer_before_minutes" value="{{ $service->buffer_before_minutes }}" type="number" min="0" max="240" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                                        <label class="text-sm">Buffer after, minutes<input name="buffer_after_minutes" value="{{ $service->buffer_after_minutes }}" type="number" min="0" max="240" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                                        <div class="flex flex-wrap items-center gap-4 sm:col-span-2"><input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($service->is_active)>Active service</label><button class="btn btn-primary">Save service</button></div>
                                    </form>
                                    <div class="mt-5 border-t border-line pt-4">
                                        <form method="post" action="{{ route('admin.services.booking', $service) }}" class="flex flex-wrap items-center justify-between gap-3">
                                            @csrf @method('PATCH')
                                            <p class="text-sm">Online booking: <strong>{{ $service->is_online_bookable ? 'Available' : 'Unavailable' }}</strong></p>
                                            <input type="hidden" name="is_online_bookable" value="{{ $service->is_online_bookable ? 0 : 1 }}">
                                            <button class="btn btn-secondary">{{ $service->is_online_bookable ? 'Remove from online booking' : 'Allow online booking' }}</button>
                                        </form>
                                        <details class="mt-4"><summary class="cursor-pointer font-semibold">Compatible extras</summary>
                                            <form method="post" action="{{ route('admin.services.add-ons', $service) }}" class="mt-3 space-y-3">@csrf @method('PATCH')
                                                <div class="grid gap-2 sm:grid-cols-2">@foreach($addOns as $addOn)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="add_on_ids[]" value="{{ $addOn->id }}" @checked($service->addOns->contains('id', $addOn->id))>{{ $addOn->name }}</label>@endforeach</div>
                                                <button class="btn btn-secondary">Save compatible extras</button>
                                            </form>
                                        </details>
                                    </div>
                                </div>
                            </details>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </section>

    <section class="card p-5 md:p-6" aria-labelledby="extras-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h2 id="extras-heading">Extras</h2><p class="mt-1 text-muted-ink">Set extra time and price, then attach each extra to compatible services above.</p></div>
            @if ($isOwner)
                <details class="admin-create-panel w-full md:w-auto"><summary class="btn btn-primary cursor-pointer">Add extra</summary>
                    <form method="post" action="{{ route('admin.add-ons.store') }}" class="mt-4 grid gap-3 rounded-card border border-line bg-canvas p-4 sm:grid-cols-2">@csrf
                        <label class="text-sm">Extra name<input name="name" required class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                        <label class="text-sm">Extra minutes<input name="duration_minutes" type="number" min="0" max="600" required class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                        <label class="text-sm">Price type<select name="price_mode" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"><option value="fixed">Fixed</option><option value="from">From</option><option value="quote">Consultation</option><option value="free">Free</option></select></label>
                        <label class="text-sm">CAD amount, if applicable<input name="price_amount" type="number" min="0" step="0.01" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                        <button class="btn btn-primary sm:col-span-2 sm:justify-self-start">Create extra</button>
                    </form>
                </details>
            @endif
        </div>
        <ul class="mt-5 divide-y divide-line">
            @foreach ($addOns as $addOn)
                <li class="py-3"><details class="admin-editor"><summary><span class="font-semibold">{{ $addOn->name }}</span><span class="text-sm text-muted-ink">{{ $addOn->duration_minutes }} min · {{ $addOn->is_active ? 'Active' : 'Archived' }}</span></summary>
                    <form method="post" action="{{ route('admin.add-ons.update', $addOn) }}" class="admin-editor-body mt-4 grid gap-3 sm:grid-cols-2">@csrf @method('PUT')
                        <label class="text-sm">Extra name<input name="name" value="{{ $addOn->name }}" required class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                        <label class="text-sm">Extra minutes<input name="duration_minutes" type="number" min="0" max="600" value="{{ $addOn->duration_minutes }}" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                        <label class="text-sm sm:col-span-2">Description<input name="description" value="{{ $addOn->description }}" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                        <label class="text-sm">Price type<select name="price_mode" class="mt-1 min-h-11 w-full rounded-card border border-line px-3">@foreach(['fixed'=>'Fixed','from'=>'From','quote'=>'Consultation','free'=>'Free'] as $mode=>$label)<option value="{{ $mode }}" @selected($addOn->price_mode===$mode)>{{ $label }}</option>@endforeach</select></label>
                        <label class="text-sm">CAD amount, if applicable<input name="price_amount" type="number" min="0" step="0.01" value="{{ $addOn->price_minor === null ? '' : number_format($addOn->price_minor / 100, 2) }}" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                        <div class="flex flex-wrap items-center gap-4 sm:col-span-2"><input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($addOn->is_active)>Active extra</label><button class="btn btn-primary">Save extra</button></div>
                    </form>
                </details></li>
            @endforeach
        </ul>
    </section>
</div>
