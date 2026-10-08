<nav class="admin-navigation" aria-label="Studio sections">
    <p class="admin-navigation-label">Daily work</p>
    <a href="{{ route('admin.index') }}" @if($section === 'bookings') aria-current="page" @endif>Bookings</a>
    @if ($canManage)
        <a href="{{ route('admin.customers.index') }}" @if($section === 'customers') aria-current="page" @endif>Customers</a>
        <p class="admin-navigation-label">Manage booking</p>
        <a href="{{ route('admin.services.index') }}" @if($section === 'services') aria-current="page" @endif>Services & extras</a>
    @endif
    @if ($isOwner)
        <a href="{{ route('admin.team.index') }}" @if($section === 'team') aria-current="page" @endif>Team & hours</a>
        <a href="{{ route('admin.availability.index') }}" @if($section === 'availability') aria-current="page" @endif>Time off & closures</a>
        <p class="admin-navigation-label">Administration</p>
        <a href="{{ route('admin.accounts.index') }}" @if($section === 'accounts') aria-current="page" @endif>Panel accounts</a>
    @endif
    <a href="{{ route('admin.settings.index') }}" @if($section === 'settings') aria-current="page" @endif>Settings</a>
</nav>
