@props([
    'label',
    'value',
    'icon' => '📊',
    'accent' => 'cyan',
    'hint' => null,
])

@php
    $accents = [
        'cyan' => 'text-cyan-accent bg-cyan-strong/10',
        'gold' => 'text-gold bg-gold/10',
        'emerald' => 'text-emerald-300 bg-emerald-500/10',
        'rose' => 'text-rose-300 bg-rose-500/10',
    ];
    $accentClass = $accents[$accent] ?? $accents['cyan'];
@endphp

<div class="panel p-4">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wider text-white/50">{{ $label }}</p>
            <p class="mt-1.5 text-2xl font-bold leading-none">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1.5 text-[11px] text-white/40">{{ $hint }}</p>
            @endif
        </div>

        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-lg {{ $accentClass }}">
            {{ $icon }}
        </span>
    </div>
</div>
