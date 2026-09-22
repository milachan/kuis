@extends('layouts.teacher')

@section('title', 'Validasi')
@section('page-title', 'Validasi Bukti')
@section('page-subtitle', 'Periksa bukti praktik siswa, lalu nyatakan lulus atau minta perbaikan')

@section('content')

    {{-- Tab filter --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @php
            $tabs = [
                'waiting_validation' => ['label' => 'Menunggu Validasi', 'count' => $counts['waiting_validation']],
                'approved' => ['label' => 'Lulus', 'count' => $counts['approved']],
                'revision' => ['label' => 'Perlu Perbaikan', 'count' => $counts['revision']],
                'all' => ['label' => 'Semua', 'count' => null],
            ];
        @endphp

        @foreach ($tabs as $key => $tab)
            @php
                $isActive = $statusFilter === $key;
            @endphp
            <a href="{{ route('teacher.validations', ['status' => $key]) }}"
               class="rounded-lg border px-3.5 py-2 text-sm font-medium transition
                      {{ $isActive
                            ? 'border-cyan-accent/50 bg-cyan-strong/15 text-cyan-accent'
                            : 'border-white/10 text-white/60 hover:border-white/25 hover:text-white' }}">
                {{ $tab['label'] }}
                @if ($tab['count'] !== null)
                    <span class="ml-1 {{ $tab['count'] > 0 ? 'text-gold' : 'text-white/40' }}">
                        ({{ $tab['count'] }})
                    </span>
                @endif
            </a>
        @endforeach
    </div>

    @if ($submissions->isEmpty())
        <div class="panel p-10 text-center">
            <p class="text-4xl">✅</p>
            <h3 class="mt-3 text-lg font-bold">Tidak ada data</h3>
            <p class="mt-1 text-sm text-white/50">Belum ada kiriman pada kategori ini.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($submissions as $submission)
                <a href="{{ route('teacher.validations.show', $submission) }}"
                   class="panel flex flex-wrap items-center gap-4 p-4 transition hover:border-cyan-accent/40">

                    {{-- Thumbnail bukti --}}
                    <div class="shrink-0">
                        @if ($submission->evidenceIsImage())
                            <img src="{{ $submission->evidenceUrl() }}"
                                 alt="Bukti"
                                 class="h-16 w-16 rounded-lg border border-white/10 object-cover">
                        @else
                            <span class="grid h-16 w-16 place-items-center rounded-lg border border-white/10 bg-white/5 text-2xl">
                                📄
                            </span>
                        @endif
                    </div>

                    {{-- Info --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $submission->team->name ?? '-' }}</span>
                            <x-status-badge :status="$submission->status" type="submission" />
                        </div>
                        <p class="mt-0.5 truncate text-sm text-white/70">
                            {{ $submission->mission->title ?? '-' }}
                        </p>
                        <p class="mt-0.5 text-xs text-white/40">
                            {{ $submission->team->gameSession->name ?? '-' }}
                            · Dikirim {{ $submission->submitted_at?->format('d/m/Y H:i') ?? '-' }}
                        </p>
                    </div>

                    <span class="shrink-0 text-xs font-semibold text-cyan-accent">Periksa →</span>
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $submissions->links() }}</div>
    @endif

@endsection
