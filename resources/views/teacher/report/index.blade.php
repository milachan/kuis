@extends('layouts.teacher')

@section('title', 'Laporan')
@section('page-title', 'Laporan Hasil Belajar')
@section('page-subtitle', 'Rekap progres, XP, dan penggunaan petunjuk setiap kelompok')

@section('page-actions')
    <a href="{{ route('teacher.report.export', request()->only('session_id')) }}" class="btn-primary">
        ⬇ Export CSV
    </a>
@endsection

@section('content')

    {{-- Filter --}}
    <form method="GET" class="panel mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-0 flex-1">
            <label for="session_id" class="label-field">Filter Sesi</label>
            <select id="session_id" name="session_id" class="input-field">
                <option value="">Semua sesi</option>
                @foreach ($sessionOptions as $option)
                    <option value="{{ $option->id }}" @selected((int) $sessionFilter === $option->id)>
                        {{ $option->name }} ({{ $option->code }})
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">Terapkan</button>
        @if ($sessionFilter)
            <a href="{{ route('teacher.report') }}" class="btn-secondary">Reset</a>
        @endif
    </form>

    @if ($teams->isEmpty())
        <div class="panel p-10 text-center">
            <p class="text-4xl">📊</p>
            <h3 class="mt-3 text-lg font-bold">Belum ada data laporan</h3>
            <p class="mt-1 text-sm text-white/50">Data akan muncul setelah siswa mulai mengerjakan misi.</p>
        </div>
    @else
        <div class="panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-white/10 bg-navy-900/60 text-xs uppercase tracking-wider text-white/50">
                        <tr>
                            <th class="px-4 py-3">Kelompok</th>
                            <th class="px-4 py-3">Anggota</th>
                            <th class="px-4 py-3 text-center">Misi</th>
                            <th class="px-4 py-3 text-right">XP</th>
                            <th class="px-4 py-3 text-center">Petunjuk</th>
                            <th class="px-4 py-3">Mulai</th>
                            <th class="px-4 py-3">Selesai</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($teams as $team)
                            @php
                                $isDone = $totalMissions > 0 && $team->completed_count >= $totalMissions;
                            @endphp
                            <tr class="transition hover:bg-white/5">
                                <td class="px-4 py-3">
                                    <a href="{{ route('teacher.teams.show', $team) }}"
                                       class="font-semibold hover:text-cyan-accent">{{ $team->name }}</a>
                                    <p class="text-[11px] text-white/40">{{ $team->gameSession->name ?? '-' }}</p>
                                </td>
                                <td class="max-w-[14rem] px-4 py-3 text-xs text-white/60">
                                    {{ $team->members->pluck('name')->implode(', ') ?: '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold {{ $isDone ? 'text-emerald-300' : 'text-white/80' }}">
                                        {{ $team->completed_count }}/{{ $totalMissions }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gold">{{ $team->xp }}</td>
                                <td class="px-4 py-3 text-center text-white/70">
                                    {{ (int) ($team->total_hints ?? 0) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-white/60">
                                    {{ $team->started_at?->format('d/m/Y H:i') ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-white/60">
                                    {{ $team->completed_at?->format('d/m/Y H:i') ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($isDone)
                                        <span class="badge bg-emerald-500/15 text-emerald-300">SELESAI</span>
                                    @else
                                        <span class="badge bg-cyan-strong/15 text-cyan-accent">BELUM</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-3 text-xs text-white/40">
            Total {{ $teams->count() }} kelompok · {{ $totalMissions }} misi aktif.
            CSV dipisahkan dengan tanda titik koma (;) agar terbaca di Excel.
        </p>
    @endif

@endsection
