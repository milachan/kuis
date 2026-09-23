@extends('layouts.student')

@section('title', 'Daftar Misi')

@push('scripts')
    @vite('resources/js/round-watch.js')
@endpush

@section('content')

    {{-- Pengawas ronde: memberi tahu saat guru membuka ronde baru. --}}
    <div data-round-watch="{{ route('student.round.status') }}"
         data-auto-redirect="false"
         data-current-mission="0"
         class="hidden"></div>

    {{-- Ucapan selamat datang --}}
    <div class="card-bright mb-5">
        <div class="flex flex-wrap items-center gap-4">
            <div class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-sun-300 text-3xl">
                🚀
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-black uppercase tracking-widest text-sky-600">Bab 4 · Sistem Komputer</p>
                <h1 class="mt-1 text-xl font-black text-ink-900 sm:text-2xl">
                    Halo, {{ $team->name }}! 👋
                </h1>
                <p class="mt-1 text-sm text-ink-700">
                    Sesi: <strong>{{ $session->name }}</strong>
                    · Kode: <span class="rounded-lg bg-sky-100 px-2 py-0.5 font-mono font-bold text-sky-700">{{ $session->code }}</span>
                </p>
            </div>
        </div>
    </div>

    {{-- Progres --}}
    <div class="card-bright mb-6">
        <div class="flex items-center justify-between text-sm">
            <span class="font-black text-ink-800">📊 Progres Misi</span>
            <span class="text-lg font-black text-sky-600">{{ $percent }}%</span>
        </div>

        <div class="mt-2 h-4 w-full overflow-hidden rounded-full bg-sky-100">
            <div class="h-full rounded-full bg-gradient-to-r from-sky-400 to-mint-400 transition-all duration-500"
                 style="width: {{ $percent }}%"></div>
        </div>

        <p class="mt-2 text-xs font-semibold text-ink-600">
            {{ $completed }} dari {{ $total }} misi selesai · {{ $team->xp }} XP
            @if ($team->totalHintsUsed() > 0)
                · {{ $team->totalHintsUsed() }} petunjuk dipakai
            @endif
        </p>
    </div>

    {{-- Anggota kelompok --}}
    @if ($team->members->isNotEmpty())
        <div class="card-soft mb-6">
            <p class="mb-2 text-xs font-black uppercase tracking-wider text-ink-600">👥 Anggota Kelompok</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($team->members as $member)
                    <span class="rounded-full border-2 border-sky-200 bg-white px-3 py-1 text-xs font-bold text-ink-700">
                        {{ $member->name }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Jam kelas habis. Kelompok yang baru mulai mengerjakan ronde tetap punya
         jatah waktunya sendiri, jadi jangan ditakut-takuti dengan pesan merah. --}}
    @if (! $acceptsSubmissions)
        <div class="mb-6 rounded-2xl border-2 border-coral-400/40 bg-coral-400/10 p-4 text-sm font-semibold text-coral-500">
            <strong>Waktu sesi sudah habis.</strong>
            Kiriman baru tidak dapat diproses, tetapi hasil yang sudah kamu kirim tetap tersimpan.
        </div>
    @elseif ($session->isTimeUp())
        <div class="mb-6 rounded-2xl border-2 border-sun-400/40 bg-sun-300/20 p-4 text-sm font-semibold text-ink-700">
            <strong>Jam kelas sudah habis.</strong>
            Kamu masih bisa mengerjakan ronde yang baru kamu buka — jatah waktunya dihitung sejak
            kamu membuka rondenya.
        </div>
    @endif

    {{-- ============================ KARTU MISI ============================ --}}
    <h2 class="mb-3 text-sm font-black uppercase tracking-wider text-ink-600">
        📋 Daftar Misi
    </h2>

    <div class="grid gap-3 lg:grid-cols-2">
        @foreach ($progressList as $progress)
            @php
                $mission = $progress->mission;
                $isCompleted = $progress->status === \App\Models\TeamProgress::STATUS_COMPLETED;
                $isLocked = $progress->isLocked();
                $isWaiting = $progress->status === \App\Models\TeamProgress::STATUS_WAITING_VALIDATION;
                // Ronde yang sedang dibuka guru: kartu ini bisa dikerjakan sekarang.
                $isOpen = in_array((int) $mission->order, $openRoundNumbers, true);
            @endphp

            @if ($isLocked)
                {{-- Kartu misi terkunci --}}
                <div class="mission-card border-sky-100 bg-sky-50/70 opacity-80">
                    <div class="flex items-start gap-3">
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-white text-lg shadow-sm">
                            🔒
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-black uppercase tracking-wider text-ink-500">
                                Misi {{ $mission->numberLabel() }}
                            </p>
                            <h3 class="mt-0.5 truncate font-black text-ink-600">{{ $mission->title }}</h3>
                            <p class="mt-1 text-xs font-semibold text-ink-500">
                                Tunggu guru membuka ronde ini ya.
                            </p>
                        </div>
                    </div>
                    <span class="badge absolute right-3 top-3 bg-white text-ink-500">TERKUNCI</span>
                </div>
            @else
                {{-- Kartu misi yang bisa dikerjakan --}}
                <a href="{{ route('student.mission.show', $mission) }}"
                   class="mission-card group border-sky-200 hover:border-sky-400 hover:shadow-md">
                    <div class="flex items-start gap-3">
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-lg
                                    {{ $isCompleted ? 'bg-mint-400/20 text-mint-600' : 'bg-sky-100 text-sky-600' }}">
                            @if ($isCompleted) ✓
                            @elseif ($isWaiting) ⏳
                            @else ▶
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-black uppercase tracking-wider text-sky-600">
                                Misi {{ $mission->numberLabel() }}
                            </p>
                            <h3 class="mt-0.5 truncate font-black text-ink-900 group-hover:text-sky-600">
                                {{ $mission->title }}
                            </h3>
                            <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-ink-600">
                                {{ $mission->story }}
                            </p>

                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                @if ($isOpen)
                                    <span class="badge bg-mint-400/25 text-mint-600">🎯 SEDANG DIBUKA</span>
                                @endif
                                <span class="badge bg-sky-100 text-sky-700">
                                    {{ $mission->difficulty }}
                                </span>
                                <span class="badge bg-sun-300/50 text-ink-800">
                                    {{ $mission->xp }} XP
                                </span>
                                @if ($mission->questionCount() > 0)
                                    <span class="badge bg-grape-400/15 text-grape-500">
                                        {{ $mission->questionCount() }} soal
                                    </span>
                                @endif
                                @if ($isCompleted)
                                    <span class="badge bg-mint-400/20 text-mint-600">✓ SELESAI</span>
                                @elseif ($isWaiting)
                                    <span class="badge bg-sun-300/50 text-ink-800">⏳ TERKIRIM</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            @endif
        @endforeach
    </div>

    {{-- Selesai semua --}}
    @if ($completed >= $total && $total > 0)
        <div class="mt-6 rounded-3xl border-2 border-mint-400/40 bg-mint-400/10 p-6 text-center">
            <p class="text-4xl">🎉</p>
            <h3 class="mt-2 text-xl font-black text-mint-600">Semua Misi Selesai!</h3>
            <p class="mt-1 text-sm font-semibold text-ink-700">
                Tim {{ $team->name }} berhasil menuntaskan Bab 4 Sistem Komputer dengan {{ $team->xp }} XP.
            </p>
        </div>
    @endif

@endsection
