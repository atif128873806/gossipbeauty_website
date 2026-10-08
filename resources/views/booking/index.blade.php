@extends('layouts.app')

@section('title', 'Book an appointment — Gossips Beauty')
@section('meta_description', 'Choose a service, any extras and a time at Gossips Beauty, 649 The Queensway, Etobicoke.')

@section('content')

    <section class="page-intro story-section border-b border-line bg-canvas">
        <div class="shell py-12 lg:py-16">
            <div class="booking-intro grid items-center gap-8 lg:grid-cols-[1fr_0.7fr] lg:gap-14">
            <div class="max-w-3xl">
                <h1 class="page-title font-display text-[2.4rem] leading-[1.02] text-ink sm:text-5xl rise"
                    style="--reveal-delay: 40ms">
                    Book an appointment
                </h1>

                <p class="mt-5 text-lg leading-relaxed text-muted-ink rise" style="--reveal-delay: 140ms">
                    Choose a service, add anything that is charged separately, and pick a time.
                    Every choice can be changed before the last step.
                </p>
            </div>
            <x-photo key="polish" alt="" class="booking-intro-photo aspect-[16/9]" sizes="(min-width: 1024px) 34vw, 92vw" />
            </div>
        </div>
    </section>

    <section class="booking-scene section bg-canvas">
        <div class="shell">
            @if ($onlineBookingEnabled)
                <livewire:booking-journey :service="$serviceSlug" />
            @else
                <div class="panel-note max-w-3xl p-6">
                    <h2 class="font-display text-2xl text-ink">Online booking is currently closed</h2>
                    <p class="mt-3 text-base leading-relaxed text-muted-ink">Please contact Gossips Beauty directly to arrange an appointment.</p>
                </div>
            @endif
        </div>
    </section>

@endsection
