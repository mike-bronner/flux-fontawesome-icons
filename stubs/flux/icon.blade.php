@props([
    "variant" => "outline",
])

@php
    $attributes = $unescapedForwardedAttributes
        ?? $attributes;
    $classes = Flux::classes("shrink-0 fill-current")
        ->add(match($variant) {
            "outline" => "[:where(&)]:size-6",
            "solid" => "[:where(&)]:size-6",
            "mini" => "[:where(&)]:size-5",
            "micro" => "[:where(&)]:size-4",
        });
@endphp

<svg
    {{ $attributes->class($classes) }}
    aria-hidden="true"
    data-flux-icon
    {SVG_ATTRIBUTES}
>

    @if ($variant === "outline")
        {OUTLINE}
    @else
        {SOLID}
    @endif

</svg>
