@extends('layouts.teacher')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard Guru')
@section('page-subtitle', 'Ringkasan aktivitas TIK Mission · Bab 4 Sistem Komputer')

@section('page-actions')
    <a href="{{ route('teacher.sessions.create') }}" class="btn-primary">+ Buat Sesi Baru</a>
@endsection

@section('content')

    {{-- Statistik utama --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Sesi Aktif" :value="$stats['active_sessions']" icon="🎮" accent="cyan"
                     hint="Sesi yang sedang berjalan" />
        <x-stat-card label="Total Kelompok" :value="$stats['total_teams']" icon="👥" accent="emerald"
                     hint="Dari semua sesi" />
        <x-stat-card label="Misi Selesai" :value="$stats['completed_missions']" icon="✅" accent="gold"
                     hint="Total misi tuntas semua kelompok" />
        <x-stat-card label="Perlu Diperiksa" :value="$stats['waiting_validation']" icon="⏳" accent="rose"
                     hint="Jawaban yang menunggu validasi" />
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-2">

        {{-- Perlu diperiksa --}}
        <div class="panel p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wider text-amber-200">⏳ Perlu Diperiksa</h2>
                <a href="{{ route('teacher.validations') }}" class="text-xs font-semibold text-cyan-accent hover:underline">
                    Lihat semua
                </a>
            </div>

            @forelse ($recentSubmissions as $submission)
                <a href="{{ route('teacher.validations.show', $submission) }}"
                   class="mb-2 flex items-center justify-between gap-3 rounded-lg border border-white/10 bg-navy-900/50 p-3 transition hover:border-cyan-accent/40">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $submission->team->name ?? '-' }}</p>
                        <p class="truncate text-xs text-white/50">{{ $submission->mission->title ?? '-' }}</p>
                    </div>
                    <span class="shrink-0 text-[11px] text-white/40">
                        {{ $submission->submitted_at?->diffForHumans() }}
                    </span>
                </a>
            @empty
                <p class="py-6 text-center text-sm text-white/40">
                    Tidak ada yang perlu diperiksa. Kerja bagus! 🎉
                </p>
            @endforelse
        </div>

        {{-- Sesi aktif --}}
        <div class="panel p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wider text-cyan-accent">🎮 Sesi Aktif</h2>
                <a href="{{ route('teacher.sessions.index') }}" class="text-xs font-semibold text-cyan-accent hover:underline">
                    Lihat semua
                </a>
            </div>

            @forelse ($activeSessions as $session)
                <a href="{{ route('teacher.sessions.show', $session) }}"
                   class="mb-2 flex items-center justify-between gap-3 rounded-lg border border-white/10 bg-navy-900/50 p-3 transition hover:border-cyan-accent/40">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $session->name }}</p>
                        <p class="text-xs text-white/50">
                            Kode: <span class="font-mono font-semibold text-cyan-accent">{{ $session->code }}</span>
                        </p>
                    </div>
                    <span class="badge shrink-0 bg-white/5 text-white/60">
                        {{ $session->teams_count }} kelompok
                    </span>
                </a>
            @empty
                <p class="py-6 text-center text-sm text-white/40">
                    Belum ada sesi aktif.
                    <a href="{{ route('teacher.sessions.create') }}" class="font-semibold text-cyan-accent hover:underline">
                        Buat sesi pertama
                    </a>
                </p>
            @endforelse
        </div>
    </div>

    {{-- Panduan singkat --}}
    <div class="panel mt-6 p-5">
        <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-white/70">📌 Alur Cepat</h2>
        <ol class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['1', 'Buat Sesi', 'Atur kode sesi, timer, dan kode rahasia tiap ronde.'],
                ['2', 'Bagikan Kode', 'Siswa masuk memakai kode sesi + nama kelompok.'],
                ['3', 'Jalankan Ronde', 'Buka Layar Proyektor, lalu tekan Mulai Ronde.'],
                ['4', 'Periksa Jawaban', 'Lihat skor AI & hasil game, lalu Lulus atau minta perbaikan.'],
            ] as $step)
                <li class="flex gap-3">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-cyan-strong/15 text-xs font-bold text-cyan-accent">
                        {{ $step[0] }}
                    </span>
                    <span>
                        <strong class="block text-white/90">{{ $step[1] }}</strong>
                        <span class="text-xs text-white/50">{{ $step[2] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </div>

@endsection
