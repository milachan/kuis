@extends('layouts.teacher')

@section('title', $session->name)
@section('page-title', $session->name)
@section('page-subtitle', 'Kode sesi: '.$session->code)

@section('page-actions')
    <a href="{{ route('teacher.sessions.screen', $session) }}" class="btn-primary">🖥 Layar Proyektor</a>
    <a href="{{ route('teacher.sessions.edit', $session) }}" class="btn-secondary">Edit Sesi</a>

    @if ($session->isEnded())
        <form method="POST" action="{{ route('teacher.sessions.reopen', $session) }}">
            @csrf
            <button type="submit" class="btn-primary">Aktifkan Kembali</button>
        </form>
    @else
        <form method="POST" action="{{ route('teacher.sessions.end', $session) }}"
              data-confirm="Akhiri sesi ini? Siswa tidak dapat mengirim tugas baru.">
            @csrf
            <button type="submit" class="btn-secondary">Akhiri Sesi</button>
        </form>
    @endif
@endsection

@section('content')

    {{-- Ringkasan sesi --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="panel p-4">
            <p class="text-xs uppercase tracking-wider text-white/50">Kode Sesi</p>
            <p class="mt-1 font-mono text-xl font-bold text-cyan-accent">{{ $session->code }}</p>
        </div>
        <div class="panel p-4">
            <p class="text-xs uppercase tracking-wider text-white/50">Timer</p>
            <p class="mt-1 text-xl font-bold">
                @if ($session->hasTimer())
                    <span data-timer="{{ $session->secondsRemaining() }}">{{ $session->formattedRemaining() }}</span>
                @else
                    <span class="text-white/50">Tanpa batas</span>
                @endif
            </p>
        </div>
        <div class="panel p-4">
            <p class="text-xs uppercase tracking-wider text-white/50">Kelompok</p>
            <p class="mt-1 text-xl font-bold">{{ $teams->count() }}</p>
        </div>
        <div class="panel p-4">
            <p class="text-xs uppercase tracking-wider text-white/50">Status</p>
            <p class="mt-1">
                @if ($session->isEnded())
                    <span class="badge bg-white/5 text-white/40">BERAKHIR</span>
                @elseif ($session->isTimeUp())
                    <span class="badge bg-rose-500/15 text-rose-300">WAKTU HABIS</span>
                @else
                    <span class="badge bg-emerald-500/15 text-emerald-300">AKTIF</span>
                @endif
            </p>
        </div>
    </div>

    {{-- Mode permainan: kendali ronde --}}
    @php
        $missionList = $missionList; // daftar misi = daftar ronde
        $totalRounds = $missionList->count();
        $currentRound = $session->current_round;
        $nextRound = $currentRound + 1;
    @endphp

    <div class="panel mt-5 border-cyan-accent/20 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-cyan-accent">🎮 Mode Permainan (Lomba Ronde)</h2>
                <p class="mt-1 text-xs text-white/50">
                    Buka layar di proyektor, lalu tekan <strong>Mulai Ronde</strong>. Misi ronde itu langsung
                    terbuka untuk semua kelompok dan timer mulai berjalan.
                </p>
            </div>
            <a href="{{ route('teacher.sessions.screen', $session) }}" class="btn-primary">Buka Layar Proyektor</a>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            @if ($currentRound > 0)
                <span class="badge bg-cyan-strong/15 text-cyan-accent">
                    Ronde {{ $currentRound }} / {{ $totalRounds }}
                </span>
                <span class="badge
                    {{ $session->round_status === 'running'
                        ? 'bg-emerald-500/15 text-emerald-300'
                        : 'bg-white/5 text-white/50' }}">
                    {{ $session->round_status === 'running' ? 'BERJALAN' : strtoupper($session->round_status) }}
                </span>
                @if ($session->hasTimer() || $session->round_duration_minutes > 0)
                    <span class="text-xs text-white/50">
                        Timer ronde: {{ $session->formattedRoundRemaining() ?? 'tanpa batas' }}
                    </span>
                @endif
            @else
                <span class="badge bg-white/5 text-white/50">BELUM ADA RONDE BERJALAN</span>
            @endif

            <span class="badge {{ $session->lobby_locked ? 'bg-amber-500/15 text-amber-200' : 'bg-emerald-500/15 text-emerald-200' }}">
                {{ $session->lobby_locked ? 'LOBI TERKUNCI' : 'LOBI TERBUKA' }}
            </span>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            @if (! $session->isEnded() && $nextRound <= $totalRounds)
                <form method="POST" action="{{ route('teacher.sessions.round.start', $session) }}">
                    @csrf
                    <input type="hidden" name="round" value="{{ $nextRound }}">
                    <div class="flex items-center gap-2">
                        <button type="submit" class="btn-primary">▶ Mulai Ronde {{ $nextRound }}</button>
                        <input type="number" name="duration_minutes" min="0" max="120"
                               value="{{ $session->round_duration_minutes }}"
                               class="w-20 rounded-lg border border-white/15 bg-navy-900 px-2 py-1.5 text-sm text-white"
                               title="Durasi ronde (menit). 0 = tanpa batas.">
                        <span class="text-xs text-white/40">menit (0 = tanpa batas)</span>
                    </div>
                </form>
            @endif

            @if ($session->round_status === 'running')
                <form method="POST" action="{{ route('teacher.sessions.round.end', $session) }}">
                    @csrf
                    <button type="submit" class="btn-secondary">⏹ Akhiri Ronde</button>
                </form>
            @endif

            @if ($currentRound > 0 || $session->round_status !== 'idle')
                <form method="POST" action="{{ route('teacher.sessions.round.reset', $session) }}"
                      data-confirm="Kembalikan sesi ke mode lobi (ronde nol)?">
                    @csrf
                    <button type="submit" class="btn-secondary">↺ Mode Lobi</button>
                </form>
            @endif

            <form method="POST" action="{{ route('teacher.sessions.lobby', $session) }}">
                @csrf
                <button type="submit" class="btn-secondary">
                    {{ $session->lobby_locked ? '🔓 Buka Lobi' : '🔒 Kunci Lobi' }}
                </button>
            </form>
        </div>
    </div>

    {{-- Kode rahasia --}}
    <div class="panel mt-5 border-gold/20 p-5">
        <h2 class="mb-1 text-sm font-bold uppercase tracking-wider text-gold">🔑 Kode Rahasia Tiap Misi</h2>
        <p class="mb-4 text-xs text-white/50">
            Hanya guru yang melihat kode ini. Kode tidak pernah dikirim ke halaman siswa.
        </p>

        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($missionList as $mission)
                <div class="flex items-center justify-between gap-2 rounded-lg border border-white/10 bg-navy-900/50 px-3 py-2">
                    <span class="min-w-0 truncate text-xs text-white/70">{{ $mission->title }}</span>
                    <span class="shrink-0 font-mono text-sm font-bold text-gold">
                        {{ $codes[$mission->id] ?? '—' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Kelompok dan progres --}}
    <h2 class="mb-3 mt-6 text-sm font-semibold uppercase tracking-wider text-white/50">
        Kelompok & Progres
    </h2>

    @if ($teams->isEmpty())
        <div class="panel p-8 text-center">
            <p class="text-3xl">👥</p>
            <h3 class="mt-2 font-bold">Belum ada kelompok</h3>
            <p class="mt-1 text-sm text-white/50">
                Bagikan kode <span class="font-mono font-bold text-cyan-accent">{{ $session->code }}</span>
                kepada siswa untuk mulai bergabung.
            </p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($teams as $row)
                @php $team = $row['team']; @endphp
                <div class="panel p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('teacher.teams.show', $team) }}"
                               class="font-semibold text-white hover:text-cyan-accent">
                                {{ $team->name }}
                            </a>
                            <p class="mt-0.5 text-xs text-white/50">
                                {{ $team->members_count }} anggota ·
                                {{ $team->xp }} XP ·
                                Selesai {{ $row['completed'] }}/{{ $row['total'] }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($team->completed_at)
                                <span class="badge bg-emerald-500/15 text-emerald-300">SELESAI</span>
                            @else
                                <span class="badge bg-cyan-strong/15 text-cyan-accent">AKTIF</span>
                            @endif
                            <a href="{{ route('teacher.teams.show', $team) }}"
                               class="text-xs font-semibold text-cyan-accent hover:underline">Detail</a>
                        </div>
                    </div>

                    {{-- Progres per misi --}}
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($missionList as $mission)
                            @php $p = $row['progress'][$mission->id] ?? null; @endphp
                            <span class="badge border border-white/10
                                {{ $p?->status === 'completed'
                                    ? 'bg-emerald-500/15 text-emerald-300'
                                    : ($p?->isLocked() || ! $p
                                        ? 'bg-white/5 text-white/35'
                                        : 'bg-cyan-strong/10 text-cyan-accent') }}">
                                {{ $mission->order }}
                                {{ $p?->status === 'completed' ? '✓' : ($p?->isLocked() ? '🔒' : '●') }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Hapus sesi --}}
    <div class="panel mt-6 border-rose-400/20 p-5">
        <h2 class="text-sm font-bold uppercase tracking-wider text-rose-300">Zona Berbahaya</h2>
        <p class="mt-1 text-xs text-white/50">
            Menghapus sesi akan menghapus seluruh kelompok, bukti, dan progres di dalamnya.
            Tindakan ini tidak dapat dibatalkan.
        </p>
        <form method="POST"
              action="{{ route('teacher.sessions.destroy', $session) }}"
              class="mt-3"
              data-confirm="Hapus sesi {{ $session->name }} beserta seluruh datanya? Tindakan ini tidak dapat dibatalkan.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger">Hapus Sesi</button>
        </form>
    </div>

@endsection
