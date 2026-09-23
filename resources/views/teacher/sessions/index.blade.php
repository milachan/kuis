@extends('layouts.teacher')

@section('title', 'Sesi')
@section('page-title', 'Sesi Permainan')
@section('page-subtitle', 'Kelola sesi, kode, dan timer')

@section('page-actions')
    <a href="{{ route('teacher.sessions.create') }}" class="btn-primary">+ Buat Sesi Baru</a>
@endsection

@section('content')

    @if ($sessions->isEmpty())
        <div class="panel p-10 text-center">
            <p class="text-4xl">🎮</p>
            <h3 class="mt-3 text-lg font-bold">Belum ada sesi</h3>
            <p class="mt-1 text-sm text-white/50">Buat sesi pertama untuk memulai permainan.</p>
            <a href="{{ route('teacher.sessions.create') }}" class="btn-primary mt-5">+ Buat Sesi Baru</a>
        </div>
    @else
        <div class="panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-white/10 bg-navy-900/60 text-xs uppercase tracking-wider text-white/50">
                        <tr>
                            <th class="px-4 py-3">Nama Sesi</th>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3 text-center">Durasi</th>
                            <th class="px-4 py-3 text-center">Kelompok</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($sessions as $session)
                            <tr class="transition hover:bg-white/5">
                                <td class="px-4 py-3">
                                    <a href="{{ route('teacher.sessions.show', $session) }}"
                                       class="font-semibold text-white hover:text-cyan-accent">
                                        {{ $session->name }}
                                    </a>
                                    @if ($session->is_demo)
                                        <span class="badge ml-2 bg-gold/15 text-gold">DEMO</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono font-bold text-cyan-accent">{{ $session->code }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-white/70">
                                    {{ $session->duration_minutes > 0 ? $session->duration_minutes.' menit' : 'Tanpa timer' }}
                                </td>
                                <td class="px-4 py-3 text-center text-white/70">{{ $session->teams_count }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($session->isEnded())
                                        <span class="badge bg-white/5 text-white/40">BERAKHIR</span>
                                    @elseif ($session->isTimeUp())
                                        <span class="badge bg-rose-500/15 text-rose-300">WAKTU HABIS</span>
                                    @else
                                        <span class="badge bg-emerald-500/15 text-emerald-300">AKTIF</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('teacher.sessions.show', $session) }}"
                                       class="text-xs font-semibold text-cyan-accent hover:underline">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $sessions->links() }}</div>
    @endif

@endsection
