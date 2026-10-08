{{-- Ledger: the account button shows a person icon, not initials (mockups). --}}
@props([
    'user' => filament()->auth()->user(),
])

<span {{ $attributes->class(['fi-user-avatar', 'ld-avatar']) }}>
    {{ svg('ledger-user', '', ['width' => 18, 'height' => 18, 'aria-hidden' => 'true']) }}
</span>
