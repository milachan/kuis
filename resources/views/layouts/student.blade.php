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
<body class="min-h-screen bg-sky-50 font-sans text-ink-900 antialiased">

    {{-- Toast container --}}
    <div id="tik-toast-container"
         class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"></div>

    @include('components.flash')

    {{-- ============ BANNER RONDE BARU (diisi oleh round-watch.js) ============ --}}
    <div id="round-banner" class="hidden mx-auto max-w-6xl px-4 pt-4">
        <div class="flex flex-wrap items-center gap-3 rounded-2xl border-2 border-sky-300 bg-white p-4 shadow-sm">
            <span class="text-2xl">🚀</span>
            <div class="min-w-0 flex-1">
                <p id="round-banner-title" class="text-sm font-black text-sky-600"></p>
                <p id="round-banner-text" class="mt-0.5 text-xs text-ink-700"></p>
            </div>
            <a id="round-banner-button" href="#" class="btn-primary hidden px-4 py-2 text-sm">Kerjakan Sekarang</a>
            <button type="button"
                    data-round-banner-close
                    class="rounded-xl border-2 border-sky-200 px-3 py-2 text-xs font-bold text-ink-600 hover:bg-sky-50">
                Tutup
            </button>
        </div>
    </div>

    {{-- ============================ HEADER ============================ --}}
    <header class="sticky top-0 z-40 border-b-2 border-sky-100 bg-white/95 backdrop-blur">
        <div class="mx-auto max-w-6xl px-4 py-3">

            <div class="flex items-center justify-between gap-3">
                {{-- Brand --}}
                <a href="{{ route('student.dashboard') }}" class="flex items-center gap-2.5">
                    <span class="grid h-10 w-10 place-items-center rounded-2xl bg-sky-500 text-xl font-black text-white shadow-sm">
                        🎓
                    </span>
                    <span class="leading-tight">
                        <span class="block text-sm font-black tracking-wide text-ink-900">TIK MISSION</span>
                        <span class="block text-[11px] font-semibold text-sky-600">Bab 4 · Sistem Komputer</span>
                    </span>
                </a>

                {{-- Aksi kanan --}}
                <div class="flex items-center gap-2">
                    <button type="button"
                            data-sound-toggle
                            class="rounded-xl border-2 border-sky-200 bg-white px-3 py-1.5 text-xs font-bold text-ink-700 transition hover:bg-sky-50">
                        🔇 Suara Mati
                    </button>

                    <form method="POST" action="{{ route('student.leave') }}" data-confirm="Keluar dari sesi kelompok?">
                        @csrf
                        <button type="submit"
                                class="rounded-xl border-2 border-coral-400/40 bg-white px-3 py-1.5 text-xs font-bold text-coral-500 transition hover:bg-coral-400/10">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>

            {{-- Bar statistik kelompok --}}
            @isset($team)
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {{-- Kelompok --}}
                    <div class="rounded-2xl border-2 border-sky-100 bg-sky-50 px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-ink-500">Kelompok</p>
                        <p class="truncate text-sm font-black text-sky-600">{{ $team->name }}</p>
                    </div>

                    {{-- XP --}}
                    <div class="rounded-2xl border-2 border-sun-400/30 bg-sun-300/20 px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-ink-500">XP</p>
                        <p class="text-sm font-black text-ink-800">{{ $team->xp }}</p>
                    </div>

                    {{-- Progres --}}
                    <div class="rounded-2xl border-2 border-mint-400/30 bg-mint-400/10 px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-ink-500">Progres</p>
                        <p class="text-sm font-black text-ink-800">{{ $completed ?? 0 }}/{{ $total ?? 0 }}</p>
                    </div>

                    {{-- Timer --}}
                    <div class="rounded-2xl border-2 border-grape-400/30 bg-grape-400/10 px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-ink-500">Sisa Waktu</p>
                        @if ($session->hasTimer())
                            <p class="text-sm font-black text-ink-800" data-timer="{{ $session->secondsRemaining() }}">
                                {{ $session->formattedRemaining() }}
                            </p>
                        @else
                            <p class="text-sm font-black text-ink-500">Tanpa batas</p>
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

    <footer class="mx-auto max-w-6xl px-4 pb-8 pt-2 text-center text-xs font-semibold text-ink-500">
        TIK Mission · Informatika Kelas 8 MTs · Bab 4 Sistem Komputer
    </footer>

</body>
</html>
