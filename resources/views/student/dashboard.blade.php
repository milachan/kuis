@extends('layouts.student')

@section('title', 'Dashboard Misi')

@push('scripts')
    @vite('resources/js/round-watch.js')
@endpush

@section('content')

    {{-- Pengawas ronde: memberi tahu saat guru membuka ronde baru. --}}
    <div data-round-watch="{{ route('student.round.status') }}"
         data-auto-redirect="false"
         data-current-mission="0"
         class="hidden"></div>

    {{-- Judul --}}
    <div class="mb-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-cyan-accent/80">Operasi File Rahasia</p>
        <h1 class="mt-1 text-xl font-bold sm:text-2xl">{{ $team->name }}</h1>
        <p class="mt-1 text-sm text-white/60">
            Sesi: <span class="font-semibold text-white/80">{{ $session->name }}</span>
            · Kode: <span class="font-mono font-semibold text-cyan-accent">{{ $session->code }}</span>
        </p>
    </div>

    {{-- Progres --}}
    <div class="panel mb-6 p-4">
        <div class="flex items-center justify-between text-sm">
            <span class="font-semibold text-white/80">Progress Misi</span>
            <span class="font-bold text-cyan-accent">{{ $percent }}%</span>
        </div>

        <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-navy-950">
            <div class="h-full rounded-full bg-gradient-to-r from-cyan-strong to-teal-accent transition-all duration-500"
                 style="width: {{ $percent }}%"></div>
        </div>

        <p class="mt-2 text-xs text-white/50">
            {{ $completed }} dari {{ $total }} misi selesai · {{ $team->xp }} XP
            @if ($team->totalHintsUsed() > 0)
                · {{ $team->totalHintsUsed() }} petunjuk dipakai
            @endif
        </p>
    </div>

    {{-- Anggota kelompok --}}
    @if ($team->members->isNotEmpty())
        <div class="panel mb-6 p-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-white/50">Anggota Kelompok</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($team->members as $member)
                    <span class="badge border border-white/15 bg-white/5 text-white/80">{{ $member->name }}</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Waktu habis --}}
    @if ($session->isTimeUp())
        <div class="mb-6 rounded-xl border border-rose-400/30 bg-rose-500/10 p-4 text-sm text-rose-200">
            <strong>Waktu sesi sudah habis.</strong>
            Kiriman baru tidak dapat diproses, tetapi hasil yang sudah kamu kirim tetap tersimpan.
        </div>
    @endif

    {{-- ============================ KARTU MISI ============================ --}}
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-white/50">Daftar Misi</h2>

    <div class="grid gap-3 lg:grid-cols-2">
        @foreach ($progressList as $progress)
            @php
                $mission = $progress->mission;
                $isCompleted = $progress->status === \App\Models\TeamProgress::STATUS_COMPLETED;
                $isLocked = $progress->isLocked();
                $isWaiting = $progress->status === \App\Models\TeamProgress::STATUS_WAITING_VALIDATION;
            @endphp

            @if ($isLocked)
                {{-- Kartu misi terkunci --}}
                <div class="mission-card border-white/5 bg-navy-900/40 opacity-70">
                    <div class="flex items-start gap-3">
                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white/5 text-lg">
                            🔒
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider text-white/40">
                                Misi {{ $mission->numberLabel() }}
                            </p>
                            <h3 class="mt-0.5 truncate font-semibold text-white/60">{{ $mission->title }}</h3>
                            <p class="mt-1 text-xs text-white/40">
                                Selesaikan misi sebelumnya untuk membuka misi ini.
                            </p>
                        </div>
                    </div>
                    <span class="badge absolute right-3 top-3 bg-white/5 text-white/40">TERKUNCI</span>
                </div>
            @else
                {{-- Kartu misi yang bisa dikerjakan --}}
                <a href="{{ route('student.mission.show', $mission) }}"
                   class="mission-card group border-white/10 bg-navy-800/60 hover:border-cyan-accent/50 hover:bg-navy-800">
                    <div class="flex items-start gap-3">
                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-lg
                                    {{ $isCompleted ? 'bg-emerald-500/15 text-emerald-300' : 'bg-cyan-strong/15 text-cyan-accent' }}">
                            @if ($isCompleted) ✓
                            @elseif ($isWaiting) ⏳
                            @else ▶
                            @endif
                        </div>

                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider text-cyan-accent/70">
                                Misi {{ $mission->numberLabel() }}
                            </p>
                            <h3 class="mt-0.5 truncate font-semibold text-white group-hover:text-cyan-accent">
                                {{ $mission->title }}
                            </h3>
                            <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-white/50">
                                {{ $mission->story }}
                            </p>

                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                <span class="badge bg-white/5 text-white/60">
                                    {{ $mission->difficulty }}
                                </span>
                                <span class="badge bg-gold/10 text-gold">
                                    {{ $mission->xp }} XP
                                </span>
                                @if ($isCompleted)
                                    <span class="badge bg-emerald-500/15 text-emerald-300">SELESAI</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            @endif
        @endforeach
    </div>

    {{-- Selesai semua --}}
    @if ($completed >= $total && $total > 0)
        <div class="mt-6 rounded-xl border border-emerald-400/30 bg-emerald-500/10 p-5 text-center">
            <p class="text-2xl">🎉</p>
            <h3 class="mt-1 text-lg font-bold text-emerald-200">Semua Misi Selesai!</h3>
            <p class="mt-1 text-sm text-emerald-200/80">
                Tim {{ $team->name }} berhasil menuntaskan Operasi File Rahasia dengan {{ $team->xp }} XP.
            </p>
        </div>
    @endif

@endsection
