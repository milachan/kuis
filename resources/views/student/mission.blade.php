@extends('layouts.student')

@section('title', $mission->title)

@push('scripts')
    @vite('resources/js/round-watch.js')
@endpush

@section('content')

    {{--
        Pengawas ronde. Bila guru membuka ronde baru sementara murid masih
        berada di halaman ronde lama, halaman ini otomatis berpindah ke misi
        ronde yang baru (auto-redirect) supaya murid tidak tertinggal.
    --}}
    <div data-round-watch="{{ route('student.round.status') }}"
         data-auto-redirect="true"
         data-current-mission="{{ $mission->id }}"
         class="hidden"></div>

    {{-- Breadcrumb --}}
    <nav class="mb-4 text-sm text-white/50">
        <a href="{{ route('student.dashboard') }}" class="transition hover:text-cyan-accent">Dashboard Misi</a>
        <span class="mx-1.5">/</span>
        <span class="text-white/80">Misi {{ $mission->numberLabel() }}</span>
    </nav>

    {{-- Status kiriman terakhir --}}
    @if ($submission && $submission->status === \App\Models\Submission::STATUS_REVISION)
        <div class="mb-5 rounded-xl border border-rose-400/30 bg-rose-500/10 p-4">
            <p class="flex items-center gap-2 text-sm font-bold text-rose-200">✗ Perlu Perbaikan</p>
            @if ($submission->teacher_comment)
                <p class="mt-1.5 text-sm text-rose-200/90">Catatan guru: {{ $submission->teacher_comment }}</p>
            @endif
            <p class="mt-1 text-xs text-rose-200/70">Perbaiki jawabanmu, lalu kirim ulang di bawah.</p>
        </div>
    @elseif ($submission && $submission->status === \App\Models\Submission::STATUS_WAITING)
        <div class="mb-5 rounded-xl border border-gold/30 bg-gold/5 p-4">
            <p class="flex items-center gap-2 text-sm font-bold text-gold">⏳ Jawaban Terkirim</p>
            <p class="mt-1 text-xs text-gold/80">
                Jawaban sudah terkirim pada {{ $submission->submitted_at?->format('d/m/Y H:i') }}.
                Kamu masih bisa mengirim ulang bila perlu.
            </p>
            <p class="mt-2 rounded-lg bg-navy-950/50 px-3 py-2 text-xs text-white/70">
                👉 <strong>Sudah selesai?</strong> Tunggu aba-aba guru untuk ronde berikutnya.
                Halaman ini akan otomatis berpindah saat ronde baru dibuka, atau kamu bisa kembali ke
                <a href="{{ route('student.dashboard') }}" class="font-semibold text-cyan-accent hover:underline">dashboard</a>.
            </p>
        </div>
    @elseif ($progress->isCompleted())
        <div class="mb-5 rounded-xl border border-emerald-400/30 bg-emerald-500/10 p-4">
            <p class="flex items-center gap-2 text-sm font-bold text-emerald-200">✓ Misi Selesai</p>
            <p class="mt-1 text-xs text-emerald-200/80">
                Misi ini sudah divalidasi guru. XP diperoleh: {{ $progress->xp }}.
            </p>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">

        {{-- ===================== KOLOM KIRI ===================== --}}
        <div class="space-y-5 lg:col-span-2">

            {{-- Judul misi --}}
            <div class="panel p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge bg-cyan-strong/15 text-cyan-accent">
                        MISI {{ $mission->numberLabel() }}
                    </span>
                    <span class="badge bg-white/5 text-white/60">{{ $mission->difficulty }}</span>
                    <span class="badge bg-gold/10 text-gold">{{ $mission->xp }} XP</span>
                    @if ($mission->questionCount() > 0)
                        <span class="badge bg-cyan-strong/15 text-cyan-accent">
                            {{ $mission->questionCount() }} PERTANYAAN
                        </span>
                    @endif
                </div>

                <h1 class="mt-3 text-xl font-bold sm:text-2xl">{{ $mission->title }}</h1>

                {{-- Cerita --}}
                <div class="mt-3 rounded-lg border-l-2 border-cyan-accent/50 bg-navy-900/60 p-3">
                    <p class="text-sm italic leading-relaxed text-white/70">{{ $mission->story }}</p>
                </div>

                {{-- Tujuan --}}
                <div class="mt-4">
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-white/50">Tujuan Pembelajaran</p>
                    <p class="text-sm leading-relaxed text-white/80">{{ $mission->objective }}</p>
                </div>
            </div>

            {{-- Instruksi praktik --}}
            <div class="panel p-5">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-cyan-accent">
                    📋 Tugas Praktik
                </h2>

                <ol class="space-y-2.5">
                    @foreach ($mission->instructions ?? [] as $index => $step)
                        <li class="flex gap-3">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-cyan-strong/15 text-xs font-bold text-cyan-accent">
                                {{ $index + 1 }}
                            </span>
                            <span class="pt-0.5 text-sm leading-relaxed text-white/80">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Form kiriman --}}
            @if ($progress->isCompleted())
                <div class="panel p-5">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-emerald-300">
                        ✅ Jawaban yang Terkirim
                    </h2>
                    @include('student.partials.submission-detail', ['submission' => $submission])
                </div>
            @else
                <div class="panel p-5">
                    <h2 class="mb-1 text-sm font-bold uppercase tracking-wider text-cyan-accent">
                        📤 Kirim Jawabanmu
                    </h2>
                    <p class="mb-4 text-xs text-white/50">
                        Jawab pertanyaan di bawah ini, lalu kirim. AI akan menilai jawabanmu.
                    </p>

                    @if (! $session->acceptsSubmissions())
                        <div class="rounded-lg border border-rose-400/30 bg-rose-500/10 p-3 text-sm text-rose-200">
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
            <div class="panel border-gold/25 p-5">
                <h2 class="mb-1 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-gold">
                    🔑 Kode Rahasia
                </h2>
                <p class="mb-4 text-xs leading-relaxed text-white/50">
                    {{ $mission->code_prompt ?? 'Masukkan kode rahasia yang kamu temukan.' }}
                </p>

                @if ($progress->isCompleted())
                    <div class="rounded-lg border border-emerald-400/30 bg-emerald-500/10 p-3 text-center text-sm font-semibold text-emerald-200">
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
                           class="input-field text-center font-mono font-bold uppercase tracking-widest">
                    <p class="mt-2 text-xs text-white/40">
                        Kode tidak ditampilkan di halaman ini. Temukan dari hasil praktikmu.
                    </p>
                @endif
            </div>

            {{-- Petunjuk --}}
            @if ($hintsEnabled)
                <div class="panel p-5">
                    <h2 class="mb-1 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-white/80">
                        💡 Petunjuk
                    </h2>
                    <p class="mb-3 text-xs text-white/50">
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

                    @if ($progress->hints_used > 0)
                        <p class="mt-3 text-center text-xs text-gold">
                            Petunjuk dipakai: {{ $progress->hints_used }}
                        </p>
                    @endif
                </div>
            @elseif (! $session->hints_enabled)
                <div class="panel p-5 text-xs text-white/40">
                    Sistem petunjuk dinonaktifkan oleh guru untuk sesi ini.
                </div>
            @endif

            {{-- Info XP --}}
            @if (! $progress->isCompleted())
                <div class="panel p-5">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-white/80">🎯 Peluang XP</h2>
                    <ul class="space-y-2 text-xs">
                        <li class="flex justify-between text-white/70">
                            <span>Menyelesaikan misi</span>
                            <span class="font-bold text-cyan-accent">+{{ $mission->xp }} XP</span>
                        </li>
                        <li class="flex justify-between text-white/70">
                            <span>Tanpa memakai petunjuk</span>
                            <span class="font-bold text-emerald-300">+{{ config('tikmission.no_hint_bonus_xp') }} XP</span>
                        </li>
                        @if ($session->hasTimer())
                            <li class="flex justify-between text-white/70">
                                <span>Tepat waktu</span>
                                <span class="font-bold text-gold">+{{ config('tikmission.on_time_bonus_xp') }} XP</span>
                            </li>
                        @endif
                        @if ($progress->hints_used > 0)
                            <li class="flex justify-between border-t border-white/10 pt-2 text-white/70">
                                <span>Petunjuk dipakai ({{ $progress->hints_used }}×)</span>
                                <span class="font-bold text-rose-300">
                                    -{{ $progress->hints_used * config('tikmission.hint_penalty_xp') }} XP
                                </span>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif

            {{-- Aturan AI --}}
            <div class="rounded-xl border border-gold/25 bg-gold/5 p-4">
                <h3 class="text-xs font-bold text-gold">🤖 AI BOLEH MEMBANTU</h3>
                <p class="mt-1.5 text-[11px] leading-relaxed text-gold/80">
                    Gunakan AI untuk memahami materi atau mencari istilah. Namun jawabanmu harus
                    ditulis dengan bahasamu sendiri — jawaban salinan mudah dikenali dan nilainya rendah.
                </p>
            </div>
        </div>
    </div>

    {{-- Skrip petunjuk --}}
    @if ($hintsEnabled)
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
                            box.className = 'animate-fade-up rounded-lg border border-gold/30 bg-gold/5 p-3';
                            box.innerHTML =
                                '<p class="text-[10px] font-bold uppercase tracking-wider text-gold">Petunjuk ' +
                                data.level + '</p>' +
                                '<p class="mt-1 text-xs leading-relaxed text-white/80">' + data.text + '</p>';

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
    @endif

@endsection
