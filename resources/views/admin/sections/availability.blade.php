        @if ($isOwner)
            <section class="mt-8 grid gap-6 lg:grid-cols-2">
                <div class="card p-6">
                    <p class="eyebrow">Time off and breaks</p><h2 class="mt-2 font-display text-2xl">Block availability</h2>
                    <p class="mt-2 text-sm text-muted-ink">Blocks are checked against current appointments before they are saved.</p>
                    <form method="post" action="{{ route('admin.availability.exceptions.store') }}" class="mt-4 grid gap-3">@csrf
                        <label class="text-sm">Applies to<select name="staff_id" class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"><option value="">Entire studio</option>@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>
                        <label class="text-sm">Type<select name="kind" class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"><option value="break">Break</option><option value="time_off">Time off</option><option value="closure">Studio closure</option></select></label>
                        <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm">Starts<input name="starts_at" type="datetime-local" required class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label><label class="text-sm">Ends<input name="ends_at" type="datetime-local" required class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label></div>
                        <label class="text-sm">Note (optional)<input name="label" maxlength="160" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label><button class="btn btn-primary justify-self-start">Save block</button>
                    </form>
                </div>
                <div class="card p-6"><p class="eyebrow">Scheduled blocks</p><h2 class="mt-2 font-display text-2xl">Exceptions</h2>
                    @if($exceptions->isEmpty())<p class="mt-4 text-sm text-muted-ink">No active time off, breaks or closures.</p>@else<ul class="mt-4 divide-y divide-line">@foreach($exceptions as $exception)<li class="flex items-center justify-between gap-3 py-3"><div><p class="font-medium">{{ ucfirst(str_replace('_',' ',$exception->kind)) }} · {{ $exception->staff?->name ?? 'Entire studio' }}</p><p class="text-sm text-muted-ink">{{ $exception->starts_at_utc->setTimezone('America/Toronto')->format('M j, g:i A') }} – {{ $exception->ends_at_utc->setTimezone('America/Toronto')->format('M j, g:i A') }} {{ $exception->label ? '· '.$exception->label : '' }}</p></div><form method="post" action="{{ route('admin.availability.exceptions.delete',$exception) }}">@csrf @method('DELETE')<button class="text-sm underline">Remove</button></form></li>@endforeach</ul>@endif
                </div>
            </section>
        @endif
