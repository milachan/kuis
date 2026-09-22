<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Misi') — TIK Mission</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('scripts')
</head>
<body class="min-h-screen bg-navy-950 font-sans text-white antialiased">

    {{-- Toast container --}}
    <div id="tik-toast-container"
         class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"></div>

    @include('components.flash')

    {{-- ============ BANNER RONDE BARU (diisi oleh round-watch.js) ============ --}}
    <div id="round-banner" class="hidden mx-auto max-w-6xl px-4 pt-4">
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-cyan-accent/40 bg-cyan-strong/15 p-4">
            <span class="text-2xl">🚀</span>
            <div class="min-w-0 flex-1">
                <p id="round-banner-title" class="text-sm font-bold text-cyan-accent"></p>
                <p id="round-banner-text" class="mt-0.5 text-xs text-white/70"></p>
            </div>
            <a id="round-banner-button" href="#" class="btn-primary hidden px-4 py-2 text-sm">Kerjakan Sekarang</a>
            <button type="button"
                    data-round-banner-close
                    class="rounded-lg border border-white/20 px-3 py-2 text-xs text-white/60 hover:text-white">
                Tutup
            </button>
        </div>
    </div>

    {{-- ============================ HEADER ============================ --}}
    <header class="sticky top-0 z-40 border-b border-white/10 bg-navy-900/95 backdrop-blur">
        <div class="mx-auto max-w-6xl px-4 py-3">

            <div class="flex items-center justify-between gap-3">
                {{-- Brand --}}
                <a href="{{ route('student.dashboard') }}" class="flex items-center gap-2.5">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-gradient-to-br from-cyan-accent to-teal-accent text-lg font-black text-navy-950">
                        T
                    </span>
                    <span class="leading-tight">
                        <span class="block text-sm font-bold tracking-wide">TIK MISSION</span>
                        <span class="block text-[11px] text-cyan-accent/80">Operasi File Rahasia</span>
                    </span>
                </a>

                {{-- Aksi kanan --}}
                <div class="flex items-center gap-2">
                    <button type="button"
                            data-sound-toggle
                            class="rounded-lg border border-white/15 px-2.5 py-1.5 text-xs font-medium text-white/70 transition hover:border-cyan-accent/50 hover:text-white">
                        🔇 Sound OFF
                    </button>

                    <form method="POST" action="{{ route('student.leave') }}" data-confirm="Keluar dari sesi kelompok?">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white/80 transition hover:border-rose-400/50 hover:text-rose-200">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>

            {{-- Bar statistik kelompok --}}
            @isset($team)
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {{-- Kelompok --}}
                    <div class="rounded-lg border border-white/10 bg-navy-800/50 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wider text-white/50">Kelompok</p>
                        <p class="truncate text-sm font-bold text-cyan-accent">{{ $team->name }}</p>
                    </div>

                    {{-- XP --}}
                    <div class="rounded-lg border border-white/10 bg-navy-800/50 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wider text-white/50">XP</p>
                        <p class="text-sm font-bold text-gold">{{ $team->xp }}</p>
                    </div>

                    {{-- Progres --}}
                    <div class="rounded-lg border border-white/10 bg-navy-800/50 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wider text-white/50">Progres</p>
                        <p class="text-sm font-bold text-white">{{ $completed ?? 0 }}/{{ $total ?? 0 }}</p>
                    </div>

                    {{-- Timer --}}
                    <div class="rounded-lg border border-white/10 bg-navy-800/50 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wider text-white/50">Sisa Waktu</p>
                        @if ($session->hasTimer())
                            <p class="text-sm font-bold text-white" data-timer="{{ $session->secondsRemaining() }}">
                                {{ $session->formattedRemaining() }}
                            </p>
                        @else
                            <p class="text-sm font-bold text-white/60">Tanpa batas</p>
                        @endif
                    </div>
                </div>
            @endisset
        </div>
    </header>

    {{-- ============================ KONTEN ============================ --}}
    <main class="mx-auto max-w-6xl px-4 py-6">
        @yield('content')
    </main>

    <footer class="mx-auto max-w-6xl px-4 pb-8 pt-2 text-center text-xs text-white/40">
        TIK Mission · Informatika Kelas 8 SMP/MTs · Bab 3 Teknologi Informasi dan Komunikasi
    </footer>

</body>
</html>
