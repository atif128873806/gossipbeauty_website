@extends('layouts.admin')

@section('title', 'Studio panel — Gossips Beauty')

@section('content')
<div class="shell admin-layout py-7 md:py-9">
    @php
        $pageTitle = match ($section) {
            'customers' => 'Customers',
            'services' => 'Services & extras',
            'team' => 'Team & hours',
            'availability' => 'Time off & closures',
            'accounts' => 'Panel accounts',
            'settings' => 'Settings',
            default => 'Bookings',
        };
        $pageDescription = match ($section) {
            'customers' => 'Find contact details, history and studio notes.',
            'services' => 'Edit bookable services, duration, price and extras.',
            'team' => 'Manage professionals, eligible services and weekly hours.',
            'availability' => 'Manage breaks, time off and studio closures.',
            'accounts' => 'Control access to the studio panel.',
            'settings' => 'Set online booking rules and update your password.',
            default => 'Manage upcoming visits, find bookings and view the calendar.',
        };
    @endphp
    <aside class="admin-sidebar" aria-label="Panel menu">@include('admin.navigation')</aside>
    <details class="admin-mobile-menu">
        <summary>Sections <span class="font-medium">{{ $pageTitle }}</span></summary>
        @include('admin.navigation')
    </details>
    <div class="admin-main min-w-0">
    @if (session('success')) <p role="status" class="mb-5 rounded-card border border-line bg-surface p-4">{{ session('success') }}</p> @endif
    @if ($errors->any()) <p role="alert" class="mb-5 rounded-card border border-action bg-surface p-4">{{ $errors->first() }}</p> @endif
    <header class="admin-page-header mb-6">
        <h1>{{ $pageTitle }}</h1>
        <p class="mt-1 text-muted-ink">{{ $pageDescription }}</p>
    </header>
    @include('admin.sections.'.$section)
    </div>
</div>
@endsection
