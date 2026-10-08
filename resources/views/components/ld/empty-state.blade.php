@props(['icon', 'heading', 'body' => null])

<div {{ $attributes->class(['ld-empty']) }}>
    <div class="ld-empty-icon"><x-ld.icon :name="$icon" :size="24" /></div>
    <h2 class="ld-empty-heading">{{ $heading }}</h2>
    @if ($body)
        <p class="ld-empty-body">{{ $body }}</p>
    @endif
    {{ $slot }}
</div>
