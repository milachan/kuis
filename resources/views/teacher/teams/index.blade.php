@extends('layouts.teacher')

@section('title', 'Kelompok')
@section('page-title', 'Kelompok')
@section('page-subtitle', 'Progres dan XP setiap kelompok')

@section('content')

    {{-- Filter sesi --}}
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
            <a href="{{ route('teacher.teams') }}" class="btn-secondary">Reset</a>
        @endif
    </form>

    @if ($teams->isEmpty())
        <div class="panel p-10 text-center">
            <p class="text-4xl">👥</p>
            <h3 class="mt-3 text-lg font-bold">Belum ada kelompok</h3>
            <p class="mt-1 text-sm text-white/50">Kelompok akan muncul setelah siswa bergabung memakai kode sesi.</p>
        </div>
    @else
        <div class="panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-white/10 bg-navy-900/60 text-xs uppercase tracking-wider text-white/50">
                        <tr>
                            <th class="px-4 py-3">Kelompok</th>
                            <th class="px-4 py-3">Sesi</th>
                            <th class="px-4 py-3 text-center">Anggota</th>
                            <th class="px-4 py-3 text-center">Progress</th>
                            <th class="px-4 py-3 text-right">XP</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
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
                                       class="font-semibold hover:text-cyan-accent">
                                        {{ $team->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-white/60">
                                    {{ $team->gameSession->name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-center text-white/70">{{ $team->members_count }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold {{ $isDone ? 'text-emerald-300' : 'text-white/80' }}">
                                        {{ $team->completed_count }}/{{ $totalMissions }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gold">{{ $team->xp }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($isDone)
                                        <span class="badge bg-emerald-500/15 text-emerald-300">SELESAI</span>
                                    @else
                                        <span class="badge bg-cyan-strong/15 text-cyan-accent">AKTIF</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('teacher.teams.show', $team) }}"
                                       class="text-xs font-semibold text-cyan-accent hover:underline">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $teams->links() }}</div>
    @endif

@endsection
