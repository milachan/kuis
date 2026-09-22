<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard Guru') — TIK Mission</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-navy-950 font-sans text-white antialiased">

    {{-- Toast container --}}
    <div id="tik-toast-container"
         class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"></div>

    {{-- Flash message untuk JS --}}
    <script type="application/json" id="tik-flash">@json($toasts ?? [])</script>

    {{-- ============================ HEADER ============================ --}}
    <header class="sticky top-0 z-40 border-b border-white/10 bg-navy-900/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">

            {{-- Brand --}}
            <a href="{{ route('teacher.dashboard') }}" class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-gradient-to-br from-cyan-accent to-teal-accent text-lg font-black text-navy-950">
                    T
                </span>
                <span class="hidden leading-tight sm:block">
                    <span class="block text-sm font-bold tracking-wide">TIK MISSION</span>
                    <span class="block text-[11px] text-cyan-accent/80">Panel Guru</span>
                </span>
            </a>

            {{-- Navigasi --}}
            <nav class="flex items-center gap-1 overflow-x-auto text-sm">
                @php
                    $navItems = [
                        ['route' => 'teacher.dashboard', 'label' => 'Dashboard'],
                        ['route' => 'teacher.sessions.index', 'label' => 'Sesi'],
                        ['route' => 'teacher.teams', 'label' => 'Kelompok'],
                        ['route' => 'teacher.validations', 'label' => 'Validasi'],
                        ['route' => 'teacher.report', 'label' => 'Laporan'],
                        ['route' => 'teacher.missions', 'label' => 'Misi'],
                    ];
                @endphp

                @foreach ($navItems as $item)
                    @php
                        $isActive = request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '.*', $item['route']));
                    @endphp
                    <a href="{{ route($item['route']) }}"
                       class="whitespace-nowrap rounded-lg px-3 py-2 font-medium transition
                              {{ $isActive
                                    ? 'bg-cyan-strong/20 text-cyan-accent'
                                    : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        {{ $item['label'] }}
                        @if ($item['label'] === 'Validasi' && ($pendingValidationCount ?? 0) > 0)
                            <span class="ml-1 rounded-full bg-gold px-1.5 py-0.5 text-[10px] font-bold text-navy-950">
                                {{ $pendingValidationCount }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </nav>

            {{-- Aksi kanan --}}
            <div class="flex items-center gap-2">
                <button type="button"
                        data-sound-toggle
                        class="hidden rounded-lg border border-white/15 px-2.5 py-1.5 text-xs font-medium text-white/70 transition hover:border-cyan-accent/50 hover:text-white lg:block"
                        title="Nyalakan atau matikan suara">
                    🔇 Sound OFF
                </button>

                <div class="flex items-center gap-2">
                    <span class="hidden text-sm text-white/70 md:block">{{ auth()->user()->name }}</span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white/80 transition hover:border-rose-400/50 hover:text-rose-200">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- ============================ KONTEN ============================ --}}
    <main class="mx-auto max-w-7xl px-4 py-6">
        {{-- Judul halaman --}}
        @hasSection('page-title')
            <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">@yield('page-title')</h1>
                    @hasSection('page-subtitle')
                        <p class="mt-1 text-sm text-white/60">@yield('page-subtitle')</p>
                    @endif
                </div>
                @hasSection('page-actions')
                    <div class="flex flex-wrap items-center gap-2">@yield('page-actions')</div>
                @endif
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto max-w-7xl px-4 pb-8 pt-4 text-center text-xs text-white/40">
        TIK Mission — Operasi File Rahasia · Informatika Kelas 8 SMP/MTs
    </footer>

    @stack('scripts')

</body>
</html>
