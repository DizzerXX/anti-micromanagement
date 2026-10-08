@props(['name', 'size' => 16, 'label' => null])

{{ svg('ledger-'.$name, $attributes->get('class', ''), array_filter([
    'width' => $size,
    'height' => $size,
    'aria-hidden' => $label === null ? 'true' : null,
    'aria-label' => $label,
    'role' => $label === null ? null : 'img',
    'style' => $attributes->get('style'),
])) }}
