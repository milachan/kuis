@extends('layouts.student')

@section('title', 'Game — '.$mission->title)

@push('scripts')
    @vite('resources/js/game.js')
@endpush

@section('content')

    @php
        $info = $mission->gameInfo();
    @endphp

    <nav class="mb-4 text-sm font-semibold text-ink-600">
        <a href="{{ route('student.dashboard') }}" class="transition hover:text-sky-600">Daftar Misi</a>
        <span class="mx-1.5 text-ink-500">/</span>
        <a href="{{ route('student.mission.show', $mission) }}" class="transition hover:text-sky-600">
            Misi {{ $mission->numberLabel() }}
        </a>
        <span class="mx-1.5 text-ink-500">/</span>
        <span class="text-ink-900">Game</span>
    </nav>

    {{-- Judul game --}}
    <div class="card-bright mb-5">
        <div class="flex flex-wrap items-center gap-4">
            <div class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-sky-100 text-3xl">
                {{ $info['icon'] ?? '🎮' }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-black uppercase tracking-widest text-sky-600">
                    Ronde {{ $mission->order }} · Game Belajar
                </p>
                <h1 class="mt-1 text-xl font-black text-ink-900 sm:text-2xl">
                    {{ $info['label'] ?? 'Game Arcade' }}
                </h1>
                <p class="mt-1 text-sm font-semibold text-ink-700">
                    {{ $info['how'] ?? 'Jawab soal untuk memenangkan permainan.' }}
                </p>
            </div>
            <div class="shrink-0 rounded-2xl border-2 border-sun-400/40 bg-sun-300/20 px-4 py-2 text-center">
                <p class="text-[10px] font-bold uppercase tracking-wider text-ink-600">XP Misi</p>
                <p class="text-xl font-black text-ink-800">{{ $mission->xp }}</p>
            </div>
        </div>
    </div>

    {{-- Akar game (data dibaca oleh resources/js/game.js) --}}
    <div id="game-root"
         data-game="{{ $mission->game_type }}"
         data-answer-url="{{ route('student.mission.game.answer', $mission) }}">

        <script type="application/json" id="game-questions">@json($questions)</script>

        <div class="grid gap-5 lg:grid-cols-3">

            {{-- ===================== AREA PERMAINAN ===================== --}}
            <div class="space-y-4 lg:col-span-2">

                {{--
                    Panggung game. Elemen ini dimasukkan ke mode layar penuh.
                    Papan permainan DAN panel soal berada DI DALAM panggung,
                    supaya soal tetap terlihat saat layar penuh (anak yang
                    menggerakkan game perlu membaca soal juga).
                --}}
                <div id="game-stage" class="card-bright tik-game-stage">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-2xl border-2 border-sky-100 bg-sky-50 px-3 py-1.5 text-xs font-black text-ink-700">
                                NYAWA <span id="stat-nyawa" class="ml-1 text-base">❤️❤️❤️</span>
                            </span>
                            <span class="rounded-2xl border-2 border-sun-400/40 bg-sun-300/20 px-3 py-1.5 text-xs font-black text-ink-800">
                                SKOR <span id="stat-skor" class="ml-1 text-base">0</span>
                            </span>
                            <span class="rounded-2xl border-2 border-mint-400/40 bg-mint-400/10 px-3 py-1.5 text-xs font-black text-mint-600">
                                BENAR <span id="stat-benar" class="ml-1 text-base">0</span>
                            </span>
                        </div>

                        {{-- Tombol layar penuh (bisa masuk atau keluar) --}}
                        <button type="button"
                                id="btn-keluar-fullscreen"
                                class="rounded-xl border-2 border-sky-200 bg-white px-3 py-1.5 text-xs font-bold text-ink-700 transition hover:bg-sky-50">
                            ⤢ Layar Penuh
                        </button>
                    </div>

                    {{-- Dua kolom: papan permainan + panel soal (berdampingan) --}}
                    <div class="tik-game-layout">
                        {{-- Kolom kiri: papan permainan --}}
                        <div class="tik-game-board">
                            <canvas id="game-canvas"
                                    class="w-full rounded-2xl border-2 border-sky-100 bg-sky-50"
                                    style="aspect-ratio: 720 / 420; touch-action: none;"></canvas>

                            {{-- Pesan permainan --}}
                            <p id="game-pesan"
                               class="mt-3 text-center text-sm font-black text-sky-600"
                               role="status"
                               aria-live="polite">
                                Tekan tombol mulai untuk bermain.
                            </p>

                            {{-- Tombol mulai --}}
                            <div class="mt-3 text-center">
                                <button type="button" id="btn-mulai" class="btn-primary px-8 py-3.5 text-base">
                                    ▶ Mulai Bermain
                                </button>
                            </div>

                            {{-- Panduan kontrol keyboard (murni keyboard, tanpa mouse) --}}
                            <div class="mt-3 rounded-2xl border-2 border-sky-100 bg-sky-50 p-3 tik-hide-fs">
                                <p class="text-[11px] font-black uppercase tracking-wider text-ink-600">
                                    🎮 Kontrol Keyboard
                                </p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] font-semibold text-ink-700">
                                    @if ($mission->game_type === 'snake')
                                        <span><kbd class="rounded border border-sky-200 bg-white px-1.5 py-0.5 font-mono">↑</kbd>
                                            <kbd class="rounded border border-sky-200 bg-white px-1.5 py-0.5 font-mono">↓</kbd>
                                            <kbd class="rounded border border-sky-200 bg-white px-1.5 py-0.5 font-mono">←</kbd>
                                            <kbd class="rounded border border-sky-200 bg-white px-1.5 py-0.5 font-mono">→</kbd>
                                            atau W A S D untuk menggerakkan ular</span>
                                    @elseif ($mission->game_type === 'flappy')
                                        <span><kbd class="rounded border border-sky-200 bg-white px-1.5 py-0.5 font-mono">↑</kbd>
                                            atau <kbd class="rounded border border-sky-200 bg-white px-1.5 py-0.5 font-mono">W</kbd>
                                            untuk terbang</span>
                                    @else
                                        <span><kbd class="rounded border border-sky-200 bg-white px-1.5 py-0.5 font-mono">←</kbd>
                                            <kbd class="rounded border border-sky-200 bg-white px-1.5 py-0.5 font-mono">→</kbd>
                                            atau A D untuk menggerakkan pemukul</span>
                                    @endif
                                </div>
                                <p class="mt-1.5 text-[10px] font-semibold text-ink-500">
                                    Tanpa mouse, supaya tidak mengganggu teman yang mengetik jawaban.
                                    Tombol game tidak aktif saat kamu sedang menulis di kolom jawaban.
                                </p>
                            </div>
                        </div>

                        {{-- Kolom kanan: soal (ikut tampil saat layar penuh) --}}
                        <div id="panel-soal" class="card-bright transition-opacity tik-game-question">
                            <h2 class="mb-2 flex items-center gap-2 text-sm font-black uppercase tracking-wider text-sky-600">
                                <span class="text-lg">❓</span> Soal
                            </h2>

                            <p id="soal-teks"
                               class="min-h-[3rem] rounded-2xl border-2 border-sky-100 bg-sky-50 p-3 text-sm font-bold leading-relaxed text-ink-900">
                                Soal akan muncul saat permainan dimulai.
                            </p>

                            <div id="soal-pilihan" class="mt-3 space-y-2"></div>
                        </div>
                    </div>
                </div>

                {{-- Cara main --}}
                <div class="rounded-3xl border-2 border-grape-400/30 bg-grape-400/10 p-4">
                    <h3 class="flex items-center gap-2 text-xs font-black text-grape-500">
                        <span class="text-base">📖</span> CARA MAIN
                    </h3>
                    <ul class="mt-2 space-y-1.5 text-[11px] font-semibold leading-relaxed text-ink-700">
                        <li>1. Tekan <strong>Mulai Bermain</strong> (layar jadi penuh).</li>
                        <li>2. Satu anak fokus menjaga nyawa (pakai keyboard), yang lain cari jawaban.</li>
                        <li>3. Pilih jawaban benar agar tenaga &amp; skor bertambah.</li>
                        <li>4. Jawaban <strong>salah</strong> mengurangi nyawa.</li>
                        <li>5. Soal selesai dijawab = layar kembali normal.</li>
                    </ul>
                </div>

                {{-- Tombol kembali --}}
                <a href="{{ route('student.mission.show', $mission) }}" class="btn-secondary">
                    Kembali ke Halaman Misi
                </a>
            </div>
        </div>
    </div>

@endsection
