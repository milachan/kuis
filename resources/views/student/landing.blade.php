<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Masuk — TIK Mission</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-sky-50 font-sans text-ink-900 antialiased">

    <div id="tik-toast-container"
         class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"></div>

    @include('components.flash')

    <main class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-10">

        {{-- Sambutan --}}
        <div class="mb-6 text-center">
            <div class="animate-bob mx-auto grid h-24 w-24 place-items-center rounded-3xl bg-sky-500 text-5xl shadow-md">
                🎓
            </div>
            <h1 class="mt-4 text-3xl font-black tracking-tight text-ink-900 sm:text-4xl">
                TIK MISSION
            </h1>
            <p class="mt-1 text-sm font-black uppercase tracking-widest text-sky-600">
                Bab 4 · Sistem Komputer
            </p>
            <p class="mx-auto mt-4 max-w-md text-sm font-semibold leading-relaxed text-ink-700">
                Kuasai materi. Jawab pertanyaannya. Kumpulkan XP. Buka ronde berikutnya!
            </p>
        </div>

        {{-- Keunggulan singkat --}}
        <div class="mb-6 grid grid-cols-3 gap-2">
            @foreach ([
                ['icon' => '📚', 'title' => '7 Ronde', 'desc' => 'Materi lengkap'],
                ['icon' => '🤖', 'title' => 'Dinilai AI', 'desc' => 'Skor langsung'],
                ['icon' => '🏆', 'title' => 'Kumpulkan XP', 'desc' => 'Jadi juara'],
            ] as $item)
                <div class="rounded-2xl border-2 border-sky-100 bg-white p-3 text-center shadow-sm">
                    <p class="text-2xl">{{ $item['icon'] }}</p>
                    <p class="mt-1 text-xs font-black text-ink-900">{{ $item['title'] }}</p>
                    <p class="text-[10px] font-semibold text-ink-600">{{ $item['desc'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Tombol masuk --}}
        <a href="{{ route('student.join') }}" class="btn-primary w-full py-4 text-base">
            🚀 Mulai Bermain
        </a>

        <p class="mt-4 text-center text-xs font-semibold text-ink-600">
            Minta kode sesi kepada gurumu dulu ya.
        </p>

        {{-- Tautan guru --}}
        <div class="mt-8 text-center">
            <a href="{{ route('login') }}" class="text-xs font-bold text-ink-500 underline transition hover:text-sky-600">
                Masuk sebagai guru
            </a>
        </div>
    </main>

</body>
</html>
