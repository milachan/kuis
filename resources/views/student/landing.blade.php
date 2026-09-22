@extends('layouts.guest')

@section('title', 'TIK Mission — Operasi File Rahasia')

@section('content')
<div class="w-full max-w-2xl text-center">

    {{-- Badge --}}
    <span class="badge border border-cyan-accent/30 bg-cyan-accent/10 text-cyan-accent">
        Informatika Kelas 8 SMP/MTs · Bab 3
    </span>

    {{-- Judul --}}
    <h1 class="mt-5 text-4xl font-black leading-tight tracking-tight sm:text-5xl">
        <span class="bg-gradient-to-r from-cyan-accent via-teal-accent to-cyan-accent bg-clip-text text-transparent">
            TIK MISSION
        </span>
        <span class="mt-2 block text-xl font-semibold text-white/90 sm:text-2xl">
            Operasi File Rahasia
        </span>
    </h1>

    <p class="mx-auto mt-4 max-w-lg text-sm leading-relaxed text-white/60 sm:text-base">
        Kuasai materi. Jawab pertanyaannya. Kumpulkan skor. Buka ronde berikutnya.
    </p>

    {{-- Tombol utama --}}
    <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
        <a href="{{ route('student.join') }}" class="btn-primary w-full px-8 py-3 text-base sm:w-auto">
            Mulai Misi
        </a>
        <a href="{{ route('login') }}" class="btn-secondary w-full sm:w-auto">
            Masuk sebagai Guru
        </a>
    </div>

    {{-- Tiga pilar misi --}}
    <div class="mt-10 grid gap-3 text-left sm:grid-cols-3">
        @foreach ([
            ['icon' => '📚', 'title' => 'Materi Lengkap', 'desc' => 'Sepuluh ronde ringkas dari Bab 3 TIK.'],
            ['icon' => '🤖', 'title' => 'Dinilai AI', 'desc' => 'Jawabanmu langsung diberi skor oleh AI.'],
            ['icon' => '🔓', 'title' => 'Buka Ronde Berikutnya', 'desc' => 'Temukan kode rahasia untuk melanjutkan ronde.'],
        ] as $item)
            <div class="panel p-4">
                <div class="text-2xl">{{ $item['icon'] }}</div>
                <h3 class="mt-2 text-sm font-semibold text-white">{{ $item['title'] }}</h3>
                <p class="mt-1 text-xs leading-relaxed text-white/60">{{ $item['desc'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Aturan AI --}}
    <div class="mt-6 rounded-xl border border-gold/25 bg-gold/5 p-4 text-left">
        <h3 class="flex items-center gap-2 text-sm font-bold text-gold">🤖 AI DIPERBOLEHKAN</h3>
        <p class="mt-2 text-xs leading-relaxed text-gold/90">
            AI boleh kamu gunakan untuk mencari langkah penggunaan aplikasi, memahami istilah,
            atau meminta penjelasan shortcut. AI <strong>tidak boleh</strong> mengerjakan seluruh
            tugas atau membuat bukti palsu. Yang dinilai adalah kemampuan kalian melakukan praktik.
        </p>
    </div>

</div>
@endsection
