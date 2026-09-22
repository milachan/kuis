@extends('layouts.teacher')

@section('title', $team->name)
@section('page-title', $team->name)
@section('page-subtitle', 'Sesi: '.($team->gameSession->name ?? '-').' · '.$team->xp.' XP')

@section('page-actions')
    <a href="{{ route('teacher.sessions.show', $team->gameSession) }}" class="btn-secondary">Kembali ke Sesi</a>
@endsection

@section('content')

    {{-- Info kelompok --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="panel p-4">
            <p class="text-xs uppercase tracking-wider text-white/50">Anggota Kelompok</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @forelse ($team->members as $member)
                    <span class="badge bg-white/5 text-white/70">{{ $member->name }}</span>
                @empty
                    <span class="text-sm text-white/40">Tidak ada anggota.</span>
                @endforelse
            </div>
        </div>

        <div class="panel p-4">
            <p class="text-xs uppercase tracking-wider text-white/50">XP & Petunjuk</p>
            <p class="mt-1 text-2xl font-bold text-gold">{{ $team->xp }} XP</p>
            <p class="mt-0.5 text-xs text-white/50">{{ $team->totalHintsUsed() }} petunjuk dipakai</p>
        </div>

        <div class="panel p-4">
            <p class="text-xs uppercase tracking-wider text-white/50">Waktu</p>
            <p class="mt-1 text-xs text-white/70">
                Mulai: {{ $team->started_at?->format('d/m/Y H:i') ?? '-' }}
            </p>
            <p class="text-xs text-white/70">
                Selesai: {{ $team->completed_at?->format('d/m/Y H:i') ?? 'Belum selesai' }}
            </p>
        </div>
    </div>

    {{-- Progres misi --}}
    <h2 class="mb-3 mt-6 text-sm font-semibold uppercase tracking-wider text-white/50">Progres Misi</h2>

    <div class="space-y-3">
        @foreach ($missionList as $mission)
            @php
                $p = $progress[$mission->id] ?? null;
                $submission = $submissions[$mission->id] ?? null;
            @endphp

            <div class="panel p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold text-cyan-accent/70">MISI {{ $mission->numberLabel() }}</span>
                            @if ($p)
                                <x-status-badge :status="$p->status" />
                            @endif
                        </div>
                        <h3 class="mt-1 font-semibold">{{ $mission->title }}</h3>

                        @if ($p)
                            <p class="mt-1 text-xs text-white/50">
                                XP diperoleh: <span class="font-semibold text-gold">{{ $p->xp }}</span>
                                · Petunjuk: {{ $p->hints_used }}
                                @if ($p->completed_at)
                                    · Selesai: {{ $p->completed_at->format('d/m/Y H:i') }}
                                @endif
                            </p>
                        @endif
                    </div>

                    {{-- Aksi kunci/buka manual --}}
                    <div class="flex shrink-0 items-center gap-2">
                        @if ($submission)
                            <a href="{{ route('teacher.validations.show', $submission) }}"
                               class="btn-secondary text-xs">
                                Lihat Bukti
                            </a>
                        @endif

                        @if ($p && $p->isLocked())
                            <form method="POST" action="{{ route('teacher.teams.unlock', $team) }}">
                                @csrf
                                <input type="hidden" name="mission_id" value="{{ $mission->id }}">
                                <button type="submit" class="btn-primary text-xs">Buka Misi</button>
                            </form>
                        @elseif ($p && ! $p->isCompleted())
                            <form method="POST" action="{{ route('teacher.teams.lock', $team) }}"
                                  data-confirm="Kunci kembali misi ini?">
                                @csrf
                                <input type="hidden" name="mission_id" value="{{ $mission->id }}">
                                <button type="submit" class="btn-secondary text-xs">Kunci</button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Preview bukti --}}
                @if ($submission && $submission->evidence_path)
                    <div class="mt-3 flex items-center gap-3 rounded-lg border border-white/10 bg-navy-900/50 p-2.5">
                        @if ($submission->evidenceIsImage())
                            <a href="{{ $submission->evidenceUrl() }}" target="_blank">
                                <img src="{{ $submission->evidenceUrl() }}"
                                     alt="Bukti"
                                     class="h-12 w-12 rounded object-cover">
                            </a>
                        @else
                            <span class="grid h-12 w-12 place-items-center rounded bg-white/5 text-lg">📄</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs text-white/70">
                                {{ $submission->evidenceOriginalName() }}
                            </p>
                            <p class="text-[11px] text-white/40">
                                Dikirim {{ $submission->submitted_at?->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        <x-status-badge :status="$submission->status" type="submission" />
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Zona berbahaya --}}
    <div class="panel mt-6 border-rose-400/20 p-5">
        <h2 class="text-sm font-bold uppercase tracking-wider text-rose-300">Kelola Kelompok</h2>
        <p class="mt-1 text-xs text-white/50">
            Reset mengembalikan kelompok ke Misi 01 dan menghapus semua bukti. Hapus meniadakan kelompok sepenuhnya.
        </p>

        <div class="mt-3 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('teacher.teams.reset', $team) }}"
                  data-confirm="Reset progres {{ $team->name }}? Semua bukti dan XP akan dihapus.">
                @csrf
                <button type="submit" class="btn-secondary">Reset Progres</button>
            </form>

            <form method="POST" action="{{ route('teacher.teams.destroy', $team) }}"
                  data-confirm="Hapus kelompok {{ $team->name }} beserta seluruh datanya?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger">Hapus Kelompok</button>
            </form>
        </div>
    </div>

@endsection
