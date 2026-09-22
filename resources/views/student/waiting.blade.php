@extends('layouts.student')

@section('title', 'Menunggu Ronde Berikutnya')
@section('page-title', 'Menunggu Ronde Berikutnya')

@push('scripts')
    @vite('resources/js/round-watch.js')
@endpush

@section('content')

    {{--
        Halaman ini muncul setelah siswa mengirim jawaban, bila ronde berikutnya
        belum dibuka guru. Halaman memantau sendiri: begitu guru membuka ronde
        baru, siswa otomatis dipindahkan ke misi ronde tersebut.
    --}}
    <div data-round-watch="{{ route('student.round.status') }}"
         data-auto-redirect="true"
         data-current-mission="0"
         class="hidden"></div>

    <div class="mx-auto max-w-2xl">

        {{-- Kartu utama --}}
        <div class="card-bright text-center">
            <div class="animate-bob mx-auto grid h-24 w-24 place-items-center rounded-full bg-sun-300 text-5xl shadow-sm">
                ⏳
            </div>

            <h1 class="mt-5 text-2xl font-black text-ink-900 sm:text-3xl">
                Jawabanmu Sudah Terkirim!
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-ink-700 sm:text-base">
                Hebat, kelompok <strong class="text-sky-600">{{ $team->name }}</strong> sudah menyelesaikan
                ronde ini. Sekarang tunggu aba-aba guru untuk membuka ronde berikutnya.
            </p>

            {{-- Status ronde yang sedang aktif --}}
            <div class="mt-6 rounded-2xl border-2 border-sky-100 bg-sky-50 p-4">
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-bold text-ink-800 shadow-sm">
                        <span class="text-lg">🎯</span>
                        Ronde aktif:
                        <span id="scr-round-now" class="text-sky-600">—</span>
                    </span>
                    <span id="wait-badge"
                          class="badge bg-sun-300/60 text-ink-800">
                        MENUNGGU GURU
                    </span>
                </div>
                <p id="wait-mission" class="mt-3 text-sm text-ink-700"></p>
            </div>

            {{-- Sisa waktu ronde (bila guru memakai timer) --}}
            <div id="wait-timer-box" class="mt-4 hidden">
                <p class="text-xs font-bold uppercase tracking-wider text-ink-500">Sisa waktu ronde</p>
                <p id="wait-timer" class="font-mono text-4xl font-black text-sky-600 tabular-nums">--:--</p>
            </div>

            {{-- Skor sementara --}}
            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border-2 border-mint-400/30 bg-mint-400/10 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-mint-600">Total XP Kelompok</p>
                    <p class="mt-1 text-3xl font-black text-mint-600">{{ $team->xp }}</p>
                </div>
                <div class="rounded-2xl border-2 border-sun-400/30 bg-sun-300/20 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-ink-600">Skor Sementara</p>
                    <p id="wait-score" class="mt-1 text-3xl font-black text-ink-800">—</p>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                <a href="{{ route('student.dashboard') }}" class="btn-secondary">
                    Lihat Semua Misi
                </a>
            </div>

            <p class="mt-4 text-xs text-ink-500">
                Halaman ini akan berpindah sendiri begitu guru membuka ronde berikutnya.
                Kamu tidak perlu me-refresh.
            </p>
        </div>

        {{-- Ajakan santai sambil menunggu --}}
        <div class="card-soft mt-5">
            <h2 class="flex items-center gap-2 text-sm font-black uppercase tracking-wider text-ink-700">
                <span class="text-lg">💡</span> Sambil Menunggu
            </h2>
            <ul class="mt-3 space-y-2 text-sm text-ink-700">
                <li class="flex gap-2">
                    <span class="text-mint-500">✓</span>
                    Cek lagi jawaban ronde tadi kalau ada yang ingin diperbaiki.
                </li>
                <li class="flex gap-2">
                    <span class="text-mint-500">✓</span>
                    Baca materi ronde berikutnya supaya lebih siap.
                </li>
                <li class="flex gap-2">
                    <span class="text-mint-500">✓</span>
                    Bantu teman satu kelompok yang masih kesulitan.
                </li>
            </ul>
        </div>
    </div>

    {{-- Skrip: pantau status ronde & tampilkan timer --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const url = @json(route('student.round.status'));
            const roundEl = document.getElementById('scr-round-now');
            const badgeEl = document.getElementById('wait-badge');
            const missionEl = document.getElementById('wait-mission');
            const timerBox = document.getElementById('wait-timer-box');
            const timerEl = document.getElementById('wait-timer');

            let remaining = null;
            let ticker = null;

            function renderTimer() {
                if (remaining === null) {
                    timerBox.classList.add('hidden');
                    return;
                }

                timerBox.classList.remove('hidden');
                const m = Math.floor(remaining / 60);
                const s = remaining % 60;
                timerEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            }

            function startTimer(seconds) {
                if (ticker) clearInterval(ticker);
                remaining = seconds === null ? null : Math.max(0, parseInt(seconds, 10));
                renderTimer();

                if (remaining === null || remaining <= 0) return;

                ticker = setInterval(function () {
                    remaining -= 1;
                    if (remaining <= 0) {
                        remaining = 0;
                        renderTimer();
                        clearInterval(ticker);
                        return;
                    }
                    renderTimer();
                }, 1000);
            }

            async function poll() {
                try {
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                    });

                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    const data = await res.json();

                    if (data.session_ended) {
                        badgeEl.textContent = 'SESI SELESAI';
                        badgeEl.className = 'badge bg-coral-400/20 text-coral-500';
                        missionEl.textContent = 'Terima kasih sudah bermain! Jawabanmu tetap tersimpan.';
                        return;
                    }

                    if (roundEl) {
                        roundEl.textContent = data.round > 0
                            ? data.round + ' dari ' + data.total
                            : 'belum ada';
                    }

                    // Ronde berikutnya sudah dibuka: pindah otomatis.
                    // (Hanya bila misi itu memang boleh dikerjakan kelompok ini.)
                    if (data.round > 0 && data.mission && data.can_work) {
                        badgeEl.textContent = 'RONDE DIBUKA!';
                        badgeEl.className = 'badge bg-mint-400/25 text-mint-600';
                        missionEl.textContent = 'Ronde ' + data.round + ' sudah dibuka. Kamu akan dipindahkan…';
                        window.TikSound?.play('success');
                        window.TikToast?.('Ronde ' + data.round + ' sudah dibuka!', 'success');

                        setTimeout(function () {
                            window.location.href = data.mission.url;
                        }, 900);

                        return;
                    }

                    if (data.mission) {
                        missionEl.textContent = 'Ronde ' + data.round + ': ' + data.mission.title;
                    } else {
                        missionEl.textContent = 'Guru belum membuka ronde berikutnya.';
                    }

                    startTimer(data.is_running ? data.seconds_remaining : null);
                } catch (e) {
                    missionEl.textContent = 'Menghubungkan ulang…';
                } finally {
                    setTimeout(poll, 4000);
                }
            }

            poll();
        });
    </script>

@endsection
