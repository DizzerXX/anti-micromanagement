@props(['status'])

@php
    /** @var \App\Enums\TaskStatus $status */
@endphp

<span {{ $attributes->class(['ld-badge', 'ld-status-'.$status->color()]) }}>
    <x-ld.icon :name="$status->icon()" :size="14" />{{ $status->label() }}
</span>
