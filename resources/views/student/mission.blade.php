@extends('layouts.student')

@section('title', $mission->title)

@push('scripts')
    @vite('resources/js/round-watch.js')
@endpush

@section('content')

    {{--
        Pengawas ronde. Bila guru membuka ronde baru sementara murid masih
        berada di halaman ronde lama, halaman ini otomatis berpindah ke misi
        ronde yang baru (jawaban yang sudah diketik dikirim lebih dulu).
    --}}
    <div data-round-watch="{{ route('student.round.status') }}"
         data-auto-redirect="true"
         data-current-mission="{{ $mission->id }}"
         class="hidden"></div>

    {{-- Breadcrumb --}}
    <nav class="mb-4 text-sm font-semibold text-ink-600">
        <a href="{{ route('student.dashboard') }}" class="transition hover:text-sky-600">Daftar Misi</a>
        <span class="mx-1.5 text-ink-500">/</span>
        <span class="text-ink-900">Misi {{ $mission->numberLabel() }}</span>
    </nav>

    {{-- Waktu pengerjaan siswa (dihitung sejak halaman ini dibuka). --}}
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-3xl border-2 border-sky-100 bg-white px-4 py-3 shadow-sm">
        <div class="flex items-center gap-3">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-sky-100 text-2xl">⏱️</span>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-ink-500">Waktu Mengerjakanku</p>
                <p id="work-timer"
                   class="font-mono text-2xl font-black tabular-nums text-sky-600"
                   data-remaining="{{ $workSeconds }}"
                   data-round-status="{{ route('student.round.status') }}">
                    {{ $workSeconds === null ? 'Tanpa batas' : gmdate('i:s', $workSeconds) }}
                </p>
            </div>
        </div>
        <p class="text-[11px] leading-relaxed text-ink-600">
            Dihitung sejak kamu membuka ronde ini. Waktu habis = tidak bisa mengirim lagi.
        </p>
    </div>

    {{-- Status kiriman terakhir --}}
    @if ($submission && $submission->status === \App\Models\Submission::STATUS_REVISION)
        <div class="mb-5 rounded-3xl border-2 border-coral-400/40 bg-coral-400/10 p-4">
            <p class="flex items-center gap-2 text-sm font-black text-coral-500">
                <span class="text-lg">✏️</span> Perlu Perbaikan
            </p>
            @if ($submission->teacher_comment)
                <p class="mt-1.5 text-sm text-ink-800">
                    <strong>Catatan guru:</strong> {{ $submission->teacher_comment }}
                </p>
            @endif
            <p class="mt-1 text-xs font-semibold text-ink-600">Perbaiki jawabanmu, lalu kirim ulang di bawah.</p>
        </div>
    @elseif ($submission && $submission->status === \App\Models\Submission::STATUS_WAITING)
        <div class="mb-5 rounded-3xl border-2 border-sun-400/40 bg-sun-300/20 p-4">
            <p class="flex items-center gap-2 text-sm font-black text-ink-800">
                <span class="text-lg">⏳</span> Jawaban Terkirim
            </p>
            <p class="mt-1 text-xs font-semibold text-ink-700">
                Terkirim pada {{ $submission->submitted_at?->format('d/m/Y H:i') }}.
                Kamu masih bisa mengirim ulang bila perlu.
            </p>
            <p class="mt-2 rounded-2xl bg-white/70 px-3 py-2 text-xs font-semibold text-ink-700">
                👉 <strong>Sudah selesai?</strong> Tunggu aba-aba guru untuk ronde berikutnya.
                Halaman ini akan otomatis berpindah saat ronde baru dibuka, atau kamu bisa kembali ke
                <a href="{{ route('student.dashboard') }}" class="font-bold text-sky-600 underline">daftar misi</a>.
            </p>
        </div>
    @elseif ($progress->isCompleted())
        <div class="mb-5 rounded-3xl border-2 border-mint-400/40 bg-mint-400/10 p-4">
            <p class="flex items-center gap-2 text-sm font-black text-mint-600">
                <span class="text-lg">✅</span> Misi Selesai
            </p>
            <p class="mt-1 text-xs font-semibold text-ink-700">
                Misi ini sudah divalidasi guru. XP diperoleh: <strong>{{ $progress->xp }}</strong>.
            </p>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">

        {{-- ===================== KOLOM KIRI ===================== --}}
        <div class="space-y-5 lg:col-span-2">

            {{-- Judul misi --}}
            <div class="card-bright">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge bg-sky-100 text-sky-700">
                        MISI {{ $mission->numberLabel() }}
                    </span>
                    <span class="badge bg-grape-400/15 text-grape-500">{{ $mission->difficulty }}</span>
                    <span class="badge bg-sun-300/50 text-ink-800">{{ $mission->xp }} XP</span>
                    @if ($mission->questionCount() > 0)
                        <span class="badge bg-mint-400/15 text-mint-600">
                            {{ $mission->questionCount() }} SOAL
                        </span>
                    @endif
                </div>

                <h1 class="mt-3 text-xl font-black text-ink-900 sm:text-2xl">{{ $mission->title }}</h1>

                {{-- Ajakan bermain game (bila ronde ini punya game) --}}
                @if ($mission->hasGame())
                    @php $game = $mission->gameInfo(); @endphp
                    <div class="mt-3 flex flex-wrap items-center gap-3 rounded-2xl border-2 border-grape-400/30 bg-grape-400/10 p-3">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white text-2xl shadow-sm">
                            {{ $game['icon'] }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-black uppercase tracking-wider text-grape-500">
                                Ronde ini pakai game!
                            </p>
                            <p class="text-sm font-bold text-ink-900">{{ $game['label'] }}</p>
                            <p class="text-[11px] font-semibold text-ink-600">{{ $game['how'] }}</p>
                        </div>
                        @if (! $progress->isCompleted())
                            <a href="{{ route('student.mission.game', $mission) }}" class="btn-primary shrink-0 px-5 py-2.5 text-sm">
                                🎮 Main Game
                            </a>
                        @endif
                    </div>
                @endif

                {{-- Cerita --}}
                <div class="mt-3 rounded-2xl border-l-4 border-sky-400 bg-sky-50 p-3">
                    <p class="text-sm italic leading-relaxed text-ink-700">{{ $mission->story }}</p>
                </div>

                {{-- Tujuan --}}
                <div class="mt-4">
                    <p class="mb-1.5 text-xs font-black uppercase tracking-wider text-ink-500">🎯 Tujuan Pembelajaran</p>
                    <p class="text-sm leading-relaxed text-ink-800">{{ $mission->objective }}</p>
                </div>
            </div>

            {{-- Langkah belajar --}}
            @if (! empty($mission->instructions))
                <div class="card-bright">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-black uppercase tracking-wider text-sky-600">
                        <span class="text-lg">📋</span> Langkah Belajar
                    </h2>

                    <ol class="space-y-2.5">
                        @foreach ($mission->instructions as $index => $step)
                            <li class="flex gap-3">
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-sky-100 text-xs font-black text-sky-700">
                                    {{ $index + 1 }}
                                </span>
                                <span class="pt-0.5 text-sm leading-relaxed text-ink-800">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            {{-- Form kiriman --}}
            @if ($progress->isCompleted())
                <div class="card-bright">
                    <h2 class="mb-3 text-sm font-black uppercase tracking-wider text-mint-600">
                        ✅ Jawaban yang Terkirim
                    </h2>
                    @include('student.partials.submission-detail', ['submission' => $submission])
                </div>
            @else
                <div class="card-bright">
                    <h2 class="mb-1 flex items-center gap-2 text-sm font-black uppercase tracking-wider text-sky-600">
                        <span class="text-lg">📤</span> Kirim Jawabanmu
                    </h2>
                    <p class="mb-4 text-xs font-semibold text-ink-600">
                        Jawab pertanyaan di bawah ini, lalu kirim. AI akan menilai jawabanmu.
                    </p>

                    @if (! $session->acceptsSubmissions())
                        <div class="rounded-2xl border-2 border-coral-400/40 bg-coral-400/10 p-3 text-sm font-semibold text-coral-500">
                            {{ $session->isEnded() ? 'Sesi sudah berakhir.' : 'Waktu sesi sudah habis.' }}
                            Kiriman baru tidak dapat diproses.
                        </div>
                    @else
                        @include('student.partials.submission-form', [
                            'mission' => $mission,
                            'submission' => $submission,
                        ])
                    @endif
                </div>
            @endif
        </div>

        {{-- ===================== KOLOM KANAN ===================== --}}
        <div class="space-y-5">

            {{-- Kode rahasia --}}
            @if (! $progress->isCompleted())
                <div class="rounded-3xl border-2 border-sun-400/40 bg-sun-300/20 p-5 shadow-sm">
                    <h2 class="mb-1 flex items-center gap-2 text-sm font-black uppercase tracking-wider text-ink-800">
                        <span class="text-lg">🔑</span> Kode Rahasia
                    </h2>
                    <p class="mb-4 text-xs font-semibold leading-relaxed text-ink-700">
                        {{ $mission->code_prompt ?? 'Masukkan kode rahasia yang kamu temukan.' }}
                    </p>

                    @if ($progress->isCompleted())
                        <div class="rounded-2xl border-2 border-mint-400/40 bg-white p-3 text-center text-sm font-black text-mint-600">
                            ✓ Kode sudah terverifikasi
                        </div>
                    @else
                        <label for="code" class="label-field">Kode ditemukan</label>
                        <input id="code"
                               type="text"
                               name="code"
                               form="submission-form"
                               autocomplete="off"
                               placeholder="TULIS KODE DI SINI"
                               class="input-field text-center font-mono text-lg font-black uppercase tracking-widest">
                        <p class="mt-2 text-xs font-semibold text-ink-600">
                            Kode tidak ditampilkan di halaman ini. Temukan dari layar guru.
                        </p>
                    @endif
                </div>
            @endif

            {{-- Petunjuk --}}
            @if ($hintsEnabled)
                <div class="card-bright">
                    <h2 class="mb-1 flex items-center gap-2 text-sm font-black uppercase tracking-wider text-ink-800">
                        <span class="text-lg">💡</span> Petunjuk
                    </h2>
                    <p class="mb-3 text-xs font-semibold text-ink-600">
                        Membuka petunjuk mengurangi XP sebesar {{ config('tikmission.hint_penalty_xp') }}.
                    </p>

                    <div class="space-y-2">
                        <button type="button"
                                data-hint-level="1"
                                class="btn-secondary w-full justify-start text-xs">
                            Buka Petunjuk 1
                        </button>
                        <button type="button"
                                data-hint-level="2"
                                class="btn-secondary w-full justify-start text-xs">
                            Buka Petunjuk 2
                        </button>
                    </div>

                    <div id="hint-output" class="mt-3 space-y-2"></div>
                </div>
            @endif

            {{-- Peluang XP --}}
            <div class="card-soft">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-black uppercase tracking-wider text-ink-700">
                    <span class="text-lg">🏆</span> Peluang XP
                </h2>
                <ul class="space-y-2 text-xs font-semibold">
                    <li class="flex justify-between text-ink-700">
                        <span>Menyelesaikan misi</span>
                        <span class="font-black text-sky-600">+{{ $mission->xp }} XP</span>
                    </li>
                    <li class="flex justify-between text-ink-700">
                        <span>Tanpa memakai petunjuk</span>
                        <span class="font-black text-mint-600">+{{ config('tikmission.no_hint_bonus_xp') }} XP</span>
                    </li>
                    <li class="flex justify-between text-ink-700">
                        <span>Menjawab dengan bahasamu sendiri</span>
                        <span class="font-black text-grape-500">+{{ config('tikmission.own_words_bonus_xp') }} XP</span>
                    </li>
                </ul>
            </div>

            {{-- Aturan AI --}}
            <div class="rounded-3xl border-2 border-grape-400/30 bg-grape-400/10 p-4">
                <h3 class="flex items-center gap-2 text-xs font-black text-grape-500">
                    <span class="text-base">🤖</span> AI BOLEH MEMBANTU
                </h3>
                <p class="mt-1.5 text-[11px] font-semibold leading-relaxed text-ink-700">
                    Gunakan AI untuk memahami materi atau mencari istilah. Namun jawabanmu harus
                    ditulis dengan bahasamu sendiri — jawaban salinan mudah dikenali dan nilainya rendah.
                </p>
            </div>
        </div>
    </div>

    {{-- Skrip petunjuk --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const output = document.getElementById('hint-output');
            const url = @json(route('student.mission.hint', $mission));
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            document.querySelectorAll('[data-hint-level]').forEach(function (btn) {
                btn.addEventListener('click', async function () {
                    const level = btn.dataset.hintLevel;
                    btn.disabled = true;

                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({ level: Number(level) }),
                        });

                        const data = await res.json();

                        if (!res.ok || !data.ok) {
                            window.TikToast(data.message || 'Petunjuk tidak tersedia.', 'error');
                            return;
                        }

                        const box = document.createElement('div');
                        box.className = 'animate-fade-up rounded-2xl border-2 border-sun-400/40 bg-sun-300/20 p-3';
                        box.innerHTML =
                            '<p class="text-[10px] font-black uppercase tracking-wider text-ink-700">Petunjuk ' +
                            data.level + '</p>' +
                            '<p class="mt-1 text-xs font-semibold leading-relaxed text-ink-800">' + data.text + '</p>';

                        output.appendChild(box);
                        window.TikToast(data.message, data.penalty > 0 ? 'warning' : 'info');
                    } catch (e) {
                        window.TikToast('Gagal memuat petunjuk. Coba lagi.', 'error');
                    } finally {
                        btn.disabled = false;
                    }
                });
            });
        });
    </script>

@endsection
