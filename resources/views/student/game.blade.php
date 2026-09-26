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
    {{--
        Data yang dibaca resources/js/game.js.
        data-round-status & data-mission-order dipakai untuk memantau apakah
        ronde masih berjalan: bila guru menghentikan ronde atau waktunya habis
        saat anak masih bermain, hasil permainan langsung dikirim supaya XP-nya
        tidak hilang.
    --}}
    <div id="game-root"
         data-game="{{ $mission->game_type }}"
         data-answer-url="{{ route('student.mission.game.answer', $mission) }}"
         data-round-status="{{ route('student.round.status', ['mission' => $mission->id]) }}"
         data-mission-id="{{ $mission->id }}"
         data-mission-order="{{ $mission->order }}">

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
                            {{--
                                Papan permainan + PAPAN HASIL.

                                Papan hasil menutupi PAPAN PERMAINAN saja (bukan
                                seluruh layar). Papan itu sudah terlihat anak
                                sejak mulai bermain, jadi hasilnya muncul tepat
                                di depan mata mereka — tanpa perlu menggulir.

                                Tombol utamanya mengarahkan ke SOAL URAIAN ronde
                                ini (dinilai AI), karena permainan saja belum
                                menyelesaikan misi.
                            --}}
                            <div class="tik-papan">
                                <canvas id="game-canvas"
                                        class="w-full rounded-2xl border-2 border-sky-100 bg-sky-50"
                                        style="aspect-ratio: 720 / 420; touch-action: none;"></canvas>

                                {{-- Kelas tik-hasil WAJIB ada: tanpa itu elemen ini
                                     tidak menjadi lapisan di atas papan, melainkan
                                     jatuh mengalir di BAWAH kanvas. --}}
                                <div id="game-hasil" class="tik-hasil" hidden>
                                    {{-- Konfeti punya lapisannya sendiri agar tidak
                                         menggeser tata letak kartu hasil. --}}
                                    <div id="hasil-konfeti" class="tik-konfeti-lapis"></div>

                                    {{-- Kartu hasil dibuat RINGKAS: tingginya harus
                                         muat di dalam papan permainan supaya tidak
                                         perlu digulir. --}}
                                    <div class="tik-hasil-kartu rounded-3xl border-2 border-mint-400/40 bg-white p-4 shadow-2xl">
                                        <p id="hasil-bintang" class="text-3xl">⭐</p>
                                        <p id="hasil-judul" class="mt-0.5 text-lg font-black text-mint-600">
                                            Ronde Selesai!
                                        </p>

                                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                            <div class="rounded-2xl border-2 border-sky-100 bg-sky-50 px-2 py-1.5">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-ink-500">Soal Benar</p>
                                                <p id="hasil-benar" class="text-base font-black text-ink-800">0</p>
                                            </div>
                                            <div class="rounded-2xl border-2 border-sky-100 bg-sky-50 px-2 py-1.5">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-ink-500">Akurasi</p>
                                                <p id="hasil-akurasi" class="text-base font-black text-ink-800">0%</p>
                                            </div>
                                            <div class="rounded-2xl border-2 border-sky-100 bg-sky-50 px-2 py-1.5">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-ink-500">Skor Main</p>
                                                <p id="hasil-skor" class="text-base font-black text-ink-800">0</p>
                                            </div>
                                            <div class="rounded-2xl border-2 border-sun-400/40 bg-sun-300/20 px-2 py-1.5">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-ink-500">XP Ronde Ini</p>
                                                <p id="hasil-xp" class="text-base font-black text-ink-800">…</p>
                                            </div>
                                        </div>

                                        <p id="hasil-xp-rincian" class="mt-2 text-[11px] font-semibold leading-snug text-ink-600">
                                            XP masuk ke total kelompok. Soal uraian menambah XP sampai maksimum misi.
                                        </p>

                                        <div class="mt-2.5 flex flex-col gap-2 sm:flex-row sm:justify-center">
                                            <a id="hasil-lanjut"
                                               href="{{ route('student.mission.show', $mission) }}"
                                               class="btn-primary justify-center">
                                                📝 Lanjut Kerjakan Soal Uraian
                                            </a>
                                            <button type="button" id="hasil-ulang" class="btn-secondary justify-center">
                                                🔁 Main Lagi
                                            </button>
                                        </div>

                                        <p id="hasil-hitung" class="mt-2 text-[11px] font-black text-grape-500"></p>

                                        <p id="hasil-sebab" class="mt-1 text-[11px] font-semibold text-ink-500"></p>

                                        {{-- Jalan keluar tanpa memulai ulang atau
                                             menunggu hitungan mundur selesai. --}}
                                        <button type="button"
                                                id="hasil-tutup"
                                                class="mt-0.5 rounded-xl px-3 py-1 text-[11px] font-bold text-ink-500 transition hover:bg-sky-50">
                                            Tutup hasil
                                        </button>
                                    </div>
                                </div>
                            </div>

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
                        <li>4. Jawaban <strong>salah</strong> atau menabrak mengurangi nyawa — tapi <strong>nyawa akan diisi ulang</strong>,
                            jadi permainan <strong>tidak berhenti</strong> sebelum semua soal selesai.</li>
                        <li>5. Soal jadi <strong>berhenti dulu</strong> saat muncul, supaya bisa dibaca tenang. Ronde selesai
                            setelah <strong>semua soal</strong> dijawab benar.</li>
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
