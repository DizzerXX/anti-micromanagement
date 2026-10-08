@props(['id', 'title', 'meta' => null])

<div class="ld-card-header">
    <h2 id="{{ $id }}" class="ld-card-title">{{ $title }}</h2>
    @if (filled($meta))
        <span class="ld-muted">{{ $meta }}</span>
    @endif
</div>
