@extends('layouts.admin')

@section('title', 'Sign in — Gossips Beauty')

@section('content')
<section class="shell py-12">
    <div class="mx-auto max-w-md card p-7">
        <p class="eyebrow">Studio access</p>
        <h1 class="mt-3 font-display text-3xl">Sign in</h1>
        @if ($errors->any()) <p role="alert" class="mt-4 rounded-card border border-action p-3">{{ $errors->first() }}</p> @endif
        <form method="post" action="{{ route('admin.login.store') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block text-base font-medium">Email
                <input name="email" type="email" autocomplete="username" required value="{{ old('email') }}" class="mt-2 min-h-11 w-full rounded-card border border-line bg-surface px-4" />
            </label>
            <label class="block text-base font-medium">Password
                <input name="password" type="password" autocomplete="current-password" required class="mt-2 min-h-11 w-full rounded-card border border-line bg-surface px-4" />
            </label>
            <label class="flex items-center gap-2"><input name="remember" type="checkbox" value="1" class="h-5 w-5" /> Remember me</label>
            <button class="btn btn-primary w-full">Sign in</button>
        </form>
    </div>
</section>
@endsection
