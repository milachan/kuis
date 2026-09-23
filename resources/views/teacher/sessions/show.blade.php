@extends('layouts.teacher')

@section('title', $session->name)
@section('page-title', $session->name)
@section('page-subtitle', 'Kode sesi: '.$session->code)

@section('page-actions')
    <a href="{{ route('teacher.sessions.screen', $session) }}" class="btn-primary">🖥 Buka Layar Proyektor</a>

    @if ($session->isEnded())
        <form method="POST" action="{{ route('teacher.sessions.reopen', $session) }}">
            @csrf
            <button type="submit" class="btn-secondary">Aktifkan Kembali Sesi</button>
        </form>
    @else
        <form method="POST" action="{{ route('teacher.sessions.end', $session) }}"
              data-confirm="Akhiri sesi ini? Siswa tidak dapat mengirim jawaban baru.">
            @csrf
            <button type="submit" class="btn-secondary">Akhiri Sesi</button>
        </form>
    @endif
@endsection

@section('content')

    @php
        $totalRounds = $missionList->count();
        $currentRound = $session->current_round;
        $nextRound = $currentRound + 1;
        $semuaSelesai = $currentRound >= $totalRounds;
    @endphp

    {{--
        Halaman ini disusun sebagai ALUR BERNOMOR supaya guru tahu urutan
        langkah saat mengajar: bagikan kode -> siapkan kelompok -> jalankan
        ronde -> pantau hasil. Setiap langkah punya satu panel sendiri.
    --}}

    {{-- ===================== RINGKASAN SINGKAT ===================== --}}
    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="panel p-4">
            <p class="text-xs uppercase tracking-wider text-white/50">Kode Sesi (bagikan ke siswa)</p>
            <p class="mt-1 font-mono text-2xl font-bold text-cyan-accent">{{ $session->code }}</p>
        </div>
        <div class="panel p-4">
            <p class="text-xs uppercase tracking-wider text-white/50">Sekarang di Ronde</p>
            <p class="mt-1 text-2xl font-bold">
                {{ $currentRound > 0 ? $currentRound : '—' }}
                <span class="text-base font-normal text-white/40">/ {{ $totalRounds }}</span>
            </p>
        </div>
        <div class="panel p-4">
            <p class="text-xs uppercase tracking-wider text-white/50">Kelompok Bergabung</p>
            <p class="mt-1 text-2xl font-bold">{{ $teams->count() }}</p>
        </div>
        <div class="panel p-4">
            <p class="text-xs uppercase tracking-wider text-white/50">Status Sesi</p>
            <p class="mt-1">
                @if ($session->isEnded())
                    <span class="badge bg-white/5 text-white/40">BERAKHIR</span>
                @elseif ($session->isTimeUp())
                    <span class="badge bg-rose-500/15 text-rose-300">WAKTU HABIS</span>
                @else
                    <span class="badge bg-emerald-500/15 text-emerald-300">SESI AKTIF</span>
                @endif
            </p>
        </div>
    </div>

    {{-- ===================== LANGKAH 1: SESI DIMULAI ===================== --}}
    <div class="panel mb-4 p-5">
        <div class="flex flex-wrap items-start gap-4">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-cyan-strong/20 text-base font-black text-cyan-accent">1</span>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold uppercase tracking-wider text-white">Bagikan Kode Sesi</h2>
                <p class="mt-1 text-xs text-white/60">
                    Tampilkan kode ini di proyektor. Siswa membuka
                    <span class="font-mono text-cyan-accent">/student/join</span> lalu mengetik kode
                    dan nama kelompok mereka.
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <span class="rounded-xl bg-white/5 px-5 py-2.5 font-mono text-3xl font-black tracking-widest text-cyan-accent">
                        {{ $session->code }}
                    </span>
                    <button type="button" data-copy="{{ $session->code }}" class="btn-secondary text-xs">
                        Salin Kode
                    </button>
                    <span class="badge {{ $session->lobby_locked ? 'bg-amber-500/15 text-amber-200' : 'bg-emerald-500/15 text-emerald-200' }}">
                        {{ $session->lobby_locked ? '🔒 LOBI TERKUNCI' : '🔓 LOBI TERBUKA' }}
                    </span>

                    {{--
                        Kontrol lobi sengaja ditempatkan di sini (bukan lagi di dalam
                        menu "Pengaturan lanjutan") karena inilah tombol yang dipakai
                        guru ketika ada siswa datang terlambat. Sebelumnya tombol ini
                        tersembunyi, sehingga guru hanya melihat status "terkunci"
                        tanpa cara membukanya.
                    --}}
                    @unless ($session->isEnded())
                        <form method="POST" action="{{ route('teacher.sessions.lobby', $session) }}">
                            @csrf
                            <button type="submit"
                                    class="{{ $session->lobby_locked ? 'btn-primary' : 'btn-secondary' }} text-xs">
                                {{ $session->lobby_locked
                                    ? '🔓 Buka Lobi (izinkan siswa terlambat masuk)'
                                    : '🔒 Kunci Lobi' }}
                            </button>
                        </form>
                    @endunless
                </div>

                @if ($session->lobby_locked && ! $session->isEnded())
                    <p class="mt-2 rounded-xl border border-amber-500/30 bg-amber-500/10 p-2.5 text-[11px] leading-relaxed text-amber-200/90">
                        Lobi terkunci otomatis setiap kali ronde dibuka. Selama terkunci, siswa yang
                        datang terlambat <strong>tidak bisa bergabung</strong>. Tekan
                        <strong>Buka Lobi</strong> di atas agar mereka bisa masuk dan langsung ikut
                        ronde yang sedang berjalan.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== LANGKAH 2: KONTROL RONDE ===================== --}}
    <div class="panel mb-4 border-cyan-accent/30 p-5">
        <div class="flex flex-wrap items-start gap-4">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-cyan-strong/20 text-base font-black text-cyan-accent">2</span>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold uppercase tracking-wider text-white">Jalankan Ronde</h2>

                @php
                    $berikutnya = app(App\Services\RoundService::class)->nextRoundNumber($session);
                    $misiBerikut = $berikutnya ? $missionList->firstWhere('order', $berikutnya) : null;
                @endphp

                {{--
                    Satu tombol besar untuk berpindah ronde. Guru tidak perlu
                    menekan "akhiri ronde" lebih dulu: ronde lama ditutup
                    otomatis saat tombol ini ditekan.
                --}}

                @if ($session->isEnded())
                    {{-- Sesi berakhir --}}
                    <div class="mt-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
                        <p class="text-sm font-bold text-amber-200">Sesi sudah berakhir</p>
                        <p class="mt-1 text-xs text-white/60">
                            Aktifkan kembali sesi bila ingin melanjutkan permainan.
                        </p>
                    </div>
                @elseif ($berikutnya === null)
                    {{-- Semua ronde selesai --}}
                    <div class="mt-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                        <p class="text-sm font-bold text-emerald-200">🎉 Semua ronde sudah dijalankan</p>
                        <p class="mt-1 text-xs text-white/60">
                            Total {{ $totalRounds }} ronde selesai. Buka Layar Proyektor untuk melihat
                            papan skor akhir, lalu periksa jawaban siswa.
                        </p>
                    </div>
                @else
                    {{-- Tombol utama: buka ronde berikutnya --}}
                    <div class="mt-3 rounded-2xl border-2 border-cyan-accent/40 bg-cyan-strong/5 p-4">
                        <p class="text-xs text-white/60">
                            @if ($currentRound > 0)
                                Ronde terdepan sekarang {{ $currentRound }}. Tombol ini membuka
                                <strong class="text-white/80">satu</strong> ronde berikutnya dan menutup
                                ronde lain yang sedang terbuka. Untuk membuka beberapa ronde sekaligus,
                                pakai pilihan ronde di bawah.
                            @else
                                Tekan tombol ini untuk memulai ronde pertama.
                            @endif
                        </p>

                        @if ($misiBerikut)
                            <p class="mt-2 text-sm font-bold text-white">
                                Berikutnya — Ronde {{ $berikutnya }}: {{ $misiBerikut->title }}
                            </p>
                            @if ($misiBerikut->hasGame())
                                <span class="badge mt-1 bg-grape-400/20 text-grape-400">
                                    {{ $misiBerikut->gameInfo()['icon'] }} GAME {{ $misiBerikut->gameInfo()['label'] }}
                                </span>
                            @endif
                        @endif

                        <form method="POST"
                              action="{{ route('teacher.sessions.round.next', $session) }}"
                              class="mt-4">
                            @csrf
                            <div class="flex flex-wrap items-end gap-3">
                                <div>
                                    <label for="durasi" class="block text-[11px] text-white/50">
                                        Durasi ronde (menit, 0 = tanpa batas)
                                    </label>
                                    <input id="durasi"
                                           type="number"
                                           name="duration_minutes"
                                           min="0"
                                           max="120"
                                           value="{{ $session->round_duration_minutes }}"
                                           class="mt-0.5 w-24 rounded-lg border border-white/15 bg-navy-900 px-2 py-2 text-sm text-white">
                                </div>

                                <button type="submit" class="btn-primary px-6 py-3.5 text-base font-bold">
                                    ▶ Buka Ronde {{ $berikutnya }}
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- ---------------------------------------------------------------
                    Daftar ronde: guru boleh mencentang BEBERAPA ronde sekaligus
                    supaya semuanya terbuka bersamaan. Ronde yang tidak dicentang
                    ditutup otomatis, jadi cara ini juga bisa dipakai untuk MUNDUR
                    ke ronde sebelumnya. XP tetap aman.
                ---------------------------------------------------------------- --}}
                @if (! $session->isEnded())
                    @php
                        $rondeTerbuka = $session->openRoundNumbers();
                    @endphp

                    <div class="mt-4 rounded-2xl border border-white/10 bg-navy-900/40 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-white/70">
                                    Pilih Ronde (boleh lebih dari satu)
                                </h3>
                                <p class="mt-0.5 text-[11px] text-white/45">
                                    Centang ronde mana saja yang harus terbuka, lalu tekan
                                    <strong class="text-white/70">Buka Ronde Terpilih</strong>.
                                    Ronde yang tidak dicentang ditutup otomatis — cara ini juga bisa
                                    dipakai untuk <strong class="text-white/70">kembali ke ronde sebelumnya</strong>.
                                    XP yang sudah terkumpul tidak terhapus.
                                </p>
                            </div>

                            <span class="badge {{ count($rondeTerbuka) > 0 ? 'bg-cyan-accent/20 text-cyan-accent' : 'bg-white/5 text-white/50' }}">
                                @if (count($rondeTerbuka) > 0)
                                    TERBUKA: {{ implode(', ', $rondeTerbuka) }}
                                @else
                                    BELUM ADA RONDE TERBUKA
                                @endif
                            </span>
                        </div>

                        @if (count($rondeTerbuka) > 1)
                            <p class="mt-2 rounded-xl border border-gold/30 bg-gold/10 p-2.5 text-[11px] leading-relaxed text-gold">
                                {{ count($rondeTerbuka) }} ronde terbuka bersamaan: kelompok bebas memilih
                                mau mengerjakan yang mana, dan <strong>hitung mundur ronde dimatikan</strong>
                                (batas waktu per kelompok tetap berlaku).
                            </p>
                        @endif

                        <form method="POST"
                              action="{{ route('teacher.sessions.rounds.open', $session) }}"
                              class="mt-3">
                            @csrf
                            <input type="hidden" name="duration_minutes"
                                   value="{{ $session->round_duration_minutes }}">

                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach ($missionList as $m)
                                    @php
                                        $rondeIni = $m->order;
                                        $terbuka = in_array($rondeIni, $rondeTerbuka, true);
                                        $sudahLewat = $rondeIni < $currentRound;
                                    @endphp

                                    <label class="flex cursor-pointer flex-col rounded-xl border p-2.5 transition
                                                  {{ $terbuka
                                                        ? 'border-cyan-accent/50 bg-cyan-strong/10'
                                                        : 'border-white/15 bg-navy-900/60 hover:border-cyan-accent/40' }}">
                                        <span class="flex items-center justify-between gap-1">
                                            <span class="flex items-center gap-1.5">
                                                <input type="checkbox"
                                                       name="rounds[]"
                                                       value="{{ $rondeIni }}"
                                                       @checked($terbuka)
                                                       class="h-3.5 w-3.5 accent-cyan-400">
                                                <span class="text-[11px] font-black {{ $terbuka ? 'text-cyan-accent' : 'text-white/60' }}">
                                                    RONDE {{ $rondeIni }}
                                                </span>
                                            </span>

                                            @if ($terbuka)
                                                <span class="badge bg-cyan-accent/20 text-cyan-accent text-[10px]">
                                                    SEDANG DIBUKA
                                                </span>
                                            @elseif ($sudahLewat)
                                                <span class="text-[10px] font-bold text-white/35">BUKA ULANG</span>
                                            @else
                                                <span class="text-[10px] font-bold text-emerald-300/80">BUKA</span>
                                            @endif
                                        </span>

                                        <span class="mt-1 line-clamp-2 text-[11px] leading-snug {{ $terbuka ? 'text-white/75' : 'text-white/60' }}">
                                            {{ $m->title }}
                                        </span>

                                        @if ($m->hasGame())
                                            <span class="mt-1 inline-block text-[10px] text-grape-400">
                                                {{ $m->gameInfo()['icon'] }} {{ $m->gameInfo()['label'] }}
                                            </span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <button type="submit" class="btn-primary text-xs">
                                    ▶ Buka Ronde Terpilih
                                </button>
                                <span class="text-[11px] text-white/45">
                                    Boleh centang beberapa ronde sekaligus.
                                </span>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- Status ronde saat ini --}}
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if ($currentRound > 0)
                        <span class="badge bg-cyan-strong/15 text-cyan-accent">
                            RONDE {{ $currentRound }} DARI {{ $totalRounds }}
                        </span>
                        <span class="badge {{ $session->round_status === 'running' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-white/5 text-white/50' }}">
                            {{ $session->round_status === 'running' ? '⏱ BERJALAN' : strtoupper($session->round_status) }}
                        </span>
                        @if ($session->round_duration_minutes > 0 && $session->round_status === 'running')
                            <span class="text-xs text-white/60">
                                Sisa: <span class="font-mono font-bold text-white">{{ $session->formattedRoundRemaining() ?? '—' }}</span>
                            </span>
                        @endif
                    @else
                        <span class="badge bg-white/5 text-white/50">BELUM ADA RONDE BERJALAN</span>
                    @endif

                    <span class="badge {{ $session->lobby_locked ? 'bg-amber-500/15 text-amber-200' : 'bg-emerald-500/15 text-emerald-200' }}">
                        {{ $session->lobby_locked ? '🔒 SISWA BARU TIDAK BISA MASUK' : '🔓 SISWA BARU BISA MASUK' }}
                    </span>
                </div>

                {{-- ---------------------------------------------------------------
                    Jam kelas. Dulu waktu ini dihitung sejak sesi DIBUAT, sehingga
                    sering habis saat kelas masih mengerjakan — dan semua kiriman
                    langsung ditolak tanpa tombol apapun untuk memperpanjangnya.
                    Sekarang jam mulai saat ronde pertama dibuka, dan guru punya
                    tombol tambah waktu / tanpa batas.
                ---------------------------------------------------------------- --}}
                <div class="mt-4 rounded-2xl border p-4
                            {{ $session->isTimeUp() ? 'border-rose-400/40 bg-rose-500/10' : 'border-white/10 bg-navy-900/40' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-white/70">
                                ⏱ Jam Kelas
                            </h3>
                            <p class="mt-1 text-2xl font-black {{ $session->isTimeUp() ? 'text-rose-300' : 'text-white' }}">
                                @if (! $session->hasTimer())
                                    Tanpa batas waktu
                                @elseif (! $session->timeLimitActive())
                                    Belum dimulai
                                @else
                                    {{ $session->formattedRemaining() }}
                                @endif
                            </p>
                            <p class="mt-1 max-w-xl text-[11px] leading-relaxed text-white/50">
                                Jam kelas mulai berjalan saat ronde pertama dibuka, bukan saat sesi dibuat.
                                Kelompok yang baru mulai mengerjakan ronde setelah jam ini habis tetap bisa
                                bekerja — mereka memakai jatah waktu ronde sendiri.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if ($session->hasTimer())
                                <form method="POST" action="{{ route('teacher.sessions.time.extend', $session) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary text-xs">
                                        ⏱ Tambah 15 Menit
                                    </button>
                                </form>

                                <form method="POST"
                                      action="{{ route('teacher.sessions.time.unlimited', $session) }}"
                                      data-confirm="Matikan batas waktu sesi? Semua kelompok bisa mengirim jawaban sampai sesi diakhiri.">
                                    @csrf
                                    <button type="submit" class="btn-secondary text-xs">
                                        ♾️ Tanpa Batas Waktu
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('teacher.sessions.edit', $session) }}" class="btn-secondary text-xs">
                                    ⏱ Pakai Batas Waktu
                                </a>
                            @endif
                        </div>
                    </div>

                    @if ($session->isTimeUp())
                        <p class="mt-3 rounded-xl border border-rose-400/30 bg-rose-500/10 p-2.5 text-[11px] leading-relaxed text-rose-200">
                            Jam kelas sudah habis. Kelompok yang mengerjakan rondenya SEBELUM jam ini
                            habis tidak bisa mengirim lagi — tekan
                            <strong>Tambah 15 Menit</strong> atau <strong>Tanpa Batas Waktu</strong>
                            supaya mereka bisa melanjutkan.
                        </p>
                    @endif
                </div>

                {{-- ---------------------------------------------------------------
                    Kendali lanjutan: DISEMBUNYIKAN supaya guru tidak salah klik
                    saat mengajar. Dibuka hanya bila benar-benar diperlukan.
                ---------------------------------------------------------------- --}}
                <details class="mt-4">
                    <summary class="cursor-pointer text-xs font-semibold text-white/40 hover:text-white/70">
                        ⚙ Pengaturan lanjutan (jarang dipakai)
                    </summary>

                    <div class="mt-3 space-y-3 rounded-xl border border-white/10 bg-navy-900/40 p-3">
                        <div class="flex flex-wrap gap-2">
                            @if ($session->round_status === 'running')
                                <form method="POST" action="{{ route('teacher.sessions.round.end', $session) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary text-xs">
                                        ⏹ Hentikan Ronde (tanpa lanjut)
                                    </button>
                                </form>
                            @endif

                            <form method="POST"
                                  action="{{ route('teacher.sessions.rounds.close', $session) }}"
                                  data-confirm="Tutup semua ronde yang terbuka? Siswa tidak bisa mengirim jawaban sampai ronde dibuka lagi. Ketikan siswa tetap tersimpan di browser mereka.">
                                @csrf
                                <button type="submit" class="btn-secondary text-xs">
                                    ⏹ Tutup Semua Ronde
                                </button>
                            </form>

                            <form method="POST"
                                  action="{{ route('teacher.sessions.round.reset', $session) }}"
                                  data-confirm="Ulangi permainan dari ronde nol? Ronde yang berjalan akan dibatalkan.">
                                @csrf
                                <button type="submit" class="btn-secondary text-xs">
                                    ↺ Ulangi dari Awal
                                </button>
                            </form>
                        </div>

                        <p class="text-[11px] leading-relaxed text-white/40">
                            "Hentikan Ronde" dipakai bila ingin menghentikan ronde tanpa membuka ronde
                            berikutnya (mis. waktu habis) — misi tetap bisa dikerjakan siswa.
                            "Tutup Semua Ronde" sekaligus menutup misi yang sedang terbuka, sehingga
                            siswa berhenti mengirim jawaban. Untuk lanjut bermain, gunakan tombol
                            <strong class="text-white/60">Buka Ronde</strong> di atas.
                        </p>
                    </div>
                </details>
            </div>
        </div>
    </div>

    {{-- ===================== LANGKAH 3: PANTAU KELOMPOK ===================== --}}
    <div class="panel mb-4 p-5">
        <div class="flex flex-wrap items-start gap-4">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-cyan-strong/20 text-base font-black text-cyan-accent">3</span>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold uppercase tracking-wider text-white">Pantau Kelompok</h2>
                <p class="mt-1 text-xs text-white/60">
                    Lihat siapa yang sudah bergabung dan sejauh mana mereka mengerjakan.
                    Papan skor lengkap ada di Layar Proyektor.
                </p>

                @if ($teams->isEmpty())
                    <div class="mt-3 rounded-xl border border-white/10 bg-navy-900/40 p-6 text-center">
                        <p class="text-3xl">👥</p>
                        <p class="mt-2 text-sm font-semibold text-white/80">Belum ada kelompok bergabung</p>
                        <p class="mt-1 text-xs text-white/50">
                            Minta siswa membuka halaman masuk dan mengetik kode
                            <span class="font-mono font-bold text-cyan-accent">{{ $session->code }}</span>.
                        </p>
                    </div>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-white/10 text-[11px] uppercase tracking-wider text-white/50">
                                    <th class="py-2 pr-3">Kelompok</th>
                                    <th class="py-2 pr-3">Anggota</th>
                                    <th class="py-2 pr-3 text-right">XP</th>
                                    <th class="py-2 pr-3 text-right">Selesai</th>
                                    <th class="py-2 pr-3">Progres Ronde</th>
                                    <th class="py-2 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($teams as $row)
                                    @php $team = $row['team']; @endphp
                                    <tr class="border-b border-white/5">
                                        <td class="py-2.5 pr-3">
                                            <a href="{{ route('teacher.teams.show', $team) }}"
                                               class="font-semibold text-white hover:text-cyan-accent">
                                                {{ $team->name }}
                                            </a>
                                        </td>
                                        <td class="py-2.5 pr-3 text-white/60">{{ $team->members_count }}</td>
                                        <td class="py-2.5 pr-3 text-right font-mono font-bold text-gold">{{ $team->xp }}</td>
                                        <td class="py-2.5 pr-3 text-right text-white/70">
                                            {{ $row['completed'] }}/{{ $row['total'] }}
                                        </td>
                                        <td class="py-2.5 pr-3">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($missionList as $mission)
                                                    @php $p = $row['progress'][$mission->id] ?? null; @endphp
                                                    <span class="grid h-6 w-6 place-items-center rounded text-[10px] font-bold
                                                        {{ $p?->status === 'completed'
                                                            ? 'bg-emerald-500/20 text-emerald-300'
                                                            : ($p?->isLocked() || ! $p
                                                                ? 'bg-white/5 text-white/30'
                                                                : 'bg-cyan-strong/15 text-cyan-accent') }}"
                                                        title="Ronde {{ $mission->order }}: {{ $p?->status ?? 'belum' }}">
                                                        {{ $mission->order }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="py-2.5 text-right">
                                            <a href="{{ route('teacher.teams.show', $team) }}"
                                               class="text-xs font-semibold text-cyan-accent hover:underline">Detail</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-2 text-[11px] text-white/40">
                        Kotak bernomor = ronde. Hijau = selesai, biru = sedang dikerjakan, abu = terkunci.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== ZONA BERBAHAYA ===================== --}}
    <details class="panel border-rose-400/20 p-5">
        <summary class="cursor-pointer text-sm font-bold uppercase tracking-wider text-rose-300">
            ⚠ Zona Berbahaya
        </summary>
        <p class="mt-2 text-xs text-white/50">
            Menghapus sesi akan menghapus seluruh kelompok, jawaban, dan progres di dalamnya.
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
    </details>

    {{-- Skrip: salin kode ke papan klip --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-copy]').forEach(function (btn) {
                btn.addEventListener('click', async function () {
                    try {
                        await navigator.clipboard.writeText(btn.dataset.copy);
                        window.TikToast('Kode sesi disalin: ' + btn.dataset.copy, 'success');
                    } catch (e) {
                        window.TikToast('Gagal menyalin. Salin manual: ' + btn.dataset.copy, 'warning');
                    }
                });
            });
        });
    </script>

@endsection
