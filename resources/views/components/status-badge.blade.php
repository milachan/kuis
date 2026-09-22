@props(['status', 'type' => 'progress'])

@php
    // Peta status misi → label, warna, dan ikon.
    $map = [
        'locked' => ['label' => 'TERKUNCI', 'class' => 'bg-white/5 text-white/40', 'icon' => '🔒'],
        'available' => ['label' => 'TERSEDIA', 'class' => 'bg-cyan-strong/15 text-cyan-accent', 'icon' => '▶'],
        'in_progress' => ['label' => 'DIKERJAKAN', 'class' => 'bg-gold/15 text-gold', 'icon' => '●'],
        'waiting_validation' => ['label' => 'MENUNGGU VALIDASI', 'class' => 'bg-amber-400/15 text-amber-200', 'icon' => '⏳'],
        'completed' => ['label' => 'SELESAI', 'class' => 'bg-emerald-500/15 text-emerald-300', 'icon' => '✓'],
    ];

    // Peta status kiriman → label dan warna.
    $submissionMap = [
        'waiting_validation' => ['label' => 'MENUNGGU VALIDASI', 'class' => 'bg-amber-400/15 text-amber-200', 'icon' => '⏳'],
        'approved' => ['label' => 'LULUS', 'class' => 'bg-emerald-500/15 text-emerald-300', 'icon' => '✓'],
        'revision' => ['label' => 'PERLU PERBAIKAN', 'class' => 'bg-rose-500/15 text-rose-300', 'icon' => '✗'],
    ];

    $data = $type === 'submission'
        ? ($submissionMap[$status] ?? ['label' => strtoupper($status), 'class' => 'bg-white/5 text-white/60', 'icon' => '•'])
        : ($map[$status] ?? ['label' => strtoupper($status), 'class' => 'bg-white/5 text-white/60', 'icon' => '•']);
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$data['class']]) }}>
    <span>{{ $data['icon'] }}</span>
    {{ $data['label'] }}
</span>
