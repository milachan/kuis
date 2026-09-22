@extends('layouts.teacher')

@section('title', 'Kelola Misi')
@section('page-title', 'Kelola Misi')
@section('page-subtitle', 'Lihat dan sesuaikan isi misi dengan materi kelasmu')

@section('content')

    <div class="space-y-3">
        @foreach ($missions as $mission)
            <div class="panel p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold text-cyan-accent/70">
                                MISI {{ $mission->numberLabel() }}
                            </span>
                            <span class="badge bg-white/5 text-white/60">{{ $mission->difficulty }}</span>
                            <span class="badge bg-gold/10 text-gold">{{ $mission->xp }} XP</span>
                            @if ($mission->requires_pdf)
                                <span class="badge bg-rose-500/15 text-rose-200">WAJIB PDF</span>
                            @endif
                            @if (! $mission->is_active)
                                <span class="badge bg-white/5 text-white/40">NONAKTIF</span>
                            @endif
                        </div>

                        <h3 class="mt-1.5 font-semibold">{{ $mission->title }}</h3>
                        <p class="mt-1 line-clamp-2 text-sm text-white/50">{{ $mission->story }}</p>

                        <p class="mt-2 text-xs text-white/40">
                            {{ count($mission->instructions ?? []) }} langkah praktik ·
                            Petunjuk: {{ $mission->hint_1 ? 'ada' : 'tidak ada' }} /
                            {{ $mission->hint_2 ? 'ada' : 'tidak ada' }}
                        </p>
                    </div>

                    <a href="{{ route('teacher.missions.edit', $mission) }}" class="btn-secondary shrink-0 text-xs">
                        Edit Misi
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    <div class="panel mt-6 p-5">
        <h2 class="mb-2 text-sm font-bold uppercase tracking-wider text-white/70">Cara Menambah atau Mengubah Misi</h2>
        <p class="text-sm leading-relaxed text-white/60">
            Untuk mengubah isi misi, gunakan tombol <strong>Edit Misi</strong> di atas.
            Untuk menambah misi baru atau mengubah urutannya secara mendasar, sunting
            <code class="rounded bg-navy-900 px-1.5 py-0.5 font-mono text-xs text-cyan-accent">database/seeders/DatabaseSeeder.php</code>
            lalu jalankan <code class="rounded bg-navy-900 px-1.5 py-0.5 font-mono text-xs text-cyan-accent">php artisan db:seed</code>.
            Detailnya ada di dokumentasi <code class="rounded bg-navy-900 px-1.5 py-0.5 font-mono text-xs text-cyan-accent">README.md</code>.
        </p>
    </div>

@endsection
