@extends('layouts.teacher')

@section('title', 'Layar Proyektor — '.$session->name)
@section('page-title', 'Layar Proyektor')
@section('page-subtitle', 'Tampilkan halaman ini di TV/proyektor untuk dilihat seluruh kelas')

@push('scripts')
    @vite('resources/js/screen.js')
@endpush

@section('page-actions')
    <a href="{{ route('teacher.sessions.show', $session) }}" class="btn-secondary">⚙ Kendali Ronde</a>
    <button type="button" data-screen-fullscreen class="btn-primary">Layar Penuh</button>
@endsection

@section('content')

    {{-- Parameter ronde untuk tampilan. --}}
    @php
        $currentRound = $session->current_round;
        $roundStatus = $session->round_status;
    @endphp

    <div data-screen-root
         data-session-id="{{ $session->id }}"
         data-live-url="{{ route('teacher.sessions.live', $session) }}">

        {{--
            Layar proyektor ini MURNI TAMPILAN: tidak ada tombol kendali ronde
            di sini supaya guru tidak bingung memilih layar. Kendali ronde ada
            di halaman sesi (tombol "Kendali Ronde" di atas).
        --}}
        <div class="panel mb-5 flex flex-wrap items-center justify-between gap-3 p-4">
            <div class="flex items-center gap-2">
                <span class="text-xl">📺</span>
                <div>
                    <p class="text-sm font-bold">Layar Proyektor</p>
                    <p class="text-xs text-white/50">
                        Tampilan untuk siswa. Kendalikan ronde dari halaman sesi guru.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs">
                @if ($session->lobby_locked)
                    <span class="badge bg-amber-500/15 text-amber-200">SISWA BARU TIDAK BISA MASUK</span>
                @else
                    <span class="badge bg-emerald-500/15 text-emerald-200">SISWA BARU BISA MASUK</span>
                @endif
                <span class="text-white/40" data-ai-indicator>AI: memeriksa…</span>
            </div>
        </div>

        {{-- ===================== BAGIAN BESAR UNTUK PROYEKTOR ===================== --}}
        <div id="tik-screen" class="rounded-3xl border border-white/10 bg-gradient-to-b from-navy-900 to-navy-950 p-6 sm:p-10">

            {{-- Header: judul sesi + kode + timer --}}
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div class="min-w-0">
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-cyan-accent">TIK MISSION</p>
                    <h1 id="scr-session-name" class="mt-1 truncate text-3xl font-black tracking-tight sm:text-5xl">
                        {{ $session->name }}
                    </h1>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <span class="text-sm text-white/50">Kode sesi</span>
                        <span id="scr-code"
                              class="rounded-xl bg-white/5 px-4 py-1.5 font-mono text-2xl font-black tracking-widest text-cyan-accent sm:text-3xl">
                            {{ $session->code }}
                        </span>
                    </div>
                </div>

                {{-- Ronde + timer besar --}}
                <div class="text-right">
                    <p id="scr-round-label" class="text-sm uppercase tracking-widest text-white/50">Ronde</p>
                    <p class="font-black leading-none">
                        <span id="scr-round" class="text-6xl sm:text-7xl">{{ $currentRound > 0 ? $currentRound : '—' }}</span>
                        <span class="text-2xl text-white/30 sm:text-3xl">/ {{ $totalRounds }}</span>
                    </p>
                    <p id="scr-mission" class="mt-2 max-w-xs truncate text-sm text-cyan-accent">
                        {{ $currentRound > 0 ? ($session->round_status === 'running' ? 'Misi berjalan' : 'Misi selesai') : 'Menunggu dimulai' }}
                    </p>
                </div>
            </div>

            {{-- Timer raksasa --}}
            <div class="mt-8 flex flex-wrap items-center justify-center gap-6">
                <div id="scr-timer-box"
                     class="rounded-2xl border border-white/10 bg-navy-950/60 px-10 py-5 text-center">
                    <p class="text-xs uppercase tracking-[0.3em] text-white/40">Sisa Waktu</p>
                    <p id="scr-timer" class="font-mono text-6xl font-black tabular-nums sm:text-8xl">--:--</p>
                </div>
                <div id="scr-round-badge" class="badge px-4 py-2 text-base bg-white/5 text-white/60">MENUNGGU</div>

                {{-- Muncul bila guru membuka beberapa ronde sekaligus. --}}
                <p id="scr-timer-note" class="hidden max-w-[15rem] text-center text-xs font-semibold text-gold">
                    Timer kelas nonaktif karena beberapa ronde terbuka sekaligus.
                    Tiap kelompok bekerja sesuai waktunya sendiri.
                </p>
            </div>

            {{-- Misi ronde berjalan --}}
            <div id="scr-mission-card" class="mt-6 hidden rounded-2xl border border-cyan-accent/20 bg-cyan-strong/5 p-4 text-center">
                <p class="text-xs uppercase tracking-widest text-white/40">Misi Ronde Ini</p>
                <p id="scr-mission-title" class="mt-1 text-xl font-bold text-white"></p>
                <p id="scr-mission-objective" class="mt-1 text-sm text-white/50"></p>
            </div>

            {{-- Daftar ronde yang sedang terbuka (bila lebih dari satu). --}}
            <div id="scr-open-rounds" class="mt-6 hidden rounded-2xl border border-white/10 bg-navy-950/50 p-4">
                <p class="text-center text-xs uppercase tracking-[0.3em] text-white/40">
                    Ronde yang sedang terbuka
                </p>
                <div id="scr-open-rounds-list" class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3"></div>
            </div>

            {{-- ===================== PAPAN SKOR ===================== --}}
            <h2 class="mt-10 text-center text-lg font-black uppercase tracking-[0.3em] text-gold">
                🏆 Papan Skor
            </h2>

            <div id="scr-board" class="mt-5 space-y-3">
                @forelse ($session->teams()->orderByDesc('xp')->get() as $team)
                    <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-navy-900/60 px-5 py-4">
                        <span class="text-lg font-bold text-white/60">{{ $team->name }}</span>
                        <span class="ml-auto font-mono text-2xl font-black text-gold">{{ $team->xp }} XP</span>
                    </div>
                @empty
                    <p class="py-10 text-center text-lg text-white/40">
                        Belum ada kelompok yang bergabung. Bagikan kode
                        <span class="font-mono font-bold text-cyan-accent">{{ $session->code }}</span>.
                    </p>
                @endforelse
            </div>

            {{-- Ringkasan bawah --}}
            <div class="mt-8 grid grid-cols-3 gap-4 text-center">
                <div class="rounded-xl border border-white/10 bg-navy-950/50 px-3 py-3">
                    <p class="text-[11px] uppercase tracking-wider text-white/40">Kelompok</p>
                    <p id="scr-team-count" class="text-2xl font-black">{{ $session->teams()->count() }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-navy-950/50 px-3 py-3">
                    <p class="text-[11px] uppercase tracking-wider text-white/40">Total XP Kelas</p>
                    <p id="scr-total-xp" class="text-2xl font-black text-gold">{{ $session->teams()->sum('xp') }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-navy-950/50 px-3 py-3">
                    <p class="text-[11px] uppercase tracking-wider text-white/40">Dinilai AI</p>
                    <p id="scr-ai-count" class="text-2xl font-black text-cyan-accent">0</p>
                </div>
            </div>
        </div>

        <p class="mt-3 text-center text-xs text-white/30">
            Layar ini memperbarui sendiri setiap 4 detik. Tekan <kbd class="rounded bg-white/10 px-1">F11</kbd> untuk layar penuh.
        </p>
    </div>

@endsection
