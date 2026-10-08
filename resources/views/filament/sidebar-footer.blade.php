@php
    $active = request()->routeIs(\App\Filament\Pages\Settings::getRouteName());
@endphp

<div class="ld-sidebar-footer">
    <a
        href="{{ \App\Filament\Pages\Settings::getUrl() }}"
        @class(['ld-nav-link', 'ld-nav-link-active' => $active])
        @if ($active) aria-current="page" @endif
    >
        {{ svg('ledger-settings', 'ld-nav-icon', ['aria-hidden' => 'true']) }}
        Settings
    </a>
</div>
